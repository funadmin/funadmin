<?php

declare(strict_types=1);

namespace app\console\ai\service;

/** AI 开发助手审批策略矩阵。 */
final class ApprovalPolicyEngine
{
    private const MODES = ['request_approval', 'agent_approval', 'full_access'];

    private const OPERATIONS = [
        'read', 'search', 'network', 'write', 'delete', 'shell', 'test', 'build',
        'dependency', 'migration', 'git_write', 'apply_workspace', 'host_sensitive', 'deploy',
    ];

    private const MATRIX = [
        'request_approval' => [
            'read' => 'allow',
            'search' => 'allow',
        ],
        'agent_approval' => [
            'read' => 'allow',
            'search' => 'allow',
            'network' => 'allow',
            'write' => 'allow',
            'test' => 'allow',
            'build' => 'allow',
        ],
        'full_access' => [
            'read' => 'allow',
            'search' => 'allow',
            'network' => 'allow',
            'write' => 'allow',
            'delete' => 'allow',
            'shell' => 'allow',
            'test' => 'allow',
            'build' => 'allow',
            'dependency' => 'allow',
            'migration' => 'allow',
            'git_write' => 'allow',
        ],
    ];

    public function decide(string $mode, string $operation, bool $permanentlyDangerous = false): string
    {
        if (!isset(self::MATRIX[$mode]) || !in_array($operation, self::OPERATIONS, true)) {
            return 'deny';
        }
        if (in_array($operation, ['host_sensitive', 'deploy'], true)) {
            return 'deny';
        }
        if ($operation === 'apply_workspace') {
            return 'ask';
        }

        return self::MATRIX[$mode][$operation] ?? 'ask';
    }

    public function decideModeTransition(string $currentMode, string $targetMode): string
    {
        $currentLevel = array_search($currentMode, self::MODES, true);
        $targetLevel = array_search($targetMode, self::MODES, true);
        if ($currentLevel === false || $targetLevel === false) {
            return 'deny';
        }

        return $targetLevel > $currentLevel ? 'ask' : 'allow';
    }

    public function pendingApprovalMode(string $modeSnapshot, string $currentMode): string
    {
        return in_array($modeSnapshot, self::MODES, true) ? $modeSnapshot : 'request_approval';
    }
}
