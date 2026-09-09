<?php

declare(strict_types=1);

namespace app\common\form\action;

use Closure;
use InvalidArgumentException;
use JsonException;

final class FormActionRegistry
{
    private readonly Closure $idempotencyClaimer;

    public function __construct(private readonly array $actions = [], ?callable $idempotencyClaimer = null)
    {
        foreach ($actions as $key => $definition) {
            if (!preg_match('/^[a-z][a-z0-9._-]*$/', (string) $key) || !is_array($definition) || !is_callable($definition['handler'] ?? null)) {
                throw new InvalidArgumentException('表单动作注册不合法：' . $key);
            }
        }
        $this->idempotencyClaimer = Closure::fromCallable($idempotencyClaimer ?? static fn (string $scope): bool => true);
    }

    public function definitions(): array
    {
        return array_map(static fn (array $definition): array => [
            'permission' => (string) ($definition['permission'] ?? ''),
            'parameters' => array_values((array) ($definition['parameters'] ?? [])),
            'capabilityVersion' => (string) ($definition['capabilityVersion'] ?? '1'),
        ], $this->actions);
    }

    public function execute(string $key, array $parameters, callable $permissionChecker, array $context = []): mixed
    {
        $definition = $this->actions[$key] ?? null;
        if (!is_array($definition)) {
            throw new InvalidArgumentException('FORM_ACTION_NOT_REGISTERED');
        }
        $permission = (string) ($definition['permission'] ?? '');
        if ($permission === '' || !$permissionChecker($permission)) {
            throw new InvalidArgumentException('FORM_ACTION_FORBIDDEN');
        }
        $idempotencyKey = trim((string) ($context['idempotencyKey'] ?? ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128 || preg_match('/[\x00-\x1F\x7F]/', $idempotencyKey)) {
            throw new InvalidArgumentException('FORM_ACTION_IDEMPOTENCY_KEY_REQUIRED');
        }
        $maxChain = max(1, min(20, (int) ($definition['maxChain'] ?? 5)));
        if (max(1, (int) ($context['chainDepth'] ?? 1)) > $maxChain) {
            throw new InvalidArgumentException('FORM_ACTION_CHAIN_LIMIT');
        }
        $scope = hash('sha256', (string) ($context['formKey'] ?? '') . "\0" . $key . "\0" . $idempotencyKey);
        if (!(($this->idempotencyClaimer)($scope))) {
            throw new InvalidArgumentException('FORM_ACTION_DUPLICATE');
        }

        $allowed = array_flip(array_map('strval', (array) ($definition['parameters'] ?? [])));
        $startedAt = hrtime(true);
        $result = ($definition['handler'])(array_intersect_key($parameters, $allowed));
        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
        if ($elapsedMs > max(1, min(30000, (int) ($definition['timeoutMs'] ?? 3000)))) {
            throw new InvalidArgumentException('FORM_ACTION_TIMEOUT');
        }
        if (!$this->safeResult($result)) {
            throw new InvalidArgumentException('FORM_ACTION_UNSAFE_RESULT');
        }
        return $result;
    }

    private function safeResult(mixed $result): bool
    {
        if (!is_null($result) && !is_scalar($result) && !is_array($result)) {
            return false;
        }
        try {
            json_encode($result, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
    }
}
