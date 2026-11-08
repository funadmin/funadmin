<?php

declare(strict_types=1);

namespace app\document\service;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use support\Db;

/** 匿名阅读边界：始终读取当前发布修订，不查询草稿正文。 */
final class PublicDocumentService
{
    private function published(): Builder
    {
        return Db::table('documents as d')
            ->join('document_revisions as r', static function ($join): void {
                $join->on('r.document_id', '=', 'd.id')->on('r.revision_no', '=', 'd.current_revision_no');
            })
            ->whereNull('d.deleted_at')->where('d.status', 'published')->where('d.visibility', 'public');
    }

    public function listing(array $input): array
    {
        $query = $this->published();
        $page = $this->positive($input['page'] ?? '1', 10000);
        $pageSize = 20;
        if (($input['scope'] ?? '') !== '') $query->where('d.scope', Rules::scope($input['scope']));
        if (($input['categoryId'] ?? '') !== '') {
            $ids = $this->categoryDescendants(Rules::id($input['categoryId']));
            $query->whereIn('d.id', Db::table('document_category_links')->select('document_id')->whereIn('category_id', $ids));
        }
        $keyword = $input['keyword'] ?? '';
        if (!is_string($keyword)) throw new InvalidArgumentException('搜索关键词无效', 422);
        $keyword = mb_substr(trim($keyword), 0, 100);
        if ($keyword !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword) . '%';
            $title = $query->getGrammar()->wrap('r.title');
            $summary = $query->getGrammar()->wrap('r.summary');
            $query->where(static fn ($where) => $where->whereRaw($title . " LIKE ? ESCAPE '!'", [$like])->orWhereRaw($summary . " LIKE ? ESCAPE '!'", [$like]));
        }
        $total = (clone $query)->count();
        $rows = $query->orderByDesc('r.created_at')->orderByDesc('d.id')->forPage($page, $pageSize)
            ->get(['d.slug','d.scope','r.title','r.summary','r.created_at','r.revision_no']);
        return ['list'=>$rows->map($this->summary(...))->all(), 'total'=>$total, 'page'=>$page, 'pageSize'=>$pageSize];
    }

    public function find(string $slug): ?array
    {
        if (preg_match('/\A[a-z0-9][a-z0-9-]{0,118}[a-z0-9]?\z/D', $slug) !== 1) return null;
        $row = $this->published()->where('d.slug', $slug)->first([
            'd.slug','d.scope','d.plugin_code','d.type','d.applies_to',
            'r.title','r.summary','r.content_json','r.created_at','r.revision_no',
        ]);
        if (!$row) return null;
        return $this->summary($row) + [
            'pluginCode'=>(string)$row->plugin_code, 'type'=>(string)$row->type, 'appliesTo'=>(string)$row->applies_to,
            'content'=>ContentValidator::clean(json_decode((string)$row->content_json, true, 512, JSON_THROW_ON_ERROR)),
        ];
    }

    /** 只公开关联到公开文档的目录及其祖先，不泄漏仅后台目录名称。 */
    public function categories(): array
    {
        $linked = Db::table('document_category_links')->whereIn('document_id', $this->published()->select('d.id'))->pluck('category_id')->all();
        $rows = Db::table('document_categories')->orderBy('sort_order')->orderBy('id')->get()->keyBy('id');
        $visible = [];
        foreach ($linked as $id) {
            while ($id && !isset($visible[$id]) && isset($rows[$id])) {
                $visible[$id] = true;
                $id = (int)$rows[$id]->parent_id;
            }
        }
        return $rows->filter(static fn ($row): bool => isset($visible[$row->id]))->map(static fn ($row): array => [
            'id'=>(int)$row->id, 'parentId'=>(int)$row->parent_id, 'name'=>(string)$row->name, 'scope'=>(string)$row->scope,
        ])->values()->all();
    }

    private function categoryDescendants(int $id): array
    {
        $rows = Db::table('document_categories')->get(['id','parent_id']);
        $ids = [$id];
        for ($index=0; $index<count($ids); $index++) {
            foreach ($rows as $row) if ((int)$row->parent_id === $ids[$index] && !in_array((int)$row->id, $ids, true)) $ids[] = (int)$row->id;
        }
        return $ids;
    }

    private function summary(object $row): array
    {
        return ['slug'=>(string)$row->slug, 'title'=>(string)$row->title, 'summary'=>(string)$row->summary,
            'scope'=>(string)$row->scope, 'publishedAt'=>(string)$row->created_at, 'revisionNo'=>(int)$row->revision_no];
    }

    private function positive(mixed $value, int $maximum): int
    {
        if (!is_scalar($value) || filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>$maximum]]) === false) {
            throw new InvalidArgumentException('分页参数无效', 422);
        }
        return (int)$value;
    }
}
