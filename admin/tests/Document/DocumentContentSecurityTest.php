<?php

declare(strict_types=1);

namespace Tests\Document;

use app\document\service\ContentValidator;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/bootstrap.php';

final class DocumentContentSecurityTest extends TestCase
{
    public function test_unknown_node_types_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContentValidator::clean(['type' => 'doc', 'content' => [['type' => 'script', 'content' => []]]]);
    }

    public function test_dangerous_link_marks_are_dropped_but_text_is_kept(): void
    {
        $clean = ContentValidator::clean(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => '点我', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']], ['type' => 'bold']]],
            ['type' => 'text', 'text' => '官网', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://funadmin.com', 'onclick' => 'x']]]],
        ]]]]);
        $nodes = $clean['content'][0]['content'];
        self::assertSame('点我', $nodes[0]['text']);
        self::assertSame([['type' => 'bold']], $nodes[0]['marks']);
        self::assertSame([['type' => 'link', 'attrs' => ['href' => 'https://funadmin.com']]], $nodes[1]['marks'], '未知属性如 onclick 必须丢弃');
    }

    public function test_safe_url_allows_only_http_mailto_site_paths_and_anchors(): void
    {
        foreach (['https://a.com', 'http://a.com/x', 'mailto:a@b.com', '/docs/x', '#intro'] as $ok) {
            self::assertSame($ok, ContentValidator::safeUrl($ok));
        }
        foreach (['javascript:alert(1)', 'data:text/html,x', '//evil.com', 'vbscript:x', ' https://a.com', "/a\\b"] as $bad) {
            self::assertNull(ContentValidator::safeUrl($bad), $bad);
        }
    }

    public function test_image_with_unsafe_src_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ContentValidator::clean(['type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'javascript:x']]]]);
    }

    public function test_technical_nodes_survive_and_mermaid_keeps_only_source(): void
    {
        $input = ['type' => 'doc', 'content' => [
            ['type' => 'codeBlock', 'attrs' => ['language' => 'php'], 'content' => [['type' => 'text', 'text' => 'echo 1;']]],
            ['type' => 'taskList', 'content' => [['type' => 'taskItem', 'attrs' => ['checked' => true], 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '完成']]]]]]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [['type' => 'tableHeader', 'content' => [['type' => 'paragraph']]]]]]],
            ['type' => 'callout', 'attrs' => ['tone' => 'warning'], 'content' => [['type' => 'paragraph']]],
            ['type' => 'mermaid', 'attrs' => ['source' => 'graph TD; A-->B', 'svg' => '<svg onload=x>']],
        ]];
        $clean = ContentValidator::clean($input);
        self::assertSame('php', $clean['content'][0]['attrs']['language']);
        self::assertTrue($clean['content'][1]['content'][0]['attrs']['checked']);
        self::assertSame('table', $clean['content'][2]['type']);
        self::assertSame('warning', $clean['content'][3]['attrs']['tone']);
        self::assertSame(['source' => 'graph TD; A-->B'], $clean['content'][4]['attrs'], 'Mermaid 只保存源码');
    }

    public function test_plain_text_extracts_searchable_text_including_code_and_mermaid(): void
    {
        $text = ContentValidator::plainText(['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => '安装']]],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'composer install']]],
            ['type' => 'mermaid', 'attrs' => ['source' => 'graph TD; Webman-->MySQL']],
        ]]);
        self::assertStringContainsString('安装', $text);
        self::assertStringContainsString('composer install', $text);
        self::assertStringContainsString('Webman-->MySQL', $text);
    }

    public function test_non_doc_root_and_oversized_or_too_deep_content_are_rejected(): void
    {
        foreach ([
            ['type' => 'paragraph'],
            'string',
            $this->nested(50),
        ] as $bad) {
            try {
                ContentValidator::clean($bad);
                self::fail('应拒绝非法正文');
            } catch (\InvalidArgumentException $e) {
                self::assertSame(422, $e->getCode());
            }
        }
    }

    private function nested(int $depth): array
    {
        $node = ['type' => 'paragraph'];
        for ($i = 0; $i < $depth; $i++) $node = ['type' => 'blockquote', 'content' => [$node]];
        return ['type' => 'doc', 'content' => [$node]];
    }
}
