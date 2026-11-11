<?php
declare(strict_types=1);
namespace app\cms\service;
use app\common\model\concern\TokenSoftDeletingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use InvalidArgumentException;
use support\Db;

/**
 * CMS 软删除与回收站的公共规则（1.6.0）。
 *
 * - 级联删除：父记录与子记录用同一个删除时间（trash 显式写入），恢复时按 deleted_at 完全相等找回一起删除的子记录；
 *   之前单独删除或被编辑移除的子记录时间不同，不会被带回。
 * - 回收站批量操作的 ids 严格校验：必须是 1–100 个合法 ID 的数组，去重；任何一个不存在或不在回收站就整批 404。
 * - 恢复撞唯一值抛 RestoreConflictException（409）；彻底删除只作用于 onlyTrashed() 的行。
 */
final class RecycleBin
{
    public const BATCH_LIMIT = 100;
    public const PAGE_SIZE = 20;

    /** @return list<string> */
    public static function ids(mixed $ids): array
    {
        if (!is_array($ids) || !array_is_list($ids) || $ids === []) throw new InvalidArgumentException('ids 必须为非空数组', 422);
        if (count($ids) > self::BATCH_LIMIT) throw new InvalidArgumentException('一次最多处理 ' . self::BATCH_LIMIT . ' 条', 422);
        $result = [];
        foreach ($ids as $id) {
            if (!is_string($id) && !is_int($id)) throw new InvalidArgumentException('ID 无效', 422);
            try {
                $result[] = Input::id($id);
            } catch (InvalidArgumentException) {
                throw new InvalidArgumentException('ID 无效', 422);
            }
        }
        return array_values(array_unique($result));
    }

    /**
     * 回收站列表的分页参数（与核心回收站列表一致：page 从 1 开始，pageSize 1–100，默认 20）。
     * @return array{0:int,1:int}
     */
    public static function paging(mixed $page, mixed $pageSize): array
    {
        $page = filter_var($page, FILTER_VALIDATE_INT);
        $size = filter_var($pageSize, FILTER_VALIDATE_INT);
        return [max(1, min(10000, $page === false ? 1 : $page)), max(1, min(100, $size === false ? self::PAGE_SIZE : $size))];
    }

    /** @return array{list:list<array>,total:int,page:int,pageSize:int} */
    public static function result(array $list, int $total, int $page, int $pageSize): array
    {
        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize];
    }

    /**
     * 一次查询统计与父记录一起删除的子记录数：按 (父 id, deleted_at) 分组，返回 "父id|deleted_at" => 数量。
     * @param list<string> $parents
     * @return array<string,int>
     */
    public static function childCounts(Builder $children, string $parentColumn, array $parents): array
    {
        if ($parents === []) return [];
        $counts = [];
        foreach ($children->whereIn($parentColumn, $parents)->toBase()->selectRaw($parentColumn . ' as parent_id, deleted_at, count(*) as aggregate')->groupBy($parentColumn, 'deleted_at')->get() as $row) {
            $counts[$row->parent_id . '|' . $row->deleted_at] = (int)$row->aggregate;
        }
        return $counts;
    }

    /** 本次删除使用的时间（UTC，与其他 CMS 时间一致）。 */
    public static function now(): string
    {
        return Input::now();
    }

    /**
     * 以给定时间软删除查询命中的未删除行；表上有 deleted_token 时写入各行自身主键。
     * 必须在事务内调用。返回影响行数。
     */
    public static function trash(Builder $query, string $at): int
    {
        if (Db::connection()->transactionLevel() === 0) throw new \LogicException('Soft delete cascade requires a transaction');
        $model = $query->getModel();
        $columns = ['deleted_at' => $at];
        if (TokenSoftDeletingScope::modelHasDeletedToken($model)) {
            $columns['deleted_token'] = new Expression($query->getQuery()->getGrammar()->wrap($model->getKeyName()));
        }
        return $query->update($columns);
    }

    /**
     * 按 id 取回收站中的行并加锁；有任何一个不存在（或未删除、不属于本站）就整批 404。
     * @param class-string<Model> $model
     * @param list<string> $ids
     * @return list<Model>
     */
    public static function locked(string $model, string $siteId, array $ids, string $missing): array
    {
        $rows = $model::onlyTrashed()->where('site_id', $siteId)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->all();
        if (count($rows) !== count($ids)) throw new InvalidArgumentException($missing, 404);
        return $rows;
    }

    /** 软删除行的删除时间（原样字符串，用于与子记录完全相等匹配）。 */
    public static function deletedAt(Model $row): string
    {
        return (string)$row->getRawOriginal('deleted_at');
    }
}
