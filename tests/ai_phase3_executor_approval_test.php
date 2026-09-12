<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\contract\AiSecurityStore;
use app\console\ai\contract\DockerProcessRunner;
use app\console\ai\infrastructure\ContainerAiToolExecutor;
use app\console\ai\infrastructure\ProcessResult;
use app\console\ai\service\AgentSandboxManager;
use app\console\ai\service\AgentToolRegistry;
use app\console\ai\service\AiApprovalService;
use app\console\ai\service\AiAuditService;
use app\console\ai\service\ApprovalPolicyEngine;

function phase3ExecutorExpect(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }

final class MemorySecurityStore implements AiSecurityStore
{
    public array $approvals = []; public array $calls = []; private int $id = 1;
    public function createApproval(array $data): array { $data['id'] = $this->id++; return $this->approvals[$data['id']] = $data; }
    public function pendingApprovals(int $adminId): array { return array_values(array_filter($this->approvals, fn ($a) => $a['requested_by'] === $adminId && $a['status'] === 'pending')); }
    public function approval(int $id, int $adminId): ?array { $a = $this->approvals[$id] ?? null; return $a && $a['requested_by'] === $adminId ? $a : null; }
    public function casApproval(int $id, int $version, string $status, array $data): bool { if (!isset($this->approvals[$id]) || $this->approvals[$id]['cas_version'] !== $version || $this->approvals[$id]['status'] !== $status) return false; $this->approvals[$id] = array_replace($this->approvals[$id], $data, ['cas_version' => $version + 1]); return true; }
    public function approvedSessionOperation(int $conversationId, int $adminId, string $operation, string $mode): ?array { foreach ($this->approvals as $a) if ($a['conversation_id'] === $conversationId && $a['requested_by'] === $adminId && $a['decided_by'] === $adminId && $a['operation'] === $operation && $a['mode_snapshot'] === $mode && $a['scope'] === 'session_operation' && $a['status'] === 'approved') return $a; return null; }
    public function consumeOnceApproval(int $toolCallId, int $conversationId, int $adminId, string $operation, string $mode): ?array { foreach ($this->approvals as $id => $a) if ($a['tool_call_id'] === $toolCallId && $a['conversation_id'] === $conversationId && $a['requested_by'] === $adminId && $a['decided_by'] === $adminId && $a['operation'] === $operation && $a['mode_snapshot'] === $mode && $a['scope'] === 'once' && $a['status'] === 'approved') { $this->approvals[$id]['status'] = 'consumed'; return $a; } return null; }
    public function toolCall(int $id, int $conversationId, int $taskId): ?array { $call = $this->calls[$id] ?? null; return $call && $call['conversation_id'] === $conversationId && $call['task_id'] === $taskId ? $call : null; }
    public function awaitingToolCall(int $taskId, int $conversationId): ?array { foreach (array_reverse($this->calls, true) as $call) if ($call['task_id'] === $taskId && $call['conversation_id'] === $conversationId && $call['status'] === 'awaiting_approval') return $call; return null; }
    public function createToolCall(array $data): array { foreach ($this->calls as $call) if ($call['conversation_id'] === $data['conversation_id'] && $call['idempotency_key'] === $data['idempotency_key']) return $call; $data['id'] = $this->id++; return $this->calls[$data['id']] = $data; }
    public function updateToolCall(int $id, array $data): void { $this->calls[$id] = array_replace($this->calls[$id], $data); }
    public function toolCalls(int $taskId): array { return array_values(array_filter($this->calls, fn ($c) => $c['task_id'] === $taskId)); }
}
final class ExecutorDockerRunner implements DockerProcessRunner { public array $calls = []; public function run(array $argv, int $timeoutSeconds): ProcessResult { $this->calls[] = $argv; if (($argv[1] ?? '') === 'version') return new ProcessResult(0, '29', ''); if (($argv[1] ?? '') === 'create') return new ProcessResult(0, 'cid', ''); return new ProcessResult(0, "token=secret-value\n" . str_repeat('x', 200), ''); } }

$clock = 1000; $store = new MemorySecurityStore(); $audit = new AiAuditService(sys_get_temp_dir() . '/ai-p3-log-' . bin2hex(random_bytes(3)), 64);
$approvals = new AiApprovalService($store, function () use (&$clock): int { return $clock; });
$pending = $approvals->request(['conversation_id' => 1, 'task_id' => 2, 'tool_call_id' => 3, 'requested_by' => 7, 'operation' => 'write', 'mode_snapshot' => 'request_approval', 'arguments' => ['path' => 'a.php']], 30);
phase3ExecutorExpect(count($approvals->pending(7)) === 1, '必须查询待审批');
$approved = $approvals->decide($pending['id'], 7, 'approve', 'once', $pending['nonce'], $pending['digest'], 0, 'request_approval');
phase3ExecutorExpect($approved['status'] === 'approved', 'once 审批必须成功');
foreach ([['replay', fn () => $approvals->decide($pending['id'], 7, 'approve', 'once', $pending['nonce'], $pending['digest'], 0, 'request_approval')], ['mode', function () use ($approvals, $store) { $p = $approvals->request(['conversation_id'=>1,'task_id'=>2,'tool_call_id'=>4,'requested_by'=>7,'operation'=>'delete','mode_snapshot'=>'request_approval','arguments'=>[]],30); return $approvals->decide($p['id'],7,'approve','once',$p['nonce'],$p['digest'],0,'full_access'); }]] as [$label, $action]) { $denied=false; try { $action(); } catch (RuntimeException) { $denied=true; } phase3ExecutorExpect($denied, "审批 {$label} 必须拒绝"); }
$expired = $approvals->request(['conversation_id'=>1,'task_id'=>2,'tool_call_id'=>5,'requested_by'=>7,'operation'=>'shell','mode_snapshot'=>'request_approval','arguments'=>[]],1); $clock=1002; $denied=false; try { $approvals->decide($expired['id'],7,'approve','once',$expired['nonce'],$expired['digest'],0,'request_approval'); } catch (RuntimeException) { $denied=true; } phase3ExecutorExpect($denied && $store->approvals[$expired['id']]['status']==='expired', '过期审批必须 CAS 标记 expired');
$reject = $approvals->request(['conversation_id'=>1,'task_id'=>2,'tool_call_id'=>6,'requested_by'=>7,'operation'=>'write','mode_snapshot'=>'request_approval','arguments'=>[]],30); phase3ExecutorExpect($approvals->decide($reject['id'],7,'reject','once',$reject['nonce'],$reject['digest'],0,'request_approval')['status']==='denied','reject 必须生效');

$runner = new ExecutorDockerRunner(); $sandbox = new AgentSandboxManager($runner, dirname(__DIR__), sys_get_temp_dir() . '/ai-p3-sbox-' . bin2hex(random_bytes(3)), ['image'=>'x@sha256:'.str_repeat('a',64),'cpu'=>.5,'memory_mb'=>128,'pids'=>32,'network_enabled'=>false]);
$executor = new ContainerAiToolExecutor(new AgentToolRegistry(['read','write','shell','test','move','delete']), new ApprovalPolicyEngine(), $approvals, $audit, $store, $sandbox, ['network_allowlist'=>[]]);
$context = ['conversation_id'=>9,'task_id'=>10,'admin_id'=>7,'approval_mode'=>'request_approval','container_id'=>'cid','workspace'=>dirname(__DIR__)];
$result = $executor->execute(['id'=>'ask1','name'=>'write','arguments'=>['path'=>'tmp/a.php','content'=>'x'],'context'=>$context]);
phase3ExecutorExpect($result['status']==='awaiting_approval' && count($runner->calls)===0, 'ask 必须持久化后返回且绝不执行');
$askApproval = $store->approvals[$result['approvalId']];
$approvedAsk = $approvals->decide($askApproval['id'],7,'approve','once',$askApproval['nonce'],$askApproval['digest'],0,'request_approval');
phase3ExecutorExpect($approvedAsk['status']==='approved', 'ask 后必须可批准');
$resumed = $executor->execute(['id'=>'ask1','name'=>'ignored-by-db','arguments'=>['path'=>'evil.php','content'=>'evil'],'context'=>$context]);
phase3ExecutorExpect($resumed['status']==='succeeded', '恢复必须从数据库可信工具调用参数执行，不依赖模型 approvalId');
$replayed = false; try { $executor->execute(['id'=>'ask1','name'=>'write','arguments'=>['path'=>'tmp/a.php','content'=>'x'],'context'=>$context]); } catch (RuntimeException) { $replayed = true; }
phase3ExecutorExpect($replayed && $store->approvals[$askApproval['id']]['status']==='consumed', 'once 审批必须原子消费且不可重放');
$session = $approvals->request(['conversation_id'=>9,'task_id'=>10,'tool_call_id'=>99,'requested_by'=>7,'operation'=>'write','mode_snapshot'=>'request_approval','arguments'=>[]],30);
$approvals->decide($session['id'],7,'approve','session_operation',$session['nonce'],$session['digest'],0,'request_approval');
phase3ExecutorExpect($approvals->hasSessionApproval(9,7,'write','request_approval'), '同管理员同会话 scoped grant 必须生效');
phase3ExecutorExpect(!$approvals->hasSessionApproval(9,8,'write','request_approval'), 'session_operation 不得跨管理员');
foreach ([['shell',['argv'=>['sh','-c','id; touch /tmp/pwn']]],['read',['path'=>'../.env']],['read',['path'=>'.env']],['shell',['argv'=>['curl','http://127.0.0.1/admin']]],['shell',['argv'=>['docker','ps']]],['shell',['argv'=>['rm','-rf','/workspace']]],['shell',['argv'=>['npm','install','https://registry.npmjs.org/x']]]] as [$name,$args]) { $denied=false; try { $executor->execute(['id'=>bin2hex(random_bytes(2)),'name'=>$name,'arguments'=>$args,'context'=>array_replace($context,['approval_mode'=>'full_access'])]); } catch (Throwable) { $denied=true; } phase3ExecutorExpect($denied, "危险调用 {$name} 必须拒绝"); }
$move = $executor->execute(['id'=>'move1','name'=>'move','arguments'=>['from'=>'a.php','to'=>'b.php'],'context'=>array_replace($context,['approval_mode'=>'full_access'])]);
$delete = $executor->execute(['id'=>'delete1','name'=>'delete','arguments'=>['path'=>'b.php'],'context'=>array_replace($context,['approval_mode'=>'full_access'])]);
$executedArgv = array_column($runner->calls, null);
phase3ExecutorExpect(!str_contains(json_encode($executedArgv), '"rm"') && !str_contains(json_encode($executedArgv), '"mv"'), '内部 move/delete 必须使用统一受控 PHP argv policy，禁止 rm/mv');
$ok = $executor->execute(['id'=>'ok1','name'=>'test','arguments'=>['argv'=>['php','-v']],'context'=>array_replace($context,['approval_mode'=>'agent_approval'])]);
phase3ExecutorExpect($ok['status']==='succeeded', 'allow 必须仅在容器执行');
$call = end($store->calls); phase3ExecutorExpect($call['stdout_hash']===hash('sha256', "token=secret-value\n" . str_repeat('x',200)), '必须记录完整 stdout hash');
phase3ExecutorExpect(!str_contains(json_encode($call), 'secret-value') && strlen($call['stdout_summary']) <= 64 && is_file($call['stdout_path']), '日志必须脱敏、截断并保存私有大日志');

echo "AI phase 3 executor and approval tests: PASS\n";
