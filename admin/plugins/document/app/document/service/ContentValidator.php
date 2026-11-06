<?php

declare(strict_types=1);

namespace app\document\service;

use InvalidArgumentException;

/**
 * Tiptap JSON 正文的服务端边界：只放行白名单节点与标记，
 * 丢弃危险链接，并提取用于搜索的纯文本。不渲染 HTML。
 */
final class ContentValidator
{
    private const NODES = [
        'doc', 'paragraph', 'text', 'heading', 'bulletList', 'orderedList', 'listItem', 'taskList', 'taskItem',
        'blockquote', 'codeBlock', 'hardBreak', 'horizontalRule', 'table', 'tableRow', 'tableHeader', 'tableCell',
        'image', 'callout', 'mermaid',
    ];
    private const MARKS = ['bold', 'italic', 'strike', 'underline', 'code', 'link'];
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_DEPTH = 40;

    /** 返回净化后的正文；结构非法时抛 422。 */
    public static function clean(mixed $content): array
    {
        if (!is_array($content) || ($content['type'] ?? null) !== 'doc') {
            throw new InvalidArgumentException('正文必须是 Tiptap 文档', 422);
        }
        $encoded = json_encode($content, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            throw new InvalidArgumentException('正文过大，请拆分为多篇文档', 422);
        }
        return self::node($content, 0);
    }

    public static function plainText(array $content): string
    {
        $parts = [];
        $walk = static function (array $node) use (&$walk, &$parts): void {
            if (($node['type'] ?? '') === 'text' && is_string($node['text'] ?? null)) $parts[] = $node['text'];
            if (($node['type'] ?? '') === 'mermaid' && is_string($node['attrs']['source'] ?? null)) $parts[] = $node['attrs']['source'];
            foreach ($node['content'] ?? [] as $child) if (is_array($child)) $walk($child);
            if (in_array($node['type'] ?? '', ['paragraph', 'heading', 'codeBlock', 'listItem', 'taskItem', 'tableCell', 'tableHeader'], true)) $parts[] = "\n";
        };
        $walk($content);
        return trim(preg_replace("/\n{2,}/", "\n", implode('', $parts)) ?? '');
    }

    /** 只允许 http(s)、mailto、站内路径和锚点。 */
    public static function safeUrl(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        // 不做 trim：前导空白/控制字符是常见的过滤绕过手法，直接拒绝。
        $url = $value;
        if ($url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) return null;
        return preg_match('~\A(?:https?://|mailto:|/(?!/)|#)~i', $url) ? $url : null;
    }

    private static function node(mixed $node, int $depth): array
    {
        if (!is_array($node) || !is_string($node['type'] ?? null)) throw new InvalidArgumentException('正文节点格式无效', 422);
        if ($depth > self::MAX_DEPTH) throw new InvalidArgumentException('正文嵌套过深', 422);
        $type = $node['type'];
        if (!in_array($type, self::NODES, true)) throw new InvalidArgumentException('正文包含不支持的内容：' . $type, 422);

        $clean = ['type' => $type];
        if ($type === 'text') {
            if (!is_string($node['text'] ?? null)) throw new InvalidArgumentException('文本节点无效', 422);
            $clean['text'] = $node['text'];
            $marks = self::marks($node['marks'] ?? []);
            if ($marks) $clean['marks'] = $marks;
            return $clean;
        }
        $attrs = self::attrs($type, is_array($node['attrs'] ?? null) ? $node['attrs'] : []);
        if ($attrs !== null) $clean['attrs'] = $attrs;
        if ($type === 'image' && ($attrs['src'] ?? null) === null) {
            throw new InvalidArgumentException('图片地址无效', 422);
        }
        if (isset($node['content'])) {
            if (!is_array($node['content'])) throw new InvalidArgumentException('正文子节点无效', 422);
            $clean['content'] = array_map(static fn ($child) => self::node($child, $depth + 1), array_values($node['content']));
        }
        return $clean;
    }

    private static function marks(mixed $marks): array
    {
        if (!is_array($marks)) return [];
        $result = [];
        foreach ($marks as $mark) {
            $type = is_array($mark) ? ($mark['type'] ?? null) : null;
            if (!in_array($type, self::MARKS, true)) continue;
            if ($type === 'link') {
                $href = self::safeUrl($mark['attrs']['href'] ?? null);
                if ($href === null) continue; // 危险链接只去掉链接，保留文字
                $result[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                continue;
            }
            $result[] = ['type' => $type];
        }
        return $result;
    }

    private static function attrs(string $type, array $attrs): ?array
    {
        return match ($type) {
            'heading' => ['level' => max(1, min(6, (int) ($attrs['level'] ?? 2)))],
            'orderedList' => ['start' => max(1, (int) ($attrs['start'] ?? 1))],
            'taskItem' => ['checked' => (bool) ($attrs['checked'] ?? false)],
            'codeBlock' => ['language' => preg_match('/\A[a-z0-9+#.-]{1,32}\z/i', (string) ($attrs['language'] ?? '')) ? (string) $attrs['language'] : null],
            'callout' => ['tone' => in_array($attrs['tone'] ?? '', ['info', 'tip', 'warning', 'danger'], true) ? $attrs['tone'] : 'info'],
            'mermaid' => ['source' => is_string($attrs['source'] ?? null) ? mb_substr($attrs['source'], 0, 20000) : ''],
            'image' => ['src' => self::safeUrl($attrs['src'] ?? null), 'alt' => is_string($attrs['alt'] ?? null) ? mb_substr($attrs['alt'], 0, 200) : ''],
            'tableCell', 'tableHeader' => ['colspan' => max(1, (int) ($attrs['colspan'] ?? 1)), 'rowspan' => max(1, (int) ($attrs['rowspan'] ?? 1))],
            default => null,
        };
    }
}
