<?php

declare(strict_types=1);

require __DIR__ . '/ai_phase2_services_test.php';

use app\console\ai\service\AiConversationService;

$store = new MemoryAiStore();
$service = new AiConversationService($store);
$group = $service->createConversationGroup(7, ['name'=>'分页组']);
for ($i = 1; $i <= 125; $i++) {
    $c = $service->createConversation(7, ['title'=>'历史 ' . $i, 'group_id'=>$i % 2 ? $group['id'] : null]);
    $service->updateConversationState($c['id'], 7, ['is_archived'=>$i % 3 === 0, 'is_unread'=>$i % 2 === 1]);
}
$service->createConversation(8, ['title'=>'其他管理员']);
phase2Expect(method_exists($service, 'conversationPage'), 'RED: 缺少独立 UI 会话分页入口');
$page = $service->conversationPage(7, []);
phase2Expect(count($page['items']) === 30 && $page['has_more'] && $page['next_cursor'] === (string) end($page['items'])['id'], '默认 30 与稳定游标');
$ids = array_column($page['items'], 'id');
while ($page['has_more']) {
    $page = $service->conversationPage(7, ['cursor'=>$page['next_cursor']]);
    $ids = array_merge($ids, array_column($page['items'], 'id'));
}
phase2Expect(count($ids) === 125 && count(array_unique($ids)) === 125 && $page['next_cursor'] === null, '所有会话分页不漏不重');
$filtered = $service->conversationPage(7, ['is_archived'=>'0', 'is_unread'=>'1', 'group_id'=>(string)$group['id'], 'search'=>'历史 1', 'limit'=>'100']);
phase2Expect(count($filtered['items']) > 0, '组合筛选存在结果');
foreach ($filtered['items'] as $row) phase2Expect(!$row['is_archived'] && $row['is_unread'] && $row['group_id'] === $group['id'] && str_contains($row['title'], '历史 1'), '组合筛选在分页前执行');
$c = $service->createConversation(7, ['title'=>'消息']);
for ($i = 1; $i <= 155; $i++) $last = $service->appendUserMessage($c['id'], 7, ['content'=>[['type'=>'text','text'=>(string)$i]], 'idempotency_key'=>'m'.$i]);
$page = $service->messagePage($c['id'], 7, []);
phase2Expect(array_column($page['items'], 'sequence') === range(106,155) && $page['has_more'], '最新 50 消息 UI 升序');
$older = $service->messagePage($c['id'], 7, ['before'=>$page['next_cursor']]);
phase2Expect(array_column($older['items'], 'sequence') === range(56,105), '向前不漏不重');
$after = $service->messagePage($c['id'], 7, ['after'=>'1:'.($last['id'] - 154), 'limit'=>100]);
phase2Expect(array_column($after['items'], 'sequence') === range(2,101) && $after['has_more'], '增量必须从最旧新增开始而非取最新');
$tail = $service->messagePage($c['id'], 7, ['after'=>$after['next_cursor'], 'limit'=>100]);
phase2Expect(array_column($tail['items'], 'sequence') === range(102,155) && !$tail['has_more'], '超过一页连续补齐');
$task = $service->createPublicTask($c['id'], 7, ['message_id'=>$last['id'], 'idempotency_key'=>'full-history']);
phase2Expect(count($task['input']['messages']) === 155, '内部冻结任务必须保留全部历史');
$invalid = [ ['limit'=>0], ['limit'=>101], ['limit'=>true], ['limit'=>'01'], ['limit'=>'1.0'], ['limit'=>[]], ['cursor'=>'-1'], ['cursor'=>null], ['unknown'=>1], ['search'=>[]], ['is_unread'=>'true'], ['group_id'=>'-1'] ];
foreach ($invalid as $query) {
    try { $service->conversationPage(7, $query); throw new RuntimeException('未拒绝参数 '.json_encode($query)); } catch (InvalidArgumentException) {}
}
foreach ([['before'=>'1:1','after'=>'2:2'], ['before'=>'x'], ['after'=>[]], ['limit'=>101]] as $query) {
    try { $service->messagePage($c['id'], 7, $query); throw new RuntimeException('未拒绝消息参数'); } catch (InvalidArgumentException) {}
}
try { $service->messagePage($c['id'], 8, []); throw new RuntimeException('越权'); } catch (RuntimeException $e) { phase2Expect($e->getCode() === 404, '每页校验会话归属'); }
$origin = $service->messagePage($c['id'], 7, ['after'=>'0:0']);
phase2Expect(array_column($origin['items'], 'sequence') === range(1,50), '空首屏增量起点必须读取最早新增');
$controller = file_get_contents(dirname(__DIR__).'/app/console/controller/ai/Ai.php');
phase2Expect(str_contains($controller, '$this->ai->conversationPage($this->adminId(), $this->request->get())'), 'Controller 必须转发原始会话查询参数');
phase2Expect(str_contains($controller, '$this->ai->messagePage($id, $this->adminId(), $this->request->get())'), 'Controller 必须转发原始消息查询参数');
echo "AI history pagination tests passed\n";
