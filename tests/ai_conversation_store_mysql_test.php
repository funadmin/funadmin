<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use app\console\ai\repository\DatabaseAiConversationStore;
use app\console\ai\service\AiConversationService;
use think\facade\Db;

if (getenv('AI_CONVERSATION_MYSQL') !== '1') { echo "AI conversation MySQL: SKIP (AI_CONVERSATION_MYSQL=1 required)\n"; return; }
$app = new think\App(dirname(__DIR__));
$app->initialize();
set_exception_handler(static function (Throwable $e): never { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); });
$original = (array) config('database');
$config = $original['connections']['mysql'];
$source = $config['database'];
$database = 'funadmin_ai_conversation_test_' . bin2hex(random_bytes(5));
$server = Db::connect('mysql');
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$check(preg_match('/^[a-zA-Z0-9_]+$/', $source) === 1, '源数据库名称非法');
$server->execute("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    // 仅复制表结构，不读取或复制用户数据；DDL 和测试写入全部在随机隔离库。
    foreach (['ai_conversation', 'ai_message', 'ai_task', 'ai_task_event', 'ai_stream_nonce'] as $table) {
        $server->execute("CREATE TABLE `{$database}`.`fun_{$table}` LIKE `{$source}`.`fun_{$table}`");
    }
    $config['database'] = $database;
    $isolated = $original;
    $isolated['connections']['mysql'] = $config;
    $app->config->set($isolated, 'database');
    Db::connect('mysql', true);
    $parser = new ReflectionMethod(MigrationService::class, 'statements');
    $migrations = new MigrationService();
    $execute = static function (string $file) use ($parser, $migrations): void {
        foreach ($parser->invoke($migrations, file_get_contents(dirname(__DIR__) . '/database/migrations/' . $file)) as $sql) Db::execute($sql);
    };
    $execute('115_ai_conversation_groups.sql');
    $execute('115_ai_conversation_groups.sql');
    $forward = dirname(__DIR__) . '/database/migrations/116_ai_conversation_group_active_name.sql';
    if (is_file($forward)) {
        $execute(basename($forward));
        $execute(basename($forward));
    }
    $store = new DatabaseAiConversationStore();
    $service = new AiConversationService($store);
    $group = $service->createConversationGroup(7, ['name' => '项目']);
    $conversation = $service->createConversation(7, ['title' => '测试', 'group_id' => (int) $group['id']]);
    $id = (int) $conversation['id'];
    $failures = [];
    $expect = static function (bool $ok, string $message) use (&$failures): void { if (!$ok) $failures[] = $message; };
    $expect($store->conversation($id, 7)['is_unread'] === false, '返回 boolean 而非 tinyint');
    foreach (['user', 'system', 'tool', 'assistant'] as $role) {
        $service->updateConversationState($id, 7, ['is_unread' => false]);
        $store->appendMessage($id, ['role' => $role, 'content' => ['text' => '回复']]);
        $expect((bool) $store->conversation($id, 7)['is_unread'] === ($role === 'assistant'), '消息角色未读：' . $role);
    }
    foreach (['compareAndSetTask', 'compareAndSetTaskOperation', 'updateTask'] as $method) {
        foreach (['succeeded', 'failed', 'cancelled'] as $status) {
            $task = $service->createTask($id, 7, ['type' => 'chat', 'idempotency_key' => $method . $status]);
            $service->updateConversationState($id, 7, ['is_unread' => false]);
            $data = ['status' => $status, 'completed_at' => date('Y-m-d H:i:s')];
            if ($method === 'compareAndSetTask') $store->$method((int) $task['id'], ['pending'], $data);
            elseif ($method === 'compareAndSetTaskOperation') $store->$method((int) $task['id'], $task['operation_token'], ['pending'], $data);
            else $store->$method((int) $task['id'], $data);
            $expect((bool) $store->conversation($id, 7)['is_unread'], '终态持久化未读：' . $method . '/' . $status);
            $service->updateConversationState($id, 7, ['is_unread' => false]);
            $store->compareAndSetTask((int) $task['id'], ['pending'], $data);
            $expect(!(bool) $store->conversation($id, 7)['is_unread'], '失败 CAS 不重标未读');
            $store->updateTask((int) $task['id'], ['heartbeat_at' => date('Y-m-d H:i:s')]);
            $expect(!(bool) $store->conversation($id, 7)['is_unread'], '终态后维护字段不重标');
        }
    }
    Db::execute("CREATE TRIGGER reject_group_delete BEFORE UPDATE ON fun_ai_conversation_group FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='模拟删除失败'");
    try { $service->deleteConversationGroup((int) $group['id'], 7); } catch (Throwable) {}
    $expect((int) $store->conversation($id, 7)['group_id'] === (int) $group['id'] && !(bool) $store->conversation($id, 7)['is_archived'], '数据库删除失败原子回滚归档');
    Db::execute('DROP TRIGGER reject_group_delete');
    $service->deleteConversationGroup((int) $group['id'], 7);
    $expect($store->conversation($id, 7)['group_id'] === null && (bool) $store->conversation($id, 7)['is_archived'], '数据库删组归档');
    try { $store->updateConversation($id, 7, ['group_id' => (int) $group['id']]); $expect(false, '存储拒绝已删除分组'); } catch (RuntimeException) {}
    try {
        $recreated = $service->createConversationGroup(7, ['name' => '项目']);
        $expect((int) $recreated['id'] !== (int) $group['id'], '删除后可同名新建且保留历史');
        $service->deleteConversationGroup((int) $recreated['id'], 7);
        $service->createConversationGroup(7, ['name' => '项目']);
    } catch (Throwable) { $expect(false, '同名分组可反复删除重建'); }
    try { $service->createConversationGroup(7, ['name' => '项目']); $expect(false, '活动分组同名必须冲突'); }
    catch (Throwable $e) { $expect($e->getCode() === 409, '同名冲突返回 409'); }
    $expect((int) Db::name('ai_conversation_group')->whereNotNull('deleted_at')->count() === 2, '历史软删除分组必须保留');
    $another = $service->createConversationGroup(7, ['name' => '另一组']);
    try { $service->updateConversationGroup((int) $another['id'], 7, ['name' => '项目']); $expect(false, '改名冲突'); }
    catch (Throwable $e) { $expect($e->getCode() === 409, '改名冲突返回 409'); }
    $foreign = $service->createConversationGroup(8, ['name' => '项目']);
    try { $store->createConversation(['admin_id' => 7, 'group_id' => (int) $foreign['id']]); $expect(false, '存储创建不得跨所有者'); }
    catch (RuntimeException $e) { $expect($e->getCode() === 404, '存储创建所有权'); }
    $service->updateConversationState($id, 7, ['is_unread' => false]);
    $task = $service->createTask($id, 7, ['type' => 'chat', 'idempotency_key' => 'rollback-unread']);
    Db::execute("CREATE TRIGGER reject_unread BEFORE UPDATE ON fun_ai_conversation FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='模拟未读写入失败'");
    $count = count($store->messages($id));
    try { $store->appendMessage($id, ['role' => 'assistant', 'content' => ['text' => '回滚']]); } catch (Throwable) {}
    $expect(count($store->messages($id)) === $count, '未读失败必须回滚消息');
    try { $store->compareAndSetTask((int) $task['id'], ['pending'], ['status' => 'succeeded']); } catch (Throwable) {}
    $expect($store->task((int) $task['id'])['status'] === 'pending', '未读失败必须回滚任务终态');
    Db::execute('DROP TRIGGER reject_unread');
    $before = Db::name('ai_conversation_group')->order('id')->select()->toArray();
    $execute('116_ai_conversation_group_active_name.sql');
    $expect(Db::name('ai_conversation_group')->order('id')->select()->toArray() === $before, '新迁移带历史数据重入不得修改用户记录');
    if ($failures) throw new RuntimeException(implode("\n", $failures));
    echo "AI conversation MySQL: PASS (115 重入、未读写入、CAS、删除事务)\n";
} finally {
    $app->config->set($original, 'database');
    Db::connect('mysql', true);
    $server->execute("DROP DATABASE `{$database}`");
}
