<?php

declare(strict_types=1);

/**
 * AuthzCacheCrossWorkerTest 的独立 worker 进程：与测试进程共用同一个 SQLite 文件库，各自持有自己的 AuthzCache 静态缓存。
 * 从 stdin 逐行读取命令，每条命令向 stdout 输出一行 JSON。
 *   seed                 迁移并写入夹具（管理员 2 / 角色 5、6，目标权限只授予角色 6）
 *   codes                管理员 2 的权限码是否包含目标权限、当前 worker 缓存条目数
 *   grant                把角色 6 赋给管理员 2（经 CasbinService 写入，由本进程监听器换版本）
 */
$database = $argv[1] ?? '';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/app/functions.php';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $database);
Webman\Config::load(dirname(__DIR__, 2) . '/config', ['route']);
class_exists(support\Db::class);
app\admin\authorization\service\AuthzCache::listen();

use app\admin\authorization\controller\SystemRole;
use app\admin\authorization\service\AuthzCache;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionCode;
use support\Db;

$target = static fn (): string => (string) PermissionCode::derive(SystemRole::class, 'index');
while (($line = fgets(STDIN)) !== false) {
    $command = trim($line);
    if ($command === 'seed') {
        (new app\common\service\database\MigrationService())->run();
        Db::table('admin')->insert(['id' => 2, 'username' => 'op', 'password' => 'x', 'status' => 1, 'dept_id' => 0]);
        Db::table('auth_group')->insert([
            ['id' => 5, 'pid' => 0, 'name' => 'op', 'code' => 'op', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 6, 'pid' => 0, 'name' => 'extra', 'code' => 'extra', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
        ]);
        $targetId = (int) Db::table('permission')->where('code', $target())->value('id');
        CasbinService::instance()->syncRolePermissions(6, [$targetId]);
        CasbinService::instance()->syncAdminRoles(2, [5]);
        echo json_encode(['ok' => true]), "\n";
    } elseif ($command === 'codes') {
        $codes = CasbinService::instance()->permissionCodesForAdmin(2);
        echo json_encode(['target' => in_array($target(), $codes, true), 'size' => AuthzCache::size()]), "\n";
    } elseif ($command === 'grant') {
        CasbinService::instance()->syncAdminRoles(2, [5, 6]);
        echo json_encode(['ok' => true]), "\n";
    } else {
        echo json_encode(['error' => $command]), "\n";
    }
}
