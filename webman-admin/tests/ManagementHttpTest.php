<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\Management;
use PHPUnit\Framework\TestCase;
use support\Request;

final class ManagementHttpTest extends TestCase
{
    public function test_management_requires_browser_session_before_reading_data(): void
    {
        self::assertTrue(class_exists(Management::class), '后台管理 HTTP 接口尚未实现');
        $response = (new Management())->listing(new Request("GET /admin/management/users HTTP/1.1\r\n\r\n"), 'users');
        self::assertSame(401, $response->getStatusCode());
    }
}
