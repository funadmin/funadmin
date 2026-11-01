<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\Management;
use PHPUnit\Framework\TestCase;
use support\Request;

final class MirroredManagementHttpTest extends TestCase
{
    public function test_unauthenticated_management_request_uses_mirrored_controller(): void
    {
        $response = (new Management())->listing(new Request("GET /admin/management/users HTTP/1.1\r\n\r\n"), 'users');
        self::assertSame(401, $response->getStatusCode());
        self::assertSame(401, json_decode($response->rawBody(), true)['code']);
    }
}
