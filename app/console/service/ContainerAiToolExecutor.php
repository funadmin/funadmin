<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\PathGuard;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** 仅在已加固容器内执行注册工具，并完整记录审批与审计。 */
final class ContainerAiToolExecutor implements AiToolExecutor
{
    private const COMMANDS = ['php', 'composer', 'npm', 'npx', 'node', 'git', 'rg', 'ls', 'cat', 'find', 'pwd'];
    private const SENSITIVE_PATHS = '/(^|\/)(?:\.env(?:\.|$)|\.git(?:\/|$)|runtime(?:\/|$)|\.ssh(?:\/|$)|credentials?(?:\/|$)|id_rsa$|id_ed25519$)/i';

    public function __construct(
        private readonly AgentToolRegistry $registry,
        private readonly ApprovalPolicyEngine $policy,
        private readonly AiApprovalService $approvals,
        private readonly AiAuditService $audit,
        private readonly AiSecurityStore $store,
        private readonly AgentSandboxManager $sandbox,
        private readonly array $networkConfig = []
    ) {
    }

    public function execute(array $call): array
    {
        $name = (string) ($call['name'] ?? '');
        $arguments = (array) ($call['arguments'] ?? []);
        $context = (array) ($call['context'] ?? []);
        $definition = $this->registry->validate($name, $arguments);
        $this->validateSecurity($name, $arguments, (string) ($context['workspace'] ?? ''));
        $decision = $this->policy->decide((string) ($context['approval_mode'] ?? ''), $definition['operation']);
        $record = $this->store->createToolCall([
            'conversation_id'=>(int)($context['conversation_id'] ?? 0), 'task_id'=>(int)($context['task_id'] ?? 0),
            'message_id'=>$context['message_id'] ?? null, 'idempotency_key'=>(string)($call['id'] ?? bin2hex(random_bytes(16))),
            'tool_name'=>$name, 'operation'=>$definition['operation'], 'risk_level'=>$definition['risk'], 'status'=>'pending',
            'redacted_arguments'=>$this->audit->redact($arguments), 'approval_decision'=>'not_required', 'side_effects'=>['declared'=>$definition['sideEffects']],
            'created_at'=>date('Y-m-d H:i:s'),
        ]);
        if ($decision === 'deny') {
            $this->store->updateToolCall($record['id'], ['status'=>'denied','approval_decision'=>'denied','completed_at'=>date('Y-m-d H:i:s')]);
            throw new RuntimeException('工具策略永久拒绝该操作', 403);
        }
        if ($decision === 'ask' && !$this->approved($definition['operation'], $context, $call, (int) $record['id'])) {
            $approval = $this->approvals->request([
                'conversation_id'=>(int)$context['conversation_id'], 'task_id'=>(int)$context['task_id'], 'tool_call_id'=>(int)$record['id'],
                'requested_by'=>(int)$context['admin_id'], 'operation'=>$definition['operation'], 'mode_snapshot'=>(string)$context['approval_mode'],
                'arguments'=>$this->audit->redact($arguments), 'risk_reason'=>$definition['risk'], 'impact'=>['sideEffects'=>$definition['sideEffects']],
            ]);
            $this->store->updateToolCall($record['id'], ['status'=>'awaiting_approval','approval_decision'=>'pending','approval_id'=>$approval['id']]);
            return ['status'=>'awaiting_approval','approvalId'=>$approval['id']];
        }
        return $this->run($record, $name, $arguments, $definition, $context);
    }

    private function approved(string $operation, array $context, array $call, int $toolCallId): bool
    {
        if ($this->approvals->hasSessionApproval((int)$context['conversation_id'], $operation, (string)$context['approval_mode'])) return true;
        $approvalId = (int) ($call['approvalId'] ?? 0);
        if ($approvalId <= 0) return false;
        $approval = $this->store->approval($approvalId, (int)$context['admin_id']);
        return $approval !== null && $approval['status'] === 'approved' && $approval['scope'] === 'once'
            && (int)$approval['tool_call_id'] === $toolCallId && $approval['operation'] === $operation
            && $approval['mode_snapshot'] === ($context['approval_mode'] ?? '');
    }

    private function run(array $record, string $name, array $arguments, array $definition, array $context): array
    {
        $argv = $this->command($name, $arguments);
        $started = microtime(true);
        $this->store->updateToolCall($record['id'], ['status'=>'running','approval_decision'=>'approved','started_at'=>date('Y-m-d H:i:s')]);
        try {
            $result = $this->sandbox->exec((string)$context['container_id'], $argv, (int)$definition['timeout']);
            $stdout = $this->audit->capture('stdout', $result->stdout, (int)$context['task_id'], (int)$record['id']);
            $stderr = $this->audit->capture('stderr', $result->stderr, (int)$context['task_id'], (int)$record['id']);
            $status = $result->exitCode === 0 ? 'succeeded' : 'failed';
            $this->store->updateToolCall($record['id'], ['status'=>$status,'exit_code'=>$result->exitCode,'stdout_summary'=>$stdout['summary'],'stdout_hash'=>$stdout['hash'],'stdout_path'=>$stdout['path'],'stderr_summary'=>$stderr['summary'],'stderr_hash'=>$stderr['hash'],'stderr_path'=>$stderr['path'],'duration_ms'=>(int)round((microtime(true)-$started)*1000),'completed_at'=>date('Y-m-d H:i:s')]);
            return ['status'=>$status,'exitCode'=>$result->exitCode,'stdout'=>$stdout['summary'],'stderr'=>$stderr['summary'],'sideEffect'=>$definition['sideEffects']];
        } catch (Throwable $exception) {
            $this->store->updateToolCall($record['id'], ['status'=>'failed','error'=>['message'=>(string)$this->audit->redact($exception->getMessage())],'duration_ms'=>(int)round((microtime(true)-$started)*1000),'completed_at'=>date('Y-m-d H:i:s')]);
            throw $exception;
        }
    }

    private function validateSecurity(string $name, array $arguments, string $workspace): void
    {
        foreach (['path', 'from', 'to'] as $key) {
            if (!isset($arguments[$key])) continue;
            $path = str_replace('\\', '/', (string)$arguments[$key]);
            if (preg_match(self::SENSITIVE_PATHS, $path) === 1) throw new InvalidArgumentException('敏感路径禁止访问');
            PathGuard::resolve($workspace, $path, 'AI 工具路径');
        }
        if (isset($arguments['argv'])) $this->validateArgv((array)$arguments['argv'], $name);
    }

    private function validateArgv(array $argv, string $tool): void
    {
        $command = basename((string)($argv[0] ?? ''));
        if (!in_array($command, self::COMMANDS, true) || in_array($command, ['docker', 'ssh', 'sudo'], true)) throw new InvalidArgumentException('命令不在 argv 白名单');
        foreach ($argv as $index => $arg) {
            if (!is_string($arg) || str_contains($arg, "\0")) throw new InvalidArgumentException('命令 argv 非法');
            if ($index > 0 && in_array($arg, ['-c', '--command', '--exec'], true)) throw new InvalidArgumentException('禁止命令解释器执行字符串');
            if (preg_match('#https?://#i', $arg) === 1) $this->validateUrl($arg);
        }
        if ($tool === 'git_write' && !in_array($argv[1] ?? '', ['add', 'restore', 'checkout'], true)) throw new InvalidArgumentException('git_write 子命令不允许');
    }

    private function validateUrl(string $url): void
    {
        if (!($this->networkConfig['network_enabled'] ?? false)) throw new InvalidArgumentException('容器网络默认禁用');
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $allowlist = (array)($this->networkConfig['network_allowlist'] ?? []);
        if ($host === '' || !in_array($host, $allowlist, true)) throw new InvalidArgumentException('网络目标不在 allowlist');
        foreach (gethostbynamel($host) ?: [$host] as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) throw new InvalidArgumentException('拒绝 SSRF 私网或保留地址');
        }
    }

    private function command(string $name, array $arguments): array
    {
        $path = isset($arguments['path']) ? '/workspace/' . $arguments['path'] : null;
        return match ($name) {
            'read' => ['cat', '--', $path], 'list' => ['find', $path, '-maxdepth', '1', '-print'],
            'search' => ['rg', '--fixed-strings', '--', $arguments['query'], '/workspace/' . ($arguments['path'] ?? '.')],
            'git_status' => ['git', 'status', '--short'], 'git_diff' => array_values(array_filter(['git','diff','--',$path])),
            'write', 'create' => ['php','-r','$p=$argv[1];file_put_contents($p,$argv[2],LOCK_EX);',$path,$arguments['content']],
            'move' => ['mv','--','/workspace/'.$arguments['from'],'/workspace/'.$arguments['to']], 'delete' => ['rm','--',$path],
            'crud_proposal' => ['php','-r','file_put_contents($argv[1],$argv[2],LOCK_EX);',$path,json_encode($arguments['proposal'], JSON_THROW_ON_ERROR)],
            default => $arguments['argv'],
        };
    }
}
