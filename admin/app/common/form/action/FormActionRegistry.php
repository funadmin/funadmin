<?php

declare(strict_types=1);

namespace app\common\form\action;

use app\common\form\DeadlineExecutor;
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
            'parameterTypes' => (array) ($definition['parameterTypes'] ?? []),
            'locations' => array_values((array) ($definition['locations'] ?? [])),
            'targets' => array_values((array) ($definition['targets'] ?? [])),
            'effect' => (string) ($definition['effect'] ?? 'write'),
            'batch' => ($definition['batch'] ?? false) === true,
            'requiresConfirmation' => ($definition['requiresConfirmation'] ?? true) === true,
            'resultContract' => (string) ($definition['resultContract'] ?? 'json'),
        ], $this->actions);
    }

    /** 仅公开适用且已授权的描述，不暴露处理器和内部配置。 */
    public function listCatalog(callable $permissionChecker, string $location, string $target): array
    {
        return array_filter($this->definitions(), static fn (array $item): bool =>
            $item['permission'] !== '' && $permissionChecker($item['permission'])
            && in_array($location, $item['locations'], true) && in_array($target, $item['targets'], true)
            && in_array($item['effect'], ['read', 'write'], true)
            && $item['resultContract'] === 'json'
            && array_keys($item['parameterTypes']) === $item['parameters']
            && !array_diff(array_values($item['parameterTypes']), ['string', 'integer', 'number', 'boolean', 'ids'])
        );
    }

    /** 仅供服务端列表执行服务调用；HTTP 入口不能直接传入处理器参数。 */
    public function invokeList(string $key, array $parameters, callable $permissionChecker): mixed
    {
        $definition = $this->actions[$key] ?? null;
        if (!$definition || empty($definition['locations'])) throw new InvalidArgumentException('FORM_ACTION_NOT_REGISTERED');
        if (!$permissionChecker((string) ($definition['permission'] ?? ''))) throw new InvalidArgumentException('FORM_ACTION_FORBIDDEN');
        if (array_diff(array_keys($parameters), $definition['parameters'] ?? [])) throw new InvalidArgumentException('FORM_ACTION_PARAMETER_NOT_ALLOWED');
        foreach (($definition['parameterTypes'] ?? []) as $name => $type) {
            $value = $parameters[$name] ?? null;
            $valid = match ($type) {
                'string' => is_string($value) && strlen($value) <= 10000,
                'integer' => is_int($value),
                'number' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                'boolean' => is_bool($value),
                'ids' => is_array($value) && array_is_list($value) && count($value) <= 200 && !array_filter($value, static fn ($id) => !is_int($id) && !is_string($id)),
                default => false,
            };
            if (!$valid) throw new InvalidArgumentException('FORM_LIST_PARAMETER_INVALID');
        }
        $result = DeadlineExecutor::run(fn () => ($definition['handler'])($parameters), (int) ($definition['timeoutMs'] ?? 3000),
            static fn () => new InvalidArgumentException('FORM_ACTION_TIMEOUT'));
        if (!$this->safeResult($result)) throw new InvalidArgumentException('FORM_ACTION_UNSAFE_RESULT');
        return $result;
    }

    public function execute(string $key, array $parameters, callable $permissionChecker, array $context = []): mixed
    {
        $definition = $this->actions[$key] ?? null;
        if (!empty($definition['locations'])) throw new InvalidArgumentException('FORM_LIST_ACTION_ENTRY_REQUIRED');
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
        $result = DeadlineExecutor::run(
            fn (): mixed => ($definition['handler'])(array_intersect_key($parameters, $allowed)),
            (int) ($definition['timeoutMs'] ?? 3000),
            static fn (): InvalidArgumentException => new InvalidArgumentException('FORM_ACTION_TIMEOUT')
        );
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
