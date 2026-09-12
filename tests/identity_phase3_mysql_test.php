<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\model\identity\ClientSecret;
use app\common\model\identity\OAuthClient;
use app\common\model\identity\OidcSigningKey;
use app\common\service\MigrationService;
use app\common\service\identity\ApplicationCatalogService;
use app\common\service\identity\ClientSecretService;
use app\common\service\identity\OAuthClientService;
use app\common\service\identity\SigningKeyService;
use think\App;
use think\facade\Db;

function phase3MysqlExpect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function phase3MysqlQuote(string $identifier): string { phase3MysqlExpect((bool) preg_match('/^[a-z0-9_]+$/', $identifier), '数据库名非法'); return '`' . $identifier . '`'; }
function phase3MigrationDirectoryThrough(string $source, int $lastVersion): string
{
    $target = sys_get_temp_dir() . '/funadmin_phase3_migrations_' . bin2hex(random_bytes(5));
    phase3MysqlExpect(mkdir($target, 0700), '无法创建隔离 migration 目录');
    foreach (glob($source . '/*.sql') ?: [] as $file) {
        if ((int) substr(basename($file), 0, 3) <= $lastVersion) phase3MysqlExpect(copy($file, $target . '/' . basename($file)), '无法复制 migration 链');
    }
    return $target;
}

$root = dirname(__DIR__); $app = new App($root); $app->initialize();
$original = (array) config('database'); $database = 'funadmin_identity_phase3_' . bin2hex(random_bytes(5));
$upgradeDatabase = 'funadmin_identity_phase3_upgrade_' . bin2hex(random_bytes(5));
$upgradeMigrations = phase3MigrationDirectoryThrough($root . '/database/migrations', 96);
$phase3Migrations = phase3MigrationDirectoryThrough($root . '/database/migrations', 98);
$serverConfig = $original; $serverConfig['connections']['mysql']['database'] = ''; $app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true); $keyDirectory = sys_get_temp_dir() . '/funadmin-phase3-keys-' . bin2hex(random_bytes(4));
try {
    $server->execute('CREATE DATABASE ' . phase3MysqlQuote($database) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $server->execute('CREATE DATABASE ' . phase3MysqlQuote($upgradeDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolated = $original; $isolated['connections']['mysql']['database'] = $database; $app->config->set($isolated, 'database'); Db::connect('mysql', true);
    $migration = new MigrationService();
    $executed = $migration->runDirectory($phase3Migrations, 'core');
    phase3MysqlExpect(in_array('097_oauth_client_foundation', $executed, true) && in_array('098_oauth_client_console', $executed, true), '隔离 MySQL 必须执行 097/098');
    phase3MysqlExpect($migration->runDirectory($phase3Migrations, 'core') === [], '097/098 空库重复执行必须幂等跳过');

    $upgrade = $original; $upgrade['connections']['mysql']['database'] = $upgradeDatabase; $app->config->set($upgrade, 'database'); Db::connect('mysql', true);
    $through096 = $migration->runDirectory($upgradeMigrations, 'core');
    phase3MysqlExpect(end($through096) === '096_enterprise_application_center', '升级库必须先完整执行到 096');
    $upgradeExecuted = $migration->runDirectory($phase3Migrations, 'core');
    phase3MysqlExpect($upgradeExecuted === ['097_oauth_client_foundation', '098_oauth_client_console'], '096 升级必须只执行 097/098');
    phase3MysqlExpect($migration->runDirectory($phase3Migrations, 'core') === [], '097/098 升级库重复执行必须幂等跳过');

    $app->config->set($isolated, 'database'); Db::connect('mysql', true);
    Db::execute("INSERT INTO fun_identity_tenant (public_id,code,name,status,created_at,updated_at) VALUES (UUID(),'other-p3','Other P3',1,NOW(),NOW())");
    $tenant2 = (int) Db::query("SELECT id FROM fun_identity_tenant WHERE code='other-p3'")[0]['id'];
    $catalog = new ApplicationCatalogService();
    $application1 = $catalog->save(1, ['code' => 'oauth-p3', 'name' => 'OAuth P3', 'runtimeType' => 'internal', 'launchUrl' => '/oauth-p3']);
    $application2 = $catalog->save($tenant2, ['code' => 'oauth-other', 'name' => 'OAuth Other', 'runtimeType' => 'internal', 'launchUrl' => '/oauth-other']);
    $clients = new OAuthClientService();
    $client1 = $clients->save(1, (int) $application1['id'], ['name' => 'Web', 'clientType' => 'confidential', 'grants' => ['authorization_code'], 'scopes' => ['openid']]);
    $client2 = $clients->save($tenant2, (int) $application2['id'], ['name' => 'Machine', 'clientType' => 'machine', 'grants' => ['client_credentials'], 'scopes' => []]);
    try { $clients->detail(1, (int) $client2['id']); throw new RuntimeException('跨 tenant client 详情必须拒绝'); } catch (DomainException) {}
    try { $clients->disable(1, (int) $client2['id']); throw new RuntimeException('跨 tenant client 禁用必须拒绝'); } catch (DomainException) {}

    $secrets = new ClientSecretService();
    $first = $secrets->rotate(1, (int) $client1['id']); $second = $secrets->rotate(1, (int) $client1['id']); $third = $secrets->rotate(1, (int) $client1['id']);
    phase3MysqlExpect((int) ClientSecret::forTenant(1)->where('client_id', $client1['id'])->whereNull('revoked_at')->count() === 2, '并发安全上限必须最多保留两个 active secret');
    phase3MysqlExpect(!$secrets->verify(1, (int) $client1['id'], $first['secret']) && $secrets->verify(1, (int) $client1['id'], $second['secret']) && $secrets->verify(1, (int) $client1['id'], $third['secret']), '最旧 secret 必须自动吊销且新 secret 可验证');
    try { $secrets->rotate(1, (int) $client2['id']); throw new RuntimeException('跨 tenant secret rotation 必须拒绝'); } catch (DomainException) {}
    phase3MysqlExpect(!$secrets->verify($tenant2, (int) $client1['id'], $second['secret']), 'secret verify 必须拒绝跨 tenant client');

    Db::execute("CREATE TRIGGER fail_public_secret_revoke BEFORE UPDATE ON fun_client_secret FOR EACH ROW BEGIN IF OLD.revoked_at IS NULL AND NEW.revoked_at IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected secret revoke failure'; END IF; END");
    try {
        $clients->save(1, (int) $application1['id'], ['name' => 'Web', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid']], (int) $client1['id']);
        throw new RuntimeException('转 public 吊销 secret 失败时必须抛出');
    } catch (Throwable $exception) {
        phase3MysqlExpect(str_contains($exception->getMessage(), 'injected secret revoke failure'), '转 public 必须执行 active secret 吊销');
    } finally {
        Db::execute('DROP TRIGGER IF EXISTS fail_public_secret_revoke');
    }
    $rolledBackClient = OAuthClient::forTenant(1)->where('id', (int) $client1['id'])->find();
    phase3MysqlExpect($rolledBackClient?->client_type === 'confidential', 'secret 吊销失败必须回滚 client 类型转换');
    phase3MysqlExpect((int) ClientSecret::forTenant(1)->where('client_id', $client1['id'])->whereNull('revoked_at')->count() === 2, 'secret 吊销失败必须保留转换前 active secrets');
    phase3MysqlExpect($secrets->verify(1, (int) $client1['id'], $second['secret']), '事务回滚后原 confidential secret 必须仍有效');

    $clients->save(1, (int) $application1['id'], ['name' => 'Web', 'clientType' => 'public', 'grants' => ['authorization_code'], 'scopes' => ['openid']], (int) $client1['id']);
    phase3MysqlExpect((int) ClientSecret::forTenant(1)->where('client_id', $client1['id'])->whereNull('revoked_at')->count() === 0, '转 public 必须在同一事务吊销全部 active secrets');
    phase3MysqlExpect(!$secrets->verify(1, (int) $client1['id'], $second['secret']) && !$secrets->verify(1, (int) $client1['id'], $third['secret']), 'public client 的历史 secret 必须全部验证失败');
    try { $secrets->rotate(1, (int) $client1['id']); throw new RuntimeException('public client 并发 rotation 必须拒绝'); } catch (DomainException) {}

    $clients->save(1, (int) $application1['id'], ['name' => 'Web', 'clientType' => 'confidential', 'grants' => ['authorization_code'], 'scopes' => ['openid']], (int) $client1['id']);
    phase3MysqlExpect(!$secrets->verify(1, (int) $client1['id'], $second['secret']) && !$secrets->verify(1, (int) $client1['id'], $third['secret']), '转回 confidential 不得复活旧 secret');
    $replacement = $secrets->rotate(1, (int) $client1['id']);
    phase3MysqlExpect($secrets->verify(1, (int) $client1['id'], $replacement['secret']), '转回 confidential 后必须 rotate 新 secret 才可认证');
    OAuthClient::forTenant(1)->where('id', (int) $client1['id'])->update(['status' => 'disabled']);
    phase3MysqlExpect(!$secrets->verify(1, (int) $client1['id'], $replacement['secret']), 'disabled client 即使 secret active 也必须验证失败');

    $keys = new SigningKeyService($keyDirectory); $firstKey = $keys->rotate(1, 3600); $secondKey = $keys->rotate(1, 3600);
    phase3MysqlExpect((int) OidcSigningKey::forTenant(1)->where('status', 'active')->count() === 1, 'rotation 后每租户必须只有一个 active key');
    phase3MysqlExpect((int) OidcSigningKey::forTenant(1)->where('status', 'retiring')->where('publish_until', '>', date('Y-m-d H:i:s'))->count() === 1, '旧 key 必须在 publish 窗口内保持 retiring');
    phase3MysqlExpect(count($keys->listPublishable(1)) === 2, 'JWKS publish 集合必须包含 active 与窗口内旧 key');
    phase3MysqlExpect($keys->listPublishable($tenant2) === [], '签名 key 列表必须 tenant 隔离');
    phase3MysqlExpect($firstKey['kid'] !== $secondKey['kid'], 'rotation 必须生成唯一 kid');
    phase3MysqlExpect((int) Db::query("SELECT COUNT(*) aggregate FROM fun_admin_menu WHERE source_name='oauth_client_console'")[0]['aggregate'] === 1, 'OAuth Client 菜单必须整合且幂等');
    echo "identity phase3 mysql tests passed; temporary database cleaned\n";
} finally {
    foreach (glob($keyDirectory . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($keyDirectory)) rmdir($keyDirectory);
    foreach ([$upgradeMigrations, $phase3Migrations] as $directory) {
        foreach (glob($directory . '/*') ?: [] as $file) if (is_file($file)) unlink($file);
        if (is_dir($directory)) rmdir($directory);
    }
    $app->config->set($serverConfig, 'database'); $cleanup = Db::connect('mysql', true);
    $cleanup->execute('DROP DATABASE IF EXISTS ' . phase3MysqlQuote($database));
    $cleanup->execute('DROP DATABASE IF EXISTS ' . phase3MysqlQuote($upgradeDatabase));
    $app->config->set($original, 'database'); Db::connect('mysql', true);
}
