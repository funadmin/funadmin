<?php

declare(strict_types=1);

require __DIR__ . '/ai_phase2_services_test.php';

use app\console\ai\service\AiConversationService;
use app\console\authorization\service\PermissionResource;

$failures = [];
$check = static function (bool $ok, string $label) use (&$failures): void { if (!$ok) $failures[] = $label; };
$reject = static function (callable $call, int $code, string $label) use ($check): void {
    try { $call(); $check(false, $label); }
    catch (InvalidArgumentException $e) { $check($code === 400, $label); }
    catch (RuntimeException $e) { $check($e->getCode() === $code, $label); }
};
$store = new MemoryAiStore();
$service = new AiConversationService($store);
$group = $service->createConversationGroup(7, ['name' => ' 项目 ']);
$other = $service->createConversationGroup(8, ['name' => '其他']);
$check($group['name'] === '项目' && count($service->listConversationGroups(7)) === 1, '分组列表隔离并 trim');
$reject(fn () => $service->createConversation(7, ['group_id' => $other['id']]), 404, '创建会话禁止跨管理员分组');
foreach ([[], null, true, 1, '', '   ', str_repeat('中', 101)] as $name) {
    $reject(fn () => $service->createConversationGroup(7, ['name' => $name]), 400, '分组名称严格类型/长度：' . get_debug_type($name));
}
$reject(fn () => $service->createConversationGroup(0, ['name' => '无效']), 400, '无效管理员');
$reject(fn () => $service->createConversationGroup(7, ['name' => '有效', 'admin_id' => 8]), 400, '分组拒绝未知字段');
$conversation = $service->createConversation(7, ['title' => '初始', 'group_id' => $group['id']]);
$id = $conversation['id'];
foreach (['1', 1.5, true, 0, -1, [], '1x'] as $groupId) {
    $reject(fn () => $service->updateConversationState($id, 7, ['group_id' => $groupId]), 400, '分组 ID 严格校验：' . get_debug_type($groupId));
    $reject(fn () => $service->createConversation(7, ['group_id' => $groupId]), 400, '创建分组 ID 严格校验');
}
foreach (['false', 'true', 0, 1, null, []] as $flag) {
    foreach (['is_archived', 'is_unread'] as $field) $reject(fn () => $service->updateConversationState($id, 7, [$field => $flag]), 400, '状态必须 JSON boolean');
}
foreach ([[], ['admin_id' => 8], ['is_unread' => false, 'approval_mode' => 'full_access']] as $input) {
    $reject(fn () => $service->updateConversationState($id, 7, $input), 400, '状态拒绝空输入/越权字段');
}
foreach ([[], null, 123, '', ' ', str_repeat('中', 256)] as $title) {
    $reject(fn () => $service->updateConversation($id, 7, ['title' => $title]), 400, '改名严格校验');
}
$reject(fn () => $service->updateConversation($id, 7, ['admin_id' => 8]), 400, '更新拒绝未知字段');
$reject(fn () => $service->updateConversationState($id, 8, ['is_unread' => false]), 404, '会话状态所有权');
$reject(fn () => $service->updateConversationState($id, 7, ['group_id' => $other['id']]), 404, '移动分组所有权');
$reject(fn () => $service->updateConversationGroup($other['id'], 7, ['name' => '窃取']), 404, '改组名所有权');
$reject(fn () => $service->deleteConversationGroup($other['id'], 7), 404, '删组所有权');
$check($service->updateConversation($id, 7, ['title' => ' 新名 '])['title'] === '新名', '改名 trim');
$state = $service->updateConversationState($id, 7, ['group_id' => null, 'is_archived' => true, 'is_unread' => true]);
$check($state['group_id'] === null && $state['is_archived'] === true && $state['is_unread'] === true, '移出分组/归档/未读');
$state = $service->updateConversationState($id, 7, ['group_id' => $group['id'], 'is_archived' => false, 'is_unread' => false]);
$check(!$state['is_archived'] && !$state['is_unread'], '恢复/已读');
$store->failGroupDelete = true;
try { $service->deleteConversationGroup($group['id'], 7); } catch (RuntimeException) {}
$check($store->conversations[$id]['group_id'] === $group['id'] && !$store->conversations[$id]['is_archived'], '删除失败不得提前归档会话');
$store->failGroupDelete = false;
$service->deleteConversationGroup($group['id'], 7);
$check($store->conversations[$id]['group_id'] === null && $store->conversations[$id]['is_archived'], '删组归档并移出');
$check($service->listConversationGroups(7) !== [$group] && $service->listConversationGroups(8) === [$other], '删除隔离');
foreach (['conversationGroupIndex'=>'conversationindex', 'conversationGroupCreate'=>'conversationcreate', 'conversationGroupUpdate'=>'conversationupdate', 'conversationGroupDelete'=>'conversationdelete', 'conversationStateUpdate'=>'conversationupdate'] as $action => $expected) {
    $resource = PermissionResource::fromParts('console', 'ai.Ai', $action);
    $check($resource['code'] === 'console/development.ai:' . $expected, '既有权限映射：' . $action);
}
if ($failures) { foreach ($failures as $failure) fwrite(STDERR, "FAIL: {$failure}\n"); exit(1); }
echo "AI conversation management: PASS\n";
