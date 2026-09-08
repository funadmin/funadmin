<?php

declare(strict_types=1);

namespace app\common\form\dataSource;

final class FormDataSourceRegistry
{
    private const KINDS = ['static', 'dictionary', 'department', 'user', 'relation', 'endpoint', 'computed'];
    private const STALE_POLICIES = ['clear', 'retain', 'revalidate'];
    private const PROVIDER_PARAMETERS = [
        'dictionary' => ['locale'],
        'department' => ['root', 'keyword'],
        'user' => ['keyword', 'department_id'],
        'relation' => ['keyword', 'tenant_id', 'parent_id'],
    ];
    private const RESPONSE_DEFAULTS = ['items' => 'data', 'label' => 'label', 'value' => 'value'];

    private function __construct(private readonly array $endpoints)
    {
    }

    public static function core(array $endpoints = []): self
    {
        return new self($endpoints);
    }

    public function kinds(): array
    {
        return self::KINDS;
    }

    public function applyStaleValuePolicy(string $policy, mixed $oldValue, array $validValues): mixed
    {
        if (!in_array($policy, self::STALE_POLICIES, true)) {
            throw new FormDataSourceException('旧值策略不合法', 'FORM_DATA_SOURCE_STALE_POLICY_INVALID');
        }
        if ($policy === 'retain') {
            return $oldValue;
        }
        if ($policy === 'revalidate' && in_array($oldValue, $validValues, true)) {
            return $oldValue;
        }
        return null;
    }

    public function resolve(array $definition, array $context = []): array
    {
        $kind = (string) ($definition['kind'] ?? '');
        if (!in_array($kind, self::KINDS, true)) {
            throw new FormDataSourceException('数据源类型未注册：' . $kind, 'FORM_DATA_SOURCE_NOT_REGISTERED');
        }

        $staleValue = (string) ($definition['staleValue'] ?? 'clear');
        if (!in_array($staleValue, self::STALE_POLICIES, true)) {
            throw new FormDataSourceException('旧值策略不合法', 'FORM_DATA_SOURCE_STALE_POLICY_INVALID');
        }
        $response = $this->responseMapping((array) ($definition['response'] ?? []));

        $resolved = match ($kind) {
            'static' => $this->resolveStatic($definition, $response),
            'dictionary' => $this->resolveProvider($kind, ['code' => (string) ($definition['dictionary'] ?? '')], $definition, $context),
            'department' => $this->resolveProvider($kind, ['root' => $definition['root'] ?? null], $definition, $context),
            'user' => $this->resolveProvider($kind, [], $definition, $context),
            'relation' => $this->resolveProvider($kind, ['relation' => (string) ($definition['relation'] ?? '')], $definition, $context),
            'endpoint' => $this->resolveEndpoint($definition, $context, $response),
            'computed' => $this->resolveComputed($definition, $context),
        };

        return [...$resolved, 'staleValue' => $staleValue];
    }

    private function resolveStatic(array $definition, array $response): array
    {
        $items = $this->readPath($definition['options'] ?? [], $response['items']);
        $options = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $option = [
                'label' => $this->readPath($item, $response['label']),
                'value' => $this->readPath($item, $response['value']),
            ];
            if (isset($response['disabled'])) {
                $option['disabled'] = (bool) $this->readPath($item, $response['disabled']);
            }
            $options[] = $option;
        }
        return ['kind' => 'static', 'options' => $options, 'response' => $response];
    }

    private function resolveProvider(string $kind, array $fixed, array $definition, array $context): array
    {
        $allowed = self::PROVIDER_PARAMETERS[$kind] ?? [];
        $mapped = $this->mapParameters((array) ($definition['params'] ?? []), $context, $allowed);
        return ['kind' => $kind, 'provider' => $kind, 'arguments' => array_filter([...$fixed, ...$mapped], static fn ($value) => $value !== null && $value !== '')];
    }

    private function resolveEndpoint(array $definition, array $context, array $response): array
    {
        $endpoint = (string) ($definition['endpoint'] ?? '');
        if (preg_match('#^https?://#i', $endpoint) || !isset($this->endpoints[$endpoint])) {
            throw new FormDataSourceException('端点未登记', 'FORM_DATA_SOURCE_ENDPOINT_NOT_ALLOWED');
        }
        $metadata = (array) $this->endpoints[$endpoint];
        return [
            'kind' => 'endpoint',
            'endpoint' => $endpoint,
            'arguments' => $this->mapParameters((array) ($definition['params'] ?? []), $context, (array) ($metadata['parameters'] ?? [])),
            'response' => $response,
            'permission' => (string) ($metadata['permission'] ?? ''),
        ];
    }

    private function resolveComputed(array $definition, array $context): array
    {
        $operation = (string) ($definition['operation'] ?? '');
        if ($operation !== 'concat') {
            throw new FormDataSourceException('计算操作未登记', 'FORM_DATA_SOURCE_COMPUTATION_NOT_ALLOWED');
        }
        $values = array_map(fn ($input) => $this->resolveValue($input, $context), (array) ($definition['inputs'] ?? []));
        $value = implode('', array_map(static fn ($item) => is_scalar($item) ? (string) $item : '', $values));
        return ['kind' => 'computed', 'operation' => $operation, 'options' => [['label' => $value, 'value' => $value]]];
    }

    private function mapParameters(array $parameters, array $context, array $allowed): array
    {
        $mapped = [];
        foreach ($parameters as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            $mapped[$name] = $this->resolveValue($value, $context);
        }
        return $mapped;
    }

    private function resolveValue(mixed $value, array $context): mixed
    {
        if (!is_string($value) || !str_starts_with($value, '$')) {
            return $value;
        }
        if ($value === '$search') {
            return $context['search'] ?? null;
        }
        return $this->readPath($context, substr($value, 1));
    }

    private function responseMapping(array $mapping): array
    {
        $result = [...self::RESPONSE_DEFAULTS, ...array_intersect_key($mapping, array_flip(['items', 'label', 'value', 'disabled']))];
        foreach ($result as $path) {
            if (!is_string($path) || !$this->safePath($path)) {
                throw new FormDataSourceException('响应映射路径不合法', 'FORM_DATA_SOURCE_RESPONSE_MAPPING_INVALID');
            }
        }
        return $result;
    }

    private function safePath(string $path): bool
    {
        if ($path === '$') {
            return true;
        }
        return preg_match('/^(?!.*(?:^|\.)(?:__proto__|prototype|constructor)(?:\.|$))[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$/', $path) === 1;
    }

    private function readPath(mixed $subject, string $path): mixed
    {
        if ($path === '$') {
            return $subject;
        }
        foreach (explode('.', $path) as $segment) {
            if (!is_array($subject) || !array_key_exists($segment, $subject)) {
                return null;
            }
            $subject = $subject[$segment];
        }
        return $subject;
    }
}
