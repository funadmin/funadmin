<?php

declare(strict_types=1);

namespace app\console\ai\service;

use InvalidArgumentException;

/** AI 工具唯一注册表：Schema、审批操作、风险、超时与副作用均不可由调用方覆盖。 */
final class AgentToolRegistry
{
    private const FORBIDDEN = ['host_sensitive', 'deploy', 'push', 'host_credentials'];
    private const IMAGE_TOOLS = ['read', 'list', 'search', 'git_status', 'git_diff', 'write', 'create', 'move', 'delete', 'shell', 'test', 'build', 'migration', 'git_write', 'crud_proposal'];

    private array $definitions;

    public function __construct(array $allowlist = [])
    {
        $path = ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024, 'format' => 'project-path'];
        $argv = ['type' => 'array', 'minItems' => 1, 'maxItems' => 64, 'items' => ['type' => 'string', 'maxLength' => 4096]];
        $definitions = [
            'read' => $this->definition('read', 'low', 10, false, ['path' => $path], ['path']),
            'list' => $this->definition('read', 'low', 10, false, ['path' => $path], ['path']),
            'search' => $this->definition('search', 'low', 20, false, ['query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 512], 'path' => $path], ['query']),
            'git_status' => $this->definition('read', 'low', 15, false),
            'git_diff' => $this->definition('read', 'low', 20, false, ['path' => $path]),
            'write' => $this->definition('write', 'medium', 15, true, ['path' => $path, 'content' => ['type' => 'string', 'maxLength' => 1048576]], ['path', 'content']),
            'create' => $this->definition('write', 'medium', 15, true, ['path' => $path, 'content' => ['type' => 'string', 'maxLength' => 1048576]], ['path', 'content']),
            'move' => $this->definition('write', 'high', 15, true, ['from' => $path, 'to' => $path], ['from', 'to']),
            'delete' => $this->definition('delete', 'high', 15, true, ['path' => $path], ['path']),
            'shell' => $this->definition('shell', 'high', 60, true, ['argv' => $argv], ['argv']),
            'test' => $this->definition('test', 'medium', 300, false, ['argv' => $argv], ['argv']),
            'build' => $this->definition('build', 'medium', 600, true, ['argv' => $argv], ['argv']),
            'dependency' => $this->definition('dependency', 'high', 600, true, ['argv' => $argv], ['argv']),
            'migration' => $this->definition('migration', 'critical', 300, true, ['argv' => $argv], ['argv']),
            'git_write' => $this->definition('git_write', 'critical', 60, true, ['argv' => $argv], ['argv']),
            'crud_proposal' => $this->definition('write', 'medium', 60, true, ['path' => $path, 'proposal' => ['type' => 'object']], ['path', 'proposal']),
        ];
        $allowed = array_values(array_unique(array_filter(array_map('strval', $allowlist))));
        $this->definitions = array_intersect_key($definitions, array_flip(array_intersect($allowed, self::IMAGE_TOOLS)));
    }

    public function names(): array
    {
        return array_keys($this->definitions);
    }

    public function get(string $name): array
    {
        if (!isset($this->definitions[$name])) {
            throw new InvalidArgumentException('未知或禁用的 AI 工具');
        }
        return $this->definitions[$name];
    }

    public function decision(string $name): string
    {
        return in_array($name, self::FORBIDDEN, true) ? 'deny' : (isset($this->definitions[$name]) ? 'registered' : 'deny');
    }

    public function validate(string $name, array $arguments): array
    {
        $definition = $this->get($name);
        $schema = $definition['schema'];
        foreach ($schema['required'] as $required) {
            if (!array_key_exists($required, $arguments)) {
                throw new InvalidArgumentException("工具 {$name} 缺少参数 {$required}");
            }
        }
        foreach ($arguments as $key => $value) {
            if (!isset($schema['properties'][$key])) {
                throw new InvalidArgumentException("工具 {$name} 包含未知参数 {$key}");
            }
            $this->validateValue($value, $schema['properties'][$key], (string) $key);
        }
        return $definition;
    }

    private function definition(string $operation, string $risk, int $timeout, bool $sideEffects, array $properties = [], array $required = []): array
    {
        return ['schema' => ['type' => 'object', 'properties' => $properties, 'required' => $required, 'additionalProperties' => false], 'operation' => $operation, 'risk' => $risk, 'timeout' => $timeout, 'sideEffects' => $sideEffects];
    }

    private function validateValue(mixed $value, array $schema, string $key): void
    {
        $valid = match ($schema['type']) {
            'string' => is_string($value) && strlen($value) >= ($schema['minLength'] ?? 0) && strlen($value) <= ($schema['maxLength'] ?? PHP_INT_MAX),
            'array' => is_array($value) && array_is_list($value) && count($value) >= ($schema['minItems'] ?? 0) && count($value) <= ($schema['maxItems'] ?? PHP_INT_MAX) && array_filter($value, static fn (mixed $item): bool => !is_string($item)) === [],
            'object' => is_array($value),
            default => false,
        };
        if (!$valid || (($schema['format'] ?? '') === 'project-path' && !$this->validProjectPath($value))) {
            throw new InvalidArgumentException("工具参数 {$key} 不合法");
        }
    }

    private function validProjectPath(string $path): bool
    {
        $segments = preg_split('#[\\\\/]#', $path) ?: [];
        return !str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1
            && !in_array('..', $segments, true) && !in_array('', $segments, true) && !str_contains($path, "\0");
    }
}
