<?php

declare(strict_types=1);
require __DIR__ . '/ai_configuration_profile_test.php';

use app\console\ai\repository\DatabaseAiProfileRepository;
use app\console\ai\service\AiConfigurationProfileService;
use app\console\ai\service\AiProfileSecret;
use Defuse\Crypto\Key;
use think\facade\Db;

$fallbackUpdate = ['name'=>'修改','fallback_models'=>['b','a'],'fallback_enabled'=>true,'max_output_tokens'=>100,'model_capabilities'=>array_map(static fn ($model) => ['model'=>$model,'reasoning_efforts'=>[],'output_token_parameter'=>'max_tokens','context_window'=>8000,'max_output_tokens'=>500], ['unknown','b','a'])];
$fixture = AiConfigurationProfileService::validate($fallbackUpdate + ['provider'=>'custom','protocol'=>'openai-chat','base_url'=>'https://example.com/v1','model'=>'unknown']);
profileExpect($fixture['fallback_models'] === ['b','a'], '隔离库 fallback fixture 必须通过真实能力校验');
profileExpect(class_exists(DatabaseAiProfileRepository::class), '缺少档案持久化仓储');
if (getenv('AI_PROFILE_MYSQL') !== '1') { echo "MySQL SKIP: AI_PROFILE_MYSQL=1 required\n"; return; }
$app = new think\App(dirname(__DIR__));
$app->initialize();
set_exception_handler(static function (Throwable $e): never { fwrite(STDERR, '档案隔离库测试失败：' . get_class($e) . '，行 ' . $e->getLine() . '，调用 ' . implode(' > ', array_map(static fn ($frame) => ($frame['function'] ?? '') . ':' . ($frame['line'] ?? 0), $e->getTrace())) . "\n"); exit(1); });
$original = (array) config('database');
$server = Db::connect('mysql');
$database = 'funadmin_ai_profile_test_' . bin2hex(random_bytes(5));
$server->execute("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $isolated = $original;
    $isolated['connections']['mysql']['database'] = $database;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/archive/117_ai_configuration_profiles.sql');
    $parser = new ReflectionMethod(app\common\service\MigrationService::class, 'statements');
    foreach ([1,2] as $_) foreach ($parser->invoke(new app\common\service\MigrationService(), $sql) as $statement) Db::execute($statement);
    // 权限迁移只复制结构到隔离库，不读取现有权限数据。
    $source = $original['connections']['mysql']['database'];
    profileExpect(preg_match('/^[a-zA-Z0-9_]+$/', $source) === 1, '源库标识符');
    foreach (['permission','casbin_rule'] as $table) $server->execute("CREATE TABLE `{$database}`.`fun_{$table}` LIKE `{$source}`.`fun_{$table}`");
    foreach (['configure','view'] as $capability) Db::name('casbin_rule')->insert(['ptype'=>'p','v0'=>'role:' . $capability,'v1'=>'console','v2'=>'development/ai','v3'=>$capability,'v4'=>'','v5'=>'','rule_hash'=>hash('sha256', $capability)]);
    $permissions = file_get_contents(dirname(__DIR__) . '/database/migrations/archive/118_ai_profile_permissions.sql');
    foreach ([1,2] as $_) foreach ($parser->invoke(new app\common\service\MigrationService(), $permissions) as $statement) Db::execute($statement);
    profileExpect(Db::name('permission')->where('obj', 'console/ai.profiles')->count() === 8, '路由登记幂等');
    profileExpect(Db::name('casbin_rule')->where('v0', 'role:configure')->where('v2', 'console/ai.profiles')->count() === 8, 'configure 迁移授权');
    profileExpect(Db::name('casbin_rule')->where('v0', 'role:view')->where('v2', 'console/ai.profiles')->count() === 0, '不得扩大 view 授权');
    $repo = new DatabaseAiProfileRepository();
    $key = Key::createNewRandomKey()->saveToAsciiSafeString();
    $service = new AiConfigurationProfileService($repo, new AiProfileSecret($key));
    $base = ['name'=>'主档案','provider'=>'custom','protocol'=>'openai-chat','base_url'=>'https://example.com/v1','model'=>'unknown'];
    $a = $service->create(7, $base + ['api_key'=>'test-private-key']);
    $id = $a['id'];
    profileExpect(is_int($id) && $a['has_api_key'] && !isset($a['api_key']) && !isset($a['secret_ciphertext']), '创建不泄露密钥');
    $raw = Db::name('ai_configuration_profile')->where('id', $id)->find();
    profileExpect(!str_contains(json_encode($raw), 'test-private-key'), '数据库不得存明文');
    profileExpect((new AiProfileSecret($key))->open($raw['secret_ciphertext'], 7) === 'test-private-key', '持久化加密可恢复');
    profileExpect(count($service->list(7)) === 1 && $service->list(8) === [], '列表隔离');
    foreach (['read','delete','makeDefault'] as $method) profileReject(fn () => $service->$method(8, $id));
    profileReject(fn () => $service->update(8, $id, ['name'=>'越权']));
    profileReject(fn () => $service->copy(8, $id, '越权'));
    profileReject(fn () => $service->create(0, $base));
    $service->update(7, $id, $fallbackUpdate);
    $read = (new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret($key)))->read(7, $id);
    profileExpect($read['name'] === '修改' && $read['fallback_models'] === ['b','a'] && $read['has_api_key'], '重建服务后持久化和保留密钥');
    $copy = $service->copy(7, $id, '副本');
    profileExpect(!$copy['has_api_key'] && !$copy['is_default'], '复制不带密钥或默认');
    $service->makeDefault(7, $id);
    $service->makeDefault(7, $copy['id']);
    profileExpect($service->default(7)['id'] === $copy['id'] && !$service->read(7, $id)['is_default'], '默认唯一');
    $foreign = $service->create(8, $base);
    $service->makeDefault(8, $foreign['id']);
    profileExpect($service->default(7)['id'] === $copy['id'], '默认隔离');
    $service->delete(7, $copy['id']);
    profileExpect($service->default(7) === null, '删除默认后清空');
    $service->update(7, $id, ['api_key'=>'']);
    profileExpect(!$service->read(7, $id)['has_api_key'], '显式清空密钥');
    $bad = new AiConfigurationProfileService($repo, new AiProfileSecret(''));
    profileReject(fn () => $bad->update(7, $id, ['name'=>'不得写入','api_key'=>'new-key']));
    profileExpect($service->read(7, $id)['name'] === '修改', '加密失败不得部分保存');
    Db::execute("CREATE TRIGGER reject_profile_default BEFORE UPDATE ON fun_ai_configuration_profile FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'");
    $failed = false;
    try { $service->makeDefault(7, $id); } catch (\think\db\exception\PDOException) { $failed = true; }
    profileExpect($failed, '故障注入必须触发数据库异常');
    Db::execute('DROP TRIGGER reject_profile_default');
    profileExpect($service->default(7) === null, '默认事务失败回滚');
    $service->delete(7, $id);
    profileReject(fn () => $service->read(7, $id));
    echo "AI configuration profile MySQL: PASS\n";
} finally {
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
    $server->execute("DROP DATABASE `{$database}`");
}
