<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/** 迁移自 ThinkPHP 的代码仍调用 root_path()，Webman 下需返回带结尾分隔符的项目根目录。 */
final class RootPathHelperTest extends TestCase
{
    public function test_root_path_returns_project_root_with_trailing_separator(): void
    {
        self::assertTrue(function_exists('root_path'));
        self::assertSame(base_path() . DIRECTORY_SEPARATOR, root_path());
        self::assertSame(base_path() . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR, root_path('runtime'));
    }
}
