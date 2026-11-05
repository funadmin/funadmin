<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

Dotenv\Dotenv::createUnsafeImmutable(base_path())->safeLoad();
Webman\Config::load(config_path(), ['route']);
date_default_timezone_set(config('app.default_timezone', 'Asia/Shanghai'));

try {
    switch ($argv[1] ?? '') {
        case 'install':
            // JSON 从标准输入读取，避免凭据出现在进程列表和 shell 历史中。
            $input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($input) || !is_array($input['db'] ?? null) || !is_array($input['admin'] ?? null)) {
                throw new DomainException('安装输入必须包含 db 和 admin 对象', 422);
            }
            $result = app\common\service\install\InstallSupport::install($input['db'], $input['admin'], (bool) ($input['debug'] ?? false), base_path());
            echo '安装成功：' . $result['username'] . PHP_EOL;
            break;
        case 'migrate':
            $migration = new app\common\service\database\MigrationService();
            $names = support\Db::connection()->getDriverName() === 'mysql'
                ? $migration->runDirectory($migration::coreDirectory()) : $migration->run();
            echo $names ? implode(PHP_EOL, $names) . PHP_EOL : "数据库已是最新版本\n";
            break;
        case 'admin:create':
            $username = $argv[2] ?? '';
            // 从标准输入读取密码，不将密码暴露在命令行参数或进程列表中。
            $password = rtrim(stream_get_contents(STDIN), "\r\n");
            if (in_array('--super', $argv, true)) {
                throw new DomainException('超级管理员只能通过首次 install 初始化，不能通过此命令提权', 422);
            }
            $admin = (new app\admin\authentication\service\AdminSessionService())->createAdmin($username, $password);
            echo '管理员已创建：' . $admin['username'] . PHP_EOL;
            break;
        case 'auth:prune':
            support\Db::table('admin_auth_tokens')->where('expires_at', '<=', date('Y-m-d H:i:s'))->delete();
            support\Db::table('browser_sessions')->where('expires_at', '<=', date('Y-m-d H:i:s'))->delete();
            support\Db::table('login_attempts')->where('window_start', '<=', time() - 900)->delete();
            echo "过期认证数据已清理\n";
            break;
        default:
            echo "用法：php bin/console.php install < install.json | migrate | admin:create <username> | auth:prune\n";
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
