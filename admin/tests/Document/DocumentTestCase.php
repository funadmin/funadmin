<?php

declare(strict_types=1);

namespace Tests\Document;

require_once __DIR__ . '/bootstrap.php';

use app\document\service\Schema;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Request;

abstract class DocumentTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop();
        Schema::install();
        $this->actAs(1);
    }

    protected function tearDown(): void
    {
        Schema::drop();
        Context::destroy();
        parent::tearDown();
    }

    protected function actAs(int $adminId, string $method = 'GET', array $payload = []): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $request = new Request("$method /admin/plugins/document HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => $adminId];
        Context::set(\Webman\Http\Request::class, $request);
    }

    /** 最小合法 Tiptap 文档。 */
    protected function doc(string $text = '正文'): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]]]];
    }
}
