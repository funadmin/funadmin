<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\contract\AiConversationStore;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** 管理员隔离的 AI 会话、消息及任务用例。 */
final class AiConversationService
{
    public function __construct(
        private readonly AiConversationStore $store,
        private readonly array $limits = [],
        private readonly ?\Closure $audit = null,
        private readonly ?AiConfigurationProfileService $profiles = null,
        private readonly ?AiAttachmentService $attachments = null
    ) {
    }

    public function createConversation(int $adminId, array $input, bool $fullAccessAuthorized = false, bool $approveAuthorized = false): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        $this->validateFields($input, ['title', 'approval_mode', 'provider', 'model', 'context', 'group_id', 'profile_id', 'reasoning_effort'], false);
        $groupId = $this->validateGroupId($input['group_id'] ?? null, $adminId);
        $title = array_key_exists('title', $input) ? $this->validateName($input['title'], 255) : '';
        foreach (['approval_mode', 'provider', 'model'] as $field) {
            if (array_key_exists($field, $input) && (!is_string($input[$field]) || mb_strlen($input[$field]) > 100)) throw new InvalidArgumentException($field . ' 必须为不超过 100 字符的字符串');
        }
        if (array_key_exists('context', $input) && !is_array($input['context'])) throw new InvalidArgumentException('context 必须为对象或数组');
        $selection = $this->profileSelection($input, $adminId);
        $approvalMode = $input['approval_mode'] ?? 'request_approval';
        $this->assertModeAuthorized($approvalMode, $fullAccessAuthorized, $approveAuthorized);
        return $this->store->createConversation([
            'admin_id' => $adminId,
            'uuid' => Uuid::uuid4()->toString(),
            'title' => $title,
            'status' => 'draft',
            'approval_mode' => $approvalMode,
            'provider' => $selection['provider'],
            'model' => $selection['model'],
            'profile_id' => $selection['profile_id'] ?? null,
            'reasoning_effort' => $input['reasoning_effort'] ?? null,
            'context' => (array) ($input['context'] ?? []),
            'group_id' => $groupId,
            'is_archived' => false,
            'is_unread' => false,
        ]);
    }

    public function listConversations(int $adminId): array
    {
        return $this->store->conversations($adminId);
    }

    /** UI 专用有界读取，不参与内部任务历史冻结。 */
    public function conversationPage(int $adminId, array $query): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        $this->validateFields($query, ['limit', 'cursor', 'is_archived', 'is_unread', 'group_id', 'search'], false);
        $limit = array_key_exists('limit', $query) ? $this->pageInteger($query['limit'], 1, 100) : 30;
        $filters = [];
        foreach (['is_archived', 'is_unread'] as $field) {
            if (!array_key_exists($field, $query)) continue;
            if (!in_array($query[$field], [0, 1, '0', '1'], true)) throw new InvalidArgumentException($field . ' 必须为 0 或 1');
            $filters[$field] = (int) $query[$field];
        }
        if (array_key_exists('group_id', $query)) {
            $filters['group_id'] = $this->pageInteger($query['group_id'], 0);
            if ($filters['group_id'] > 0) $this->ownedGroup($filters['group_id'], $adminId);
        }
        if (array_key_exists('search', $query)) {
            if (!is_string($query['search']) || !mb_check_encoding($query['search'], 'UTF-8') || mb_strlen($query['search']) > 255 || preg_match('/[\x00-\x1f\x7f]/u', $query['search'])) throw new InvalidArgumentException('搜索文本无效');
            $filters['search'] = trim($query['search']);
        }
        $cursor = array_key_exists('cursor', $query) ? $this->pageInteger($query['cursor'], 1) : null;
        $rows = $this->store->conversationPageRows($adminId, $filters, $cursor, $limit + 1);
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        return ['items'=>$rows, 'has_more'=>$more, 'next_cursor'=>$more ? (string) end($rows)['id'] : null];
    }

    /** sequence/id 复合游标；after 必须从最早新增开始，避免跨页跳消息。 */
    public function messagePage(int $conversationId, int $adminId, array $query): array
    {
        $this->ownedConversation($conversationId, $adminId);
        $this->validateFields($query, ['limit', 'before', 'after'], false);
        if (array_key_exists('before', $query) && array_key_exists('after', $query)) throw new InvalidArgumentException('before 与 after 互斥');
        $limit = array_key_exists('limit', $query) ? $this->pageInteger($query['limit'], 1, 100) : 50;
        $forward = array_key_exists('after', $query);
        $cursor = null;
        if ($forward || array_key_exists('before', $query)) {
            $value = $query[$forward ? 'after' : 'before'];
            if ($forward && $value === '0:0') $cursor = [0, 0];
            else {
                if (!is_string($value) || preg_match('/^([1-9][0-9]*):([1-9][0-9]*)$/D', $value, $parts) !== 1) throw new InvalidArgumentException('消息游标无效');
                $cursor = [$this->pageInteger($parts[1], 1), $this->pageInteger($parts[2], 1)];
            }
        }
        $rows = $this->store->messagePageRows($conversationId, $cursor, $forward, $limit + 1);
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        $edge = $rows ? end($rows) : null;
        if (!$forward) $rows = array_reverse($rows);
        return ['items'=>AiAuditService::publicValue($rows), 'has_more'=>$more, 'next_cursor'=>$more && $edge ? $edge['sequence'] . ':' . $edge['id'] : null];
    }

    private function pageInteger(mixed $value, int $minimum, int $maximum = PHP_INT_MAX): int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value) !== 1) throw new InvalidArgumentException('分页参数必须为无歧义整数');
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>$minimum, 'max_range'=>$maximum]]);
        if ($number === false) throw new InvalidArgumentException('分页参数超出范围');
        return $number;
    }

    public function listConversationGroups(int $adminId): array
    {
        return $this->store->conversationGroups($adminId);
    }

    public function createConversationGroup(int $adminId, array $input): array
    {
        if ($adminId <= 0) throw new InvalidArgumentException('管理员无效');
        $this->validateFields($input, ['name']);
        $name = $this->validateName($input['name'] ?? null, 100);
        return $this->store->createConversationGroup(['admin_id' => $adminId, 'name' => $name]);
    }

    public function updateConversationGroup(int $id, int $adminId, array $input): array
    {
        $this->ownedGroup($id, $adminId);
        $this->validateFields($input, ['name']);
        $name = $this->validateName($input['name'] ?? null, 100);
        $this->store->updateConversationGroup($id, $adminId, ['name' => $name]);
        return $this->ownedGroup($id, $adminId);
    }

    public function deleteConversationGroup(int $id, int $adminId): bool
    {
        $this->ownedGroup($id, $adminId);
        return $this->store->deleteConversationGroup($id, $adminId);
    }

    public function updateConversationState(int $id, int $adminId, array $input): array
    {
        $this->ownedConversation($id, $adminId);
        $this->validateFields($input, ['group_id', 'is_archived', 'is_unread']);
        $allowed = $input;
        if (array_key_exists('group_id', $allowed)) $allowed['group_id'] = $this->validateGroupId($allowed['group_id'], $adminId);
        foreach (['is_archived', 'is_unread'] as $field) {
            if (array_key_exists($field, $allowed) && !is_bool($allowed[$field])) throw new InvalidArgumentException($field . ' 必须为 boolean');
        }
        $this->store->updateConversation($id, $adminId, $allowed);
        return $this->ownedConversation($id, $adminId);
    }

    public function getConversation(int $id, int $adminId): array
    {
        return $this->ownedConversation($id, $adminId);
    }

    public function updateConversation(int $id, int $adminId, array $input, bool $fullAccessAuthorized = false, bool $approveAuthorized = false): array
    {
        $conversation = $this->ownedConversation($id, $adminId);
        $this->validateFields($input, ['title', 'context', 'approval_mode', 'provider', 'model', 'profile_id', 'reasoning_effort']);
        $allowed = $input;
        if (array_key_exists('provider', $input) || array_key_exists('model', $input) || array_key_exists('profile_id', $input) || array_key_exists('reasoning_effort', $input)) {
            if (array_key_exists('model', $input)) $input['model'] = $this->validateName($input['model'], 100);
            $selection = $this->profileSelection(array_replace($conversation, $input), $adminId);
            unset($selection['snapshot']);
            $allowed = array_replace($allowed, $selection);
        }
        if (array_key_exists('title', $allowed)) $allowed['title'] = $this->validateName($allowed['title'], 255);
        if (array_key_exists('context', $allowed) && !is_array($allowed['context'])) throw new InvalidArgumentException('context 必须为对象或数组');
        if (array_key_exists('approval_mode', $allowed) && !is_string($allowed['approval_mode'])) throw new InvalidArgumentException('审批模式必须为字符串');
        if (array_key_exists('approval_mode', $allowed)) {
            $this->assertModeAuthorized((string) $allowed['approval_mode'], $fullAccessAuthorized, $approveAuthorized);
            if ($this->modeLevel((string) $allowed['approval_mode']) > $this->modeLevel((string) $conversation['approval_mode'])) {
                $this->assertModeAuthorized((string) $allowed['approval_mode'], $fullAccessAuthorized, $approveAuthorized);
            }
        }
        $this->store->updateConversation($id, $adminId, $allowed);
        return $this->ownedConversation($id, $adminId);
    }

    public function deleteConversation(int $id, int $adminId): bool
    {
        $this->ownedConversation($id, $adminId);
        return $this->store->deleteConversation($id, $adminId);
    }

    /** 公开写入只允许用户数据，内部 assistant/tool 写入仍经可信存储端口。 */
    public function appendUserMessage(int $conversationId, int $adminId, array $input): array
    {
        $this->ownedConversation($conversationId, $adminId);
        $this->validateFields($input, ['role', 'content', 'idempotency_key']);
        $key = $input['idempotency_key'] ?? null;
        if (array_key_exists('idempotency_key', $input) && (!is_string($key) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $key) !== 1)) throw new InvalidArgumentException('idempotency_key 无效', 400);
        if (($input['role'] ?? 'user') !== 'user') throw new InvalidArgumentException('只允许 user 消息', 400);
        $content = $this->userTextBlocks($input['content'] ?? null, true);
        $create = function () use ($conversationId, $adminId, $content): array {
            if (in_array('attachment', array_column($content, 'type'), true)) return ($this->attachments ?? AiAttachmentService::production())->append($conversationId, $adminId, $content, $this->store);
            return $this->store->appendMessage($conversationId, ['role'=>'user', 'content'=>$content, 'metadata'=>[], 'parent_id'=>null]);
        };
        // 兼容旧客户端；新客户端为每条逻辑消息传入稳定键。
        if ($key === null) return $create();
        $canonical = array_map(static fn (array $block): array => $block['type'] === 'text' ? ['type'=>'text','text'=>$block['text']] : ['type'=>'attachment','attachment_id'=>$block['attachment_id']], $content);
        return $this->store->idempotentMessage($conversationId, $adminId, $key, hash('sha256', json_encode(['role'=>'user','content'=>$canonical], JSON_THROW_ON_ERROR)), $create);
    }

    /** 任务入口不接受客户端历史、工具、系统提示或配置快照。 */
    public function createPublicTask(int $conversationId, int $adminId, array $input): array
    {
        $this->ownedConversation($conversationId, $adminId);
        $this->validateFields($input, ['message_id', 'idempotency_key', 'type']);
        $messageId = $input['message_id'] ?? null;
        if (!is_int($messageId) || $messageId <= 0) throw new InvalidArgumentException('message_id 必须为正整数', 400);
        if (!is_string($input['idempotency_key'] ?? null) || trim($input['idempotency_key']) === '' || strlen($input['idempotency_key']) > 128) throw new InvalidArgumentException('idempotency_key 无效', 400);
        if (($input['type'] ?? 'chat') !== 'chat') throw new InvalidArgumentException('公开任务只支持 chat', 400);
        $rows = $this->store->messages($conversationId);
        $target = null;
        foreach ($rows as $row) if ((int) $row['id'] === $messageId && ($row['role'] ?? '') === 'user') $target = $row;
        if ($target === null) throw new RuntimeException('资源不存在', 404);
        usort($rows, static fn (array $a, array $b): int => (int) $a['sequence'] <=> (int) $b['sequence']);
        $messages = [];
        $bytes = 0;
        foreach ($rows as $row) {
            if ((int) $row['sequence'] > (int) $target['sequence']) break;
            $role = $row['role'] ?? '';
            if (!in_array($role, ['user', 'assistant'], true)) continue;
            if ($role === 'assistant' && !empty($row['metadata']['tool_calls'])) continue;
            if ($role === 'assistant' && isset($row['metadata']['protocol_history'])) {
                $history = $row['metadata']['protocol_history'];
                $bytes += strlen(json_encode($history, JSON_THROW_ON_ERROR));
                if ($bytes > 1024 * 1024) throw new InvalidArgumentException('历史文本超过请求上限', 413);
                $messages = array_merge($messages, $history);
                continue;
            }
            $blocks = $row['content'] ?? [];
            // 兼容旧版数据库文本对象，但绝不把旧消息元数据转成模型指令。
            if (is_array($blocks) && array_keys($blocks) === ['text']) $blocks = [['type'=>'text', 'text'=>$blocks['text']]];
            $blocks = $this->userTextBlocks($blocks, $role === 'user');
            $text = in_array('attachment', array_column($blocks, 'type'), true)
                ? ($this->attachments ?? AiAttachmentService::production())->modelHistory($conversationId, $adminId, $row)
                : implode("\n", array_column($blocks, 'text'));
            $bytes += is_string($text) ? strlen($text) : strlen(json_encode($text, JSON_THROW_ON_ERROR));
            if ($bytes > 1024 * 1024) throw new InvalidArgumentException('历史文本超过请求上限', 413);
            $message = ['role'=>$role, 'content'=>$text];
            if ($role === 'assistant' && isset($row['metadata']['protocol_context'])) $message['protocol_context'] = $row['metadata']['protocol_context'];
            $messages[] = $message;
        }
        return $this->createTask($conversationId, $adminId, array_replace($input, ['input'=>[
            'messages'=>$messages, 'tools'=>[], 'history_sequence'=>(int) $target['sequence'],
        ]]));
    }

    /** 文本以 UTF-8 用户数据保存，不解析或执行源码。 */
    private function userTextBlocks(mixed $content, bool $allowAttachments = false): array
    {
        if (!is_array($content) || !array_is_list($content) || $content === [] || count($content) > 64) throw new InvalidArgumentException('content 必须为非空块数组', 400);
        $bytes = 0;
        foreach ($content as $block) {
            if ($allowAttachments && is_array($block) && ($block['type'] ?? null) === 'attachment') {
                if (array_diff(array_keys($block), ['type','attachment_id']) || !is_int($block['attachment_id'] ?? null) || $block['attachment_id'] <= 0) throw new InvalidArgumentException('附件引用无效', 400);
                continue;
            }
            if (!is_array($block) || array_diff(array_keys($block), ['type', 'text']) || ($block['type'] ?? null) !== 'text'
                || !is_string($block['text'] ?? null) || !mb_check_encoding($block['text'], 'UTF-8') || str_contains($block['text'], "\0")) throw new InvalidArgumentException('只支持合法 UTF-8 text 块', 400);
            $bytes += strlen($block['text']);
        }
        if ($bytes > 128 * 1024) throw new InvalidArgumentException('消息文本超过 128KiB', 413);
        return $content;
    }

    public function appendMessage(int $conversationId, int $adminId, array $input): array
    {
        $this->ownedConversation($conversationId, $adminId);
        $role = (string) ($input['role'] ?? 'user');
        if (!in_array($role, ['system', 'user', 'assistant', 'tool'], true)) throw new InvalidArgumentException('消息角色无效');
        return $this->store->appendMessage($conversationId, [
            'role' => $role,
            'content' => (array) ($input['content'] ?? []),
            'metadata' => (array) ($input['metadata'] ?? []),
            'parent_id' => isset($input['parent_id']) ? (int) $input['parent_id'] : null,
        ]);
    }

    public function listMessages(int $conversationId, int $adminId): array
    {
        $this->ownedConversation($conversationId, $adminId);
        return AiAuditService::publicValue($this->store->messages($conversationId));
    }

    public function createTask(int $conversationId, int $adminId, array $input): array
    {
        $conversation = $this->ownedConversation($conversationId, $adminId);
        $key = trim((string) ($input['idempotency_key'] ?? ''));
        if ($key === '') throw new InvalidArgumentException('idempotency_key 必填');
        $selection = $this->profileSelection($conversation, $adminId);
        $snapshot = $selection['snapshot'] ?? null;
        $limits = $this->limits;
        if ($snapshot !== null) {
            $config = $snapshot['configuration'];
            $limits = array_replace($limits, ['max_rounds'=>$config['max_iterations'], 'max_input_tokens'=>$config['max_input_tokens'] ?? 0, 'max_output_tokens'=>$config['max_output_tokens'] ?? 0]);
        }
        // 模型与连接配置不接受 task input 覆盖，凭据只在执行时从服务端读取。
        $taskInput = array_diff_key((array) ($input['input'] ?? []), array_flip(['model', 'provider', 'api_key', 'base_url', 'profile_id', 'profile_snapshot', 'reasoning_effort']));
        return $this->store->createTask([
            'conversation_id' => $conversationId,
            'message_id' => isset($input['message_id']) ? (int) $input['message_id'] : null,
            'idempotency_key' => mb_substr($key, 0, 128),
            'operation_token' => hash('sha256', $conversationId . "\0" . $key),
            'type' => (string) ($input['type'] ?? 'chat'),
            'status' => 'pending',
            'approval_mode' => $conversation['approval_mode'],
            'provider' => $selection['provider'],
            'model' => $selection['model'],
            'max_rounds' => max(1, (int) ($limits['max_rounds'] ?? 1)),
            'max_cost' => max(0.0, (float) ($this->limits['max_total_cost'] ?? 0)),
            'input_token_budget' => max(0, (int) ($limits['max_input_tokens'] ?? 0)),
            'output_token_budget' => max(0, (int) ($limits['max_output_tokens'] ?? 0)),
            'total_token_budget' => max(0, (int) ($limits['max_input_tokens'] ?? 0) + (int) ($limits['max_output_tokens'] ?? 0)),
            'input' => array_replace($taskInput, ['admin_id' => $adminId], $snapshot === null ? [] : ['profile_snapshot'=>$snapshot]),
        ]);
    }

    public function getTask(int $taskId, int $adminId): array
    {
        $task = $this->store->task($taskId);
        if (!$task || !$this->store->conversation((int) $task['conversation_id'], $adminId)) throw new RuntimeException('资源不存在', 404);
        return AiAuditService::publicValue($task);
    }

    public function cancelTask(int $taskId, int $adminId): bool
    {
        $this->getTask($taskId, $adminId);
        return $this->store->compareAndSetTask($taskId, ['pending', 'running', 'paused'], ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')]);
    }

    private function profileSelection(array $input, int $adminId): array
    {
        $id = $input['profile_id'] ?? null;
        // null 继承档案；省略由会话合并保留。禁止以 default 混淆档案的不发送参数语义。
        $effort = $input['reasoning_effort'] ?? null;
        if (!in_array($effort, [null, ...\app\common\ai\provider\AiModelCapabilities::REASONING_EFFORTS], true)) throw new InvalidArgumentException('reasoning_effort 必须为 null、low、medium、high、xhigh、max 或 ultra', 400);
        if ($id === null) {
            if ($effort !== null) throw new InvalidArgumentException('会话推理覆盖需要选择能力已声明的档案', 400);
            return $this->modelSelection($input);
        }
        if (!is_int($id) || $id <= 0) throw new InvalidArgumentException('profile_id 必须为正整数或 null');
        $snapshot = ($this->profiles ?? AiConfigurationProfileService::production())->snapshot($adminId, $id, $input['model'] ?? null, $effort);
        return ['profile_id'=>$id, 'provider'=>$snapshot['configuration']['provider'], 'model'=>$snapshot['model'], 'snapshot'=>$snapshot];
    }

    /** 单供应商基线：只选择当前可信配置下的模型，不解析任意供应商标识。 */
    private function modelSelection(array $input): array
    {
        $config = (array) \think\facade\Config::get('ai.provider', []);
        $provider = trim((string) ($config['name'] ?? ''));
        $requested = $input['provider'] ?? '';
        if (!is_string($requested) || (trim($requested) !== '' && trim($requested) !== $provider)) {
            throw new InvalidArgumentException('不支持跨供应商模型选择');
        }
        $model = $input['model'] ?? ($config['model'] ?? '');
        // 未配置的旧会话可保持草稿；实际执行不得退回全局模型。
        if ($model === '' && ($config['model'] ?? '') !== '') $model = $config['model'];
        return ['provider' => $provider, 'model' => $model === '' ? '' : $this->validateName($model, 100)];
    }

    private function assertModeAuthorized(string $mode, bool $fullAccessAuthorized, bool $approveAuthorized): void
    {
        if ($mode === 'full_access' && !$fullAccessAuthorized) {
            ($this->audit) && ($this->audit)('ai.full_access.denied', ['mode' => $mode]);
            throw new RuntimeException('缺少 development:ai:full-access capability', 403);
        }
        if ($mode === 'agent_approval' && !$approveAuthorized) {
            ($this->audit) && ($this->audit)('ai.agent_approval.denied', ['mode' => $mode]);
            throw new RuntimeException('缺少 development:ai:approve capability', 403);
        }
        if (!in_array($mode, ['request_approval', 'agent_approval', 'full_access'], true)) throw new InvalidArgumentException('审批模式无效');
    }

    private function modeLevel(string $mode): int
    {
        return array_search($mode, ['request_approval', 'agent_approval', 'full_access'], true) ?: 0;
    }

    private function validateFields(array $input, array $fields, bool $required = true): void
    {
        if (($required && $input === []) || array_diff(array_keys($input), $fields)) throw new InvalidArgumentException('请求字段为空或包含不支持的字段');
    }

    private function validateName(mixed $value, int $maximum): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException('名称必须为 UTF-8 字符串');
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maximum || preg_match('/[\x00-\x1f\x7f]/u', $value)) throw new InvalidArgumentException('名称为空、过长或包含控制字符');
        return $value;
    }

    private function validateGroupId(mixed $id, int $adminId): ?int
    {
        if ($id === null) return null;
        // 兼容 ORM bigint 的十进制字符串，只接受无损、无歧义的正整数。
        if (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id) === 1) {
            $normalized = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalized !== false) $id = $normalized;
        }
        if (!is_int($id) || $id <= 0) throw new InvalidArgumentException('group_id 必须为正整数或 null');
        $this->ownedGroup($id, $adminId);
        return $id;
    }

    private function ownedConversation(int $id, int $adminId): array
    {
        if ($id <= 0 || $adminId <= 0) throw new RuntimeException('资源不存在', 404);
        $conversation = $this->store->conversation($id, $adminId);
        if (!$conversation) throw new RuntimeException('资源不存在', 404);
        return $conversation;
    }

    private function ownedGroup(int $id, int $adminId): array
    {
        if ($id <= 0 || $adminId <= 0) throw new RuntimeException('分组不存在', 404);
        $group = $this->store->conversationGroup($id, $adminId);
        if (!$group) throw new RuntimeException('分组不存在', 404);
        return $group;
    }
}
