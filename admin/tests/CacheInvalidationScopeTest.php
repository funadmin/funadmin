<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\SystemPermission;
use app\common\model\Config;
use app\common\service\database\MigrationService;
use app\common\service\member\MemberAccountService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Cache;
use support\Context;
use support\Db;
use support\Request;

/**
 * 缓存失效必须按键收敛：后台授权写入不能清空整个缓存（会员注册节流、表单幂等键等都在同一 store），
 * 配置写入只失效对应的 syscfg 键，且下一次读取立即看到新值。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CacheInvalidationScopeTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_admin_permission_write_does_not_reset_member_registration_throttle(): void
    {
        $ip = '198.51.100.' . random_int(1, 254);
        $accounts = new MemberAccountService();
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            self::assertSame('用户名需以字母开头，4 到 20 位字母、数字或下划线', $this->registerError($accounts, $ip), "attempt {$attempt} is counted, then rejected by validation");
        }
        self::assertSame('注册过于频繁，请稍后再试', $this->registerError($accounts, $ip));

        $sentinel = 'cache_scope_sentinel_' . bin2hex(random_bytes(6));
        Cache::set($sentinel, 'kept', 600);
        $response = $this->adminCall(SystemPermission::class, 'POST', '/admin/system/permission', [
            'name' => '缓存范围测试', 'appName' => 'admin', 'resourceType' => 'route',
            'object' => 'cachescope/probe' . bin2hex(random_bytes(3)), 'action' => 'index',
        ], static fn (SystemPermission $controller) => $controller->create());
        self::assertSame(200, $response['code'], (string) ($response['msg'] ?? ''));

        self::assertSame('kept', Cache::get($sentinel), 'an authorization write must not flush unrelated cache entries');
        self::assertSame('注册过于频繁，请稍后再试', $this->registerError($accounts, $ip), 'registration throttle must survive admin writes');
        self::assertSame(5, (int) Db::table('login_attempts')->where('key', hash('sha256', 'member_throttle:register:' . $ip))->value('attempts'), 'registration throttle lives in the atomic login_attempts table, not in the flushable cache');
        self::assertSame('用户名需以字母开头，4 到 20 位字母、数字或下划线', $this->registerError($accounts, '203.0.113.9'), 'other addresses keep their own window');
        Cache::delete($sentinel);
    }

    public function test_config_write_invalidates_only_its_own_syscfg_entries(): void
    {
        $now = date('Y-m-d H:i:s');
        Db::table('config')->insert([
            ['code' => 'scope_alpha', 'group' => 'scopegrp', 'value' => 'a1', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'scope_beta', 'group' => 'scopegrp', 'value' => 'b1', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'scope_other', 'group' => 'scopeoth', 'value' => 'o1', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach ([['scopegrp', 'scope_alpha'], ['scopegrp', 'scope_beta'], ['scopegrp', null], ['scopeoth', 'scope_other'], ['scopegrp', 'scope_missing']] as [$group, $code]) {
            syscfg_forget($group, $code);
            syscfg($group, $code);
        }
        $sentinel = 'cache_scope_sentinel_' . bin2hex(random_bytes(6));
        Cache::set($sentinel, 'kept', 600);

        Config::where('code', 'scope_alpha')->firstOrFail()->forceFill(['value' => 'a2'])->save();
        self::assertSame('a2', syscfg('scopegrp', 'scope_alpha'));
        self::assertSame(['scope_alpha' => 'a2', 'scope_beta' => 'b1'], syscfg('scopegrp'));

        $created = Config::forceCreate(['code' => 'scope_missing', 'group' => 'scopegrp', 'value' => 'm1', 'status' => 1]);
        self::assertSame('m1', syscfg('scopegrp', 'scope_missing'), 'a cached miss must be dropped when the row is created');

        $created->forceFill(['group' => 'scopeoth'])->save();
        self::assertNull(syscfg('scopegrp', 'scope_missing'), 'moving a row out of a group invalidates the old group');
        self::assertSame(['scope_other' => 'o1', 'scope_missing' => 'm1'], syscfg('scopeoth'));

        Config::where('code', 'scope_beta')->firstOrFail()->forceFill(['status' => 0])->save();
        self::assertNull(syscfg('scopegrp', 'scope_beta'));
        $created->forceDelete();
        self::assertSame(['scope_other' => 'o1'], syscfg('scopeoth'));

        self::assertSame('kept', Cache::get($sentinel), 'config writes must not flush unrelated cache entries');
        Cache::delete($sentinel);
        foreach (['scopegrp', 'scopeoth'] as $group) syscfg_forget($group);
    }

    public function test_core_code_never_flushes_the_whole_cache_store(): void
    {
        $offenders = [];
        foreach (['app', 'support', 'plugins'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($directory), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                $path = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if ($file->getExtension() !== 'php' || str_contains($path, '/vendor/')) continue;
                if (preg_match('/\bCache::clear\s*\(|->clearApplicationCache\s*\(/', (string) file_get_contents($file->getPathname())) === 1) $offenders[] = $path;
            }
        }
        self::assertSame([], $offenders, 'use key-scoped invalidation (syscfg_forget, model events) instead of Cache::clear()');
    }

    private function registerError(MemberAccountService $accounts, string $ip): string
    {
        try {
            $accounts->register(['username' => '1-invalid', 'email' => 'x@example.com', 'password' => 'abc12345', 'password_confirm' => 'abc12345'], $ip);
        } catch (\RuntimeException $error) {
            return $error->getMessage();
        }
        self::fail('registration with an invalid username must not succeed');
    }

    private function adminCall(string $class, string $method, string $path, array $data, callable $action): array
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $request = new Request("$method $path HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => (int) config('auth.super_admin_id', 1)];
        Context::set(\Webman\Http\Request::class, $request);
        return json_decode($action(new $class())->rawBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
