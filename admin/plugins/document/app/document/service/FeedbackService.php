<?php

declare(strict_types=1);

namespace app\document\service;

use app\document\model\DocumentFeedback;
use InvalidArgumentException;
use Webman\Limiter\Limiter;

/**
 * 读者「有帮助 / 无帮助」反馈。
 *
 * - 只接受已发布的公开文档；
 * - 访客标识由浏览器生成，只保存其 SHA-256；同一访客对同一文档重复提交时覆盖上一次选择，避免刷票；
 * - 按来源 IP（IPv6 按 /64 归并）限流，原始 IP 不入库。
 */
final class FeedbackService
{
    /** 「无帮助」可选原因；键入库，值用于展示。 */
    public const REASONS = [
        'incomplete' => '内容不完整',
        'unclear' => '描述不清楚',
        'outdated' => '内容已过时',
        'broken' => '示例无法运行',
        'missing' => '没有找到需要的内容',
        'other' => '其他',
    ];
    public const COMMENT_MAX = 500;
    public const THROTTLE_LIMIT = 30;
    public const THROTTLE_WINDOW = 600;

    /** @return array{helpful: bool} */
    public function submit(string $slug, array $input, string $ip): array
    {
        $target = (new PublicDocumentService())->target($slug);
        if ($target === null) throw new InvalidArgumentException('文档不存在或尚未公开', 404);

        $helpful = $input['helpful'] ?? null;
        if (!is_bool($helpful)) throw new InvalidArgumentException('请选择是否有帮助', 422);
        $visitor = $input['visitor'] ?? null;
        if (!is_string($visitor) || preg_match('/\A[A-Za-z0-9_-]{16,64}\z/D', $visitor) !== 1) throw new InvalidArgumentException('访客标识无效', 422);

        $reasons = [];
        $comment = '';
        if (!$helpful) {
            $raw = $input['reasons'] ?? [];
            if (!is_array($raw) || !array_is_list($raw) || count($raw) > count(self::REASONS)) throw new InvalidArgumentException('反馈原因无效', 422);
            foreach ($raw as $reason) {
                if (!is_string($reason) || !isset(self::REASONS[$reason])) throw new InvalidArgumentException('反馈原因无效', 422);
            }
            $reasons = array_values(array_intersect(array_keys(self::REASONS), $raw));
            $text = $input['comment'] ?? '';
            if (!is_string($text)) throw new InvalidArgumentException('反馈内容无效', 422);
            $comment = trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text));
            if (mb_strlen($comment) > self::COMMENT_MAX) throw new InvalidArgumentException('反馈内容不能超过 ' . self::COMMENT_MAX . ' 个字', 422);
        }

        if (!Limiter::attempt('document_feedback:' . self::bucket($ip), self::THROTTLE_LIMIT, self::THROTTLE_WINDOW)) {
            throw new InvalidArgumentException('提交过于频繁，请稍后再试', 429);
        }

        $now = date('Y-m-d H:i:s');
        DocumentFeedback::query()->upsert([[
            'document_id' => $target['id'], 'revision_no' => $target['revisionNo'], 'helpful' => $helpful,
            'reasons' => json_encode($reasons, JSON_THROW_ON_ERROR), 'comment' => $comment,
            'voter' => hash('sha256', 'document-feedback|' . $visitor), 'created_at' => $now, 'updated_at' => $now,
        ]], ['document_id', 'voter'], ['revision_no', 'helpful', 'reasons', 'comment', 'updated_at']);
        return ['helpful' => $helpful];
    }

    /**
     * 各文档的反馈计数。
     *
     * @param int[] $documentIds
     * @return array<int, array{helpful: int, unhelpful: int}>
     */
    public function counts(array $documentIds): array
    {
        $result = [];
        foreach ($documentIds as $id) $result[(int) $id] = ['helpful' => 0, 'unhelpful' => 0];
        if ($result === []) return [];
        $rows = DocumentFeedback::query()->whereIn('document_id', array_keys($result))
            ->groupBy('document_id', 'helpful')->select('document_id', 'helpful')->selectRaw('count(*) as total')->get();
        foreach ($rows as $row) $result[(int) $row->document_id][(bool) $row->helpful ? 'helpful' : 'unhelpful'] = (int) $row->total;
        return $result;
    }

    /** 后台反馈列表：按文档、是否有帮助筛选，最新在前。 */
    public function list(array $query): array
    {
        $page = max(1, min(10000, (int) ($query['page'] ?? 1)));
        $pageSize = max(1, min(100, (int) ($query['pageSize'] ?? 20)));
        $builder = DocumentFeedback::query()->from('document_feedback as f')->join('documents as d', 'd.id', '=', 'f.document_id')->whereNull('d.deleted_at');
        if (($query['documentId'] ?? '') !== '' && $query['documentId'] !== null) $builder->where('f.document_id', Rules::id($query['documentId']));
        $summary = ['helpful' => 0, 'unhelpful' => 0];
        foreach ((clone $builder)->groupBy('f.helpful')->select('f.helpful')->selectRaw('count(*) as total')->get() as $row) {
            $summary[(bool) $row->helpful ? 'helpful' : 'unhelpful'] = (int) $row->total;
        }
        $helpful = $query['helpful'] ?? '';
        if ($helpful !== '' && $helpful !== null) {
            if (!in_array($helpful, ['1', '0', 1, 0, true, false], true)) throw new InvalidArgumentException('筛选条件无效', 422);
            $builder->where('f.helpful', in_array($helpful, ['1', 1, true], true));
        }
        if (filter_var($query['withComment'] ?? false, FILTER_VALIDATE_BOOLEAN)) $builder->where('f.comment', '<>', '');
        $total = (clone $builder)->count();
        $rows = $builder->orderByDesc('f.updated_at')->orderByDesc('f.id')->forPage($page, $pageSize)
            ->get(['f.id', 'f.document_id', 'f.revision_no', 'f.helpful', 'f.reasons', 'f.comment', 'f.updated_at', 'd.title', 'd.slug']);
        return [
            'list' => $rows->map(static function ($row): array {
                $reasons = json_decode((string) $row->reasons, true);
                $reasons = is_array($reasons) ? array_values(array_filter($reasons, static fn ($r) => is_string($r) && isset(self::REASONS[$r]))) : [];
                return [
                    'id' => (int) $row->id, 'documentId' => (int) $row->document_id, 'title' => (string) $row->title, 'slug' => (string) $row->slug,
                    'revisionNo' => (int) $row->revision_no, 'helpful' => (bool) $row->helpful, 'reasons' => $reasons,
                    'comment' => (string) $row->comment, 'updatedAt' => (string) $row->updated_at,
                ];
            })->all(),
            'total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'summary' => $summary, 'reasons' => self::REASONS,
        ];
    }

    /** 限流键中的来源地址：IPv4 按单个地址；IPv6 按 /64 前缀归并；IPv4 映射地址按 IPv4 处理。 */
    public static function bucket(string $ip): string
    {
        $binary = @inet_pton($ip);
        if ($binary === false) return $ip;
        if (strlen($binary) === 16 && substr($binary, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") $binary = substr($binary, 12);
        if (strlen($binary) === 4) return (string) inet_ntop($binary);
        return inet_ntop(substr($binary, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
}
