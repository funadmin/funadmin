<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\ApiAuth;
use PHPUnit\Framework\TestCase;

final class ApiAuthLayoutContractTest extends TestCase
{
    public function test_bearer_auth_controller_is_under_authentication_module(): void
    {
        self::assertTrue(class_exists(ApiAuth::class));
        self::assertTrue(method_exists(ApiAuth::class, 'login'));
        self::assertTrue(method_exists(ApiAuth::class, 'me'));
        self::assertTrue(method_exists(ApiAuth::class, 'logout'));
    }
}
