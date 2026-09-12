<?php

declare(strict_types=1);

/** 分组端口的内存实现，删除操作包含归档语义。 */
trait AiConversationGroupsFake
{
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
