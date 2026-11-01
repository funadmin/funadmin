<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

Dotenv\Dotenv::createUnsafeImmutable(base_path())->safeLoad();
Webman\Config::load(config_path(), ['route']);
date_default_timezone_set(config('app.default_timezone', 'Asia/Shanghai'));

try {
    switch ($argv[1] ?? '') {
        case 'migrate':
            $names = (new app\service\MigrationService())->run();
            echo $names ? implode(PHP_EOL, $names) . PHP_EOL : "数据库已是最新版本\n";
            break;
        case 'admin:create':
            $username = $argv[2] ?? '';
            // 从标准输入读取密码，不将密码暴露在命令行参数或进程列表中。
            $password = rtrim(stream_get_contents(STDIN), "\r\n");
            $admin = (new app\service\AuthService())->createAdmin($username, $password);
            if (in_array('--super', $argv, true)) {
                support\Db::table('admins')->where('id', $admin['id'])->update(['is_super_admin' => true]);
            }
            echo '管理员已创建：' . $admin['username'] . PHP_EOL;
            break;
        case 'auth:prune':
            support\Db::table('admin_tokens')->where('expires_at', '<=', date('Y-m-d H:i:s'))->delete();
            support\Db::table('browser_sessions')->where('expires_at', '<=', date('Y-m-d H:i:s'))->delete();
            support\Db::table('login_attempts')->where('window_start', '<=', time() - 900)->delete();
            echo "过期认证数据已清理\n";
            break;
        default:
            echo "用法：php bin/console.php migrate | admin:create <username> [--super] | auth:prune\n";
            exit(1);
    }
} catch (DomainException $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $error) {
    // 不输出数据库连接信息或密码。
    fwrite(STDERR, '操作失败（' . $error::class . "），请检查新项目数据库配置\n");
    exit(1);
}
