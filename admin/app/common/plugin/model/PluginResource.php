<?php

declare(strict_types=1);

namespace app\common\plugin\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

final class PluginResource extends BaseModel
{
    use LaravelSoftDelete;

    /** 登记内容列及其缺省值：$records 缺少的列按缺省值比较，与整行重建的结果一致。 */
    private const CONTENT = [
        'plugin_code' => null, 'version' => null, 'resource_type' => 'file', 'publication_unit' => null,
        'operation_token' => null, 'source_path' => null, 'target_path' => null, 'sha256' => null, 'tree_hash' => null,
    ];

    protected $hidden = ['deleted_token'];

    protected $table = 'plugin_resource';
    protected $guarded = [];

    /**
     * 以 $records 整体替换插件某类资源（file / native_app）的登记，按 target_path 差异同步：
     * 内容未变的行不动，变化的行原地更新，不再登记的行软删除，缺少的行新增。
     * 卸载（$records 为空）只做软删除；唯一索引含 deleted_token，重新安装可再次登记相同的 target_path。
     * $records 可以是 toArray() 得到的快照行，其中的 id 与删除标记一律忽略。
     *
     * @param list<array<string, mixed>> $records
     */
    public static function replaceOwned(string $pluginCode, string $resourceType, array $records): void
    {
        $desired = [];
        foreach ($records as $record) {
            $desired[(string) $record['target_path']] = $record;
        }
        $kept = [];
        $removed = [];
        foreach (self::query()->where('plugin_code', $pluginCode)->where('resource_type', $resourceType)->orderBy('id')->get() as $row) {
            $target = (string) $row->target_path;
            if (!isset($desired[$target]) || isset($kept[$target])) {
                $removed[] = $row->getKey();
                continue;
            }
            $kept[$target] = true;
            $changes = [];
            foreach (array_intersect_key($desired[$target] + self::CONTENT, self::CONTENT) as $column => $value) {
                $current = $row->getAttribute($column);
                if (($current === null) !== ($value === null) || (string) $current !== (string) $value) $changes[$column] = $value;
            }
            if ($changes !== []) $row->forceFill($changes)->save();
        }
        if ($removed !== []) self::query()->whereKey($removed)->delete();
        foreach ($desired as $target => $record) {
            if (isset($kept[$target])) continue;
            self::query()->forceCreate(array_diff_key($record, array_flip(['id', 'deleted_at', 'deleted_token'])));
        }
    }
}
