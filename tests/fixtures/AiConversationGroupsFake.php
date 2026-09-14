<?php

declare(strict_types=1);

/** 分组端口的内存实现，删除操作包含归档语义。 */
trait AiConversationGroupsFake
{
    public function conversationPageRows(int $adminId, array $filters, ?int $cursor, int $limit): array
    {
        $rows = array_filter($this->conversations($adminId), static function (array $row) use ($filters, $cursor): bool {
            if ($cursor !== null && $row['id'] >= $cursor) return false;
            foreach (['is_archived', 'is_unread'] as $key) if (isset($filters[$key]) && (int) ($row[$key] ?? 0) !== $filters[$key]) return false;
            if (isset($filters['group_id']) && (int) ($row['group_id'] ?? 0) !== $filters['group_id']) return false;
            return !isset($filters['search']) || str_contains($row['title'], $filters['search']);
        });
        usort($rows, static fn ($a, $b) => $b['id'] <=> $a['id']);
        return array_slice($rows, 0, $limit);
    }

    public function messagePageRows(int $conversationId, ?array $cursor, bool $forward, int $limit): array
    {
        $rows = array_filter($this->messages($conversationId), static function (array $row) use ($cursor, $forward): bool {
            if ($cursor === null) return true;
            $comparison = [(int)$row['sequence'], (int)$row['id']] <=> $cursor;
            return $forward ? $comparison > 0 : $comparison < 0;
        });
        usort($rows, static fn ($a, $b) => ($forward ? 1 : -1) * ([$a['sequence'], $a['id']] <=> [$b['sequence'], $b['id']]));
        return array_slice($rows, 0, $limit);
    }

    private array $messageKeys = [];
    public function idempotentMessage(int $conversationId, int $adminId, string $key, string $digest, callable $create): array
    {
        if (!$this->conversation($conversationId, $adminId)) throw new RuntimeException('资源不存在', 404);
        $scope = $adminId . ':' . $conversationId . ':' . $key;
        if (isset($this->messageKeys[$scope])) {
            if ($this->messageKeys[$scope]['digest'] !== $digest) throw new RuntimeException('内容不一致', 409);
            return $this->messageKeys[$scope]['message'];
        }
        $message = $create();
        $this->messageKeys[$scope] = ['digest'=>$digest, 'message'=>$message];
        return $message;
    }
    public array $groups = [];
    public bool $failGroupDelete = false;

    public function conversationGroups(int $adminId): array { return array_values(array_filter($this->groups, fn ($row) => $row['admin_id'] === $adminId)); }
    public function conversationGroup(int $id, int $adminId): ?array { $row = $this->groups[$id] ?? null; return $row && $row['admin_id'] === $adminId ? $row : null; }
    public function createConversationGroup(array $data): array { $data['id'] = $this->id++; return $this->groups[$data['id']] = $data; }
    public function updateConversationGroup(int $id, int $adminId, array $data): bool { if (!$this->conversationGroup($id, $adminId)) return false; $this->groups[$id] = array_replace($this->groups[$id], $data); return true; }
    public function deleteConversationGroup(int $id, int $adminId): bool
    {
        if (!$this->conversationGroup($id, $adminId)) return false;
        if ($this->failGroupDelete) throw new RuntimeException('模拟删除失败');
        $this->archiveGroupConversations($id, $adminId);
        unset($this->groups[$id]);
        return true;
    }
    public function archiveGroupConversations(int $groupId, int $adminId): void
    {
        foreach ($this->conversations as &$row) {
            if ($row['admin_id'] === $adminId && ($row['group_id'] ?? null) === $groupId) {
                $row['group_id'] = null;
                $row['is_archived'] = true;
            }
        }
    }
}
