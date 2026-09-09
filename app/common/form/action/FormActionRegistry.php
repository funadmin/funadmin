<?php

declare(strict_types=1);

namespace app\common\form\action;

use InvalidArgumentException;

final class FormActionRegistry
{
    public function __construct(private readonly array $actions = [])
    {
        foreach ($actions as $key => $definition) {
            if (!preg_match('/^[a-z][a-z0-9._-]*$/', (string) $key) || !is_array($definition) || !is_callable($definition['handler'] ?? null)) {
                throw new InvalidArgumentException('表单动作注册不合法：' . $key);
            }
        }
    }

    public function definitions(): array
    {
        return array_map(static fn (array $definition): array => [
            'permission' => (string) ($definition['permission'] ?? ''),
            'parameters' => array_values((array) ($definition['parameters'] ?? [])),
            'capabilityVersion' => (string) ($definition['capabilityVersion'] ?? '1'),
        ], $this->actions);
    }

    public function execute(string $key, array $parameters, callable $permissionChecker): mixed
    {
        $definition = $this->actions[$key] ?? null;
        if (!is_array($definition)) throw new InvalidArgumentException('FORM_ACTION_NOT_REGISTERED');
        $permission = (string) ($definition['permission'] ?? '');
        if ($permission === '' || !$permissionChecker($permission)) throw new InvalidArgumentException('FORM_ACTION_FORBIDDEN');
        $allowed = array_flip((array) ($definition['parameters'] ?? []));
        return ($definition['handler'])(array_intersect_key($parameters, $allowed));
    }
}
