<?php

declare(strict_types=1);
namespace app\common\form\action;

use app\common\crud\CrudDefinition;
use InvalidArgumentException;

/** 共享执行边界：快照和记录加载器必须由已授权宿主提供，绝不来自请求。 */
final class ListButtonExecutor
{
    public function __construct(private readonly FormActionRegistry $registry, private readonly string $confirmationSecret, private readonly ?ListActionStore $store = null, private readonly ?ListResourceRegistry $resources = null)
    {
        if (!$resources && strlen($confirmationSecret) < 32) throw new InvalidArgumentException('FORM_LIST_CONFIRMATION_UNAVAILABLE');
    }

    public function execute(array $snapshot, array $request, string $principal, callable $permission, callable $loadRecords): array
    {
        if ($principal === '' || array_diff(array_keys($request), ['buttonId', 'location', 'schemaHash', 'ids', 'input', 'idempotencyKey', 'confirmation', 'filter', 'category', 'sourceSchemaHash'])) $this->fail('FORM_LIST_REQUEST_INVALID');
        if (!is_string($request['schemaHash'] ?? null) || !hash_equals($snapshot['schemaHash'], $request['schemaHash'])) $this->fail('FORM_SCHEMA_CONFLICT');
        $location = $request['location'] ?? '';
        if (!in_array($location, ['row', 'toolbar', 'categoryToolbar', 'categoryNode'], true)) $this->fail('FORM_LIST_ACTION_ADAPTER_UNAVAILABLE');
        $categoryLocation = in_array($location, ['categoryToolbar', 'categoryNode'], true);
        if (isset($request['filter']) && !isset($snapshot['filter'])) $this->fail('FORM_LIST_FILTER_INVALID');
        if ($categoryLocation || array_key_exists('category', $request)) {
            if (!isset($snapshot['categoryContext']) || !is_string($request['sourceSchemaHash'] ?? null)
                || !hash_equals($snapshot['categoryContext']['schemaHash'], $request['sourceSchemaHash'])) $this->fail('FORM_SCHEMA_CONFLICT');
        } elseif (array_key_exists('sourceSchemaHash', $request)) $this->fail('FORM_LIST_REQUEST_INVALID');
        $buttons = $snapshot['schema']['list']['buttons'][$location] ?? [];
        $found = array_values(array_filter($buttons, static fn ($item) => ($item['id'] ?? '') === ($request['buttonId'] ?? null)));
        if (count($found) !== 1) $this->fail('FORM_LIST_BUTTON_NOT_DECLARED');
        $button = $found[0];
        $resourceType = $button['action']['type'] ?? '';
        $resource = in_array($resourceType, ['navigate', 'external', 'copy', 'refresh', 'download'], true);
        if (!$resource && $resourceType !== 'registered') $this->fail('FORM_LIST_ACTION_ADAPTER_UNAVAILABLE');
        if ($resource) {
            if (!$this->resources || (!empty($button['permission']) && !$permission($button['permission']))) $this->fail('FORM_ACTION_FORBIDDEN');
            if (in_array($resourceType, ['navigate', 'external'], true)) $this->resources->definition($button['action'], $permission);
            if ($resourceType === 'copy' && (array_keys($button['params'] ?? []) !== ['text'] || !in_array($button['params']['text']['source'] ?? '', ['row', 'category'], true))) $this->fail('FORM_LIST_BINDING_UNSUPPORTED');
            if (in_array($resourceType, ['refresh', 'download'], true) && !empty($button['params'])) $this->fail('FORM_LIST_BINDING_UNSUPPORTED');
            if ($resourceType === 'download' && (($button['action']['key'] ?? '') !== 'export' || $location !== 'toolbar')) $this->fail('FORM_LIST_ACTION_ADAPTER_UNAVAILABLE');
            $definition = ['effect' => 'read', 'batch' => true];
        } else {
        $key = $button['action']['key'];
        $definition = $this->registry->definitions()[$key] ?? null;
        if (!$definition || !$permission($definition['permission']) || ($button['permission'] ?? '') !== $definition['permission']) $this->fail('FORM_ACTION_FORBIDDEN');
        $target = match ($location) { 'row' => 'record', 'toolbar' => 'selection', 'categoryNode' => 'category', 'categoryToolbar' => 'none' };
        if (!isset($this->registry->listCatalog($permission, $location, $target)[$key])) $this->fail('FORM_LIST_ACTION_ADAPTER_UNAVAILABLE');
        if (($button['action']['capabilityVersion'] ?? '') !== $definition['capabilityVersion']) $this->fail('FORM_ACTION_VERSION_MISMATCH');
        if ($categoryLocation && $definition['effect'] === 'write'
            && !in_array($definition['permission'], $snapshot['categoryContext']['writePermissions'][$location] ?? [], true)) $this->fail('FORM_ACTION_FORBIDDEN');
        }
        // 静态开关先检查；声明条件在授权记录重新加载后逐条复验。
        if (($button['hidden'] ?? false) || ($button['disabled'] ?? false)) $this->fail('FORM_LIST_BUTTON_DISABLED');
        // 持久化原子占位尚未接通时，绝不退回 Cache::remember 或进程内锁。
        if ($definition['effect'] !== 'read' && !$this->store) $this->fail('FORM_LIST_ATOMIC_STORE_UNAVAILABLE');
        $ids = $request['ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids) || (!$ids && !$categoryLocation && (!$resource || $location === 'row')) || ($categoryLocation && $ids !== []) || count($ids) > 200) $this->fail('FORM_LIST_REQUEST_INVALID');
        foreach ($ids as $id) if ((!is_string($id) && !is_int($id)) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', (string) $id)) $this->fail('FORM_LIST_REQUEST_INVALID');
        if (count(array_unique(array_map('strval', $ids))) !== count($ids)) $this->fail('FORM_LIST_REQUEST_INVALID');
        if (($location === 'row' && count($ids) !== 1) || ($location === 'toolbar' && !$definition['batch'])) $this->fail('FORM_LIST_BATCH_UNSUPPORTED');
        $records = $categoryLocation ? [] : $loadRecords($ids);
        $loadedIds = array_map('strval', array_column($records, 'id'));
        $expectedIds = array_map('strval', $ids);
        sort($loadedIds); sort($expectedIds);
        if ($loadedIds !== $expectedIds) $this->fail('FORM_LIST_RECORD_FORBIDDEN');
        $input = $request['input'] ?? [];
        $this->validateInput($button['interaction']['fields'] ?? [], $input);
        $conditionRecords = $categoryLocation ? [$snapshot['categoryContext']['record'] ?? []] : ($records ?: [[]]);
        foreach ($conditionRecords as $record) {
            if ((isset($button['visibleWhen']) && !$this->condition($button['visibleWhen'], $record))
                || (isset($button['disabledWhen']) && $this->condition($button['disabledWhen'], $record))) $this->fail('FORM_LIST_BUTTON_DISABLED');
        }
        $parameters = [];
        foreach (($button['params'] ?? []) as $name => $binding) {
            $source = $binding['source'] ?? '';
            if ($source === 'literal') $parameters[$name] = $binding['value'] ?? null;
            elseif ($source === 'form') $parameters[$name] = $input[$binding['field']] ?? null;
            elseif ($source === 'selection' && $location === 'toolbar' && ($binding['field'] ?? '') === 'ids') $parameters[$name] = $ids;
            elseif ($source === 'filter' || $source === 'category') {
                $values = $source === 'filter' ? ($snapshot['filter'] ?? []) : ($snapshot['categoryContext']['record'] ?? []);
                $field = $binding['field'] ?? '';
                if (!is_string($field) || !preg_match('/^[a-z][a-z0-9_]*$/D', $field) || in_array($field, ['constructor', 'prototype'], true) || !array_key_exists($field, $values)) $this->fail('FORM_LIST_FIELD_FORBIDDEN');
                $parameters[$name] = $values[$field];
            }
            elseif ($source === 'row' && $location === 'row') {
                $field = $binding['field'] ?? '';
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $field) || in_array($field, ['constructor', 'prototype'], true) || !array_key_exists($field, $records[0])) $this->fail('FORM_LIST_FIELD_FORBIDDEN');
                $parameters[$name] = $records[0][$field];
            } else $this->fail('FORM_LIST_BINDING_UNSUPPORTED');
        }
        if ($resource) {
            $result = match ($resourceType) {
                'navigate', 'external' => $this->resources->resolve($button['action'], $parameters, $permission),
                'copy' => ['type' => 'copy', 'text' => $parameters['text']],
                'download' => ['type' => 'download', 'key' => 'export'],
                'refresh' => ['type' => 'refresh'],
            };
            if ($resourceType === 'copy' && (!is_scalar($result['text']) || strlen((string) $result['text']) > 10000)) $this->fail('FORM_LIST_PARAMETER_INVALID');
            if ($resourceType === 'copy') $result['text'] = (string) $result['text'];
            return ['status' => 'success', 'result' => $result];
        }
        if (strlen($this->confirmationSecret) < 32) $this->fail('FORM_LIST_CONFIRMATION_UNAVAILABLE');
        if (array_diff(array_keys($parameters), $definition['parameters']) || array_diff($definition['parameters'], array_keys($parameters))) $this->fail('FORM_LIST_PARAMETER_INVALID');
        foreach ($definition['parameterTypes'] as $name => $type) {
            $value = $parameters[$name];
            $valid = match ($type) {
                'string' => is_string($value) && strlen($value) <= 10000,
                'integer' => is_int($value),
                'number' => (is_int($value) || is_float($value)) && is_finite((float) $value),
                'boolean' => is_bool($value),
                'ids' => is_array($value) && array_is_list($value) && count($value) <= 200 && !array_filter($value, static fn ($id) => (!is_int($id) && !is_string($id)) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string) $id)),
                default => false,
            };
            if (!$valid) $this->fail('FORM_LIST_PARAMETER_INVALID');
        }
        $nonce = $request['idempotencyKey'] ?? '';
        if (!is_string($nonce) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $nonce)) $this->fail('FORM_ACTION_IDEMPOTENCY_KEY_REQUIRED');
        $digest = hash('sha256', CrudDefinition::canonicalJson([$principal, $request['schemaHash'], $location, $button['id'], $key, $ids, $parameters, $nonce, $snapshot['filter'] ?? [], $snapshot['categoryContext'] ?? []]));
        if ($definition['requiresConfirmation'] || ($button['interaction']['type'] ?? '') === 'confirm') {
            $token = $request['confirmation'] ?? '';
            if ($token === '') {
                $expires = time() + 300;
                return ['status' => 'confirmation_required', 'confirmation' => $expires . '.' . hash_hmac('sha256', $digest . ':' . $expires, $this->confirmationSecret)];
            }
            if (!is_string($token) || !preg_match('/^([0-9]{10})\.([a-f0-9]{64})$/', $token, $parts)
                || (int) $parts[1] < time() || (int) $parts[1] > time() + 300
                || !hash_equals(hash_hmac('sha256', $digest . ':' . $parts[1], $this->confirmationSecret), $parts[2])) $this->fail('FORM_LIST_CONFIRMATION_INVALID');
        }
        $scope = hash('sha256', CrudDefinition::canonicalJson([$principal, $location, $button['id'], $nonce]));
        if ($definition['effect'] === 'write') {
            $claim = $this->store->claim($scope, $digest);
            if (!$claim['claimed']) {
                if ($claim['state'] === 'success') return ['status' => 'success', 'result' => $claim['result']];
                $this->fail('FORM_LIST_RESULT_' . strtoupper($claim['state']));
            }
        }
        try {
            $result = $this->registry->invokeList($key, $parameters, $permission);
        } catch (\Throwable $exception) {
            // 处理器可能已经产生外部副作用，任何抛错均保守标记未知，禁止自动重试。
            if ($definition['effect'] === 'write') $this->store->finish($scope, $digest, 'unknown', null);
            throw $exception;
        }
        if ($definition['effect'] === 'write') $this->store->finish($scope, $digest, 'success', $result);
        return ['status' => 'success', 'result' => $result];
    }

    private function validateInput(array $fields, mixed $input): void
    {
        if (!is_array($input) || array_diff(array_keys($input), array_column($fields, 'name'))) $this->fail('FORM_LIST_INPUT_INVALID');
        foreach ($fields as $field) {
            $value = $input[$field['name']] ?? null;
            if ($value === null || $value === '') {
                if ($field['required'] ?? false) $this->fail('FORM_LIST_INPUT_INVALID');
                continue;
            }
            $valid = match ($field['type']) {
                'input', 'textarea' => is_string($value) && mb_strlen($value) <= ($field['maxLength'] ?? 10000),
                'number' => (is_int($value) || is_float($value)) && is_finite((float) $value)
                    && (!isset($field['min']) || $value >= $field['min']) && (!isset($field['max']) || $value <= $field['max']),
                'switch' => is_bool($value),
                'select' => in_array($value, array_column($field['options'] ?? [], 'value'), true),
                'date' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                    && ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value,
                default => false,
            };
            if (!$valid) $this->fail('FORM_LIST_INPUT_INVALID');
        }
    }

    private function condition(array $condition, array $record, int $depth = 0): bool
    {
        if ($depth > 8) $this->fail('FORM_LIST_CONDITION_INVALID');
        $op = $condition['op'] ?? '';
        if (in_array($op, ['and', 'or'], true)) {
            $children = $condition['conditions'] ?? [];
            if (!$children || count($children) > 20) $this->fail('FORM_LIST_CONDITION_INVALID');
            $results = array_map(fn ($child) => $this->condition($child, $record, $depth + 1), $children);
            return $op === 'and' ? !in_array(false, $results, true) : in_array(true, $results, true);
        }
        if ($op === 'not') return !$this->condition($condition['condition'], $record, $depth + 1);
        $field = $condition['field'] ?? '';
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $field) || in_array($field, ['constructor', 'prototype'], true) || !array_key_exists($field, $record)) $this->fail('FORM_LIST_FIELD_FORBIDDEN');
        $actual = $record[$field];
        $expected = $condition['value'] ?? null;
        return match ($op) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'gt', 'gte', 'lt', 'lte' => is_numeric($actual) && is_numeric($expected) && match ($op) {
                'gt' => $actual > $expected, 'gte' => $actual >= $expected, 'lt' => $actual < $expected, 'lte' => $actual <= $expected,
            },
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'notIn' => is_array($expected) && !in_array($actual, $expected, true),
            'empty' => $actual === null || $actual === '' || $actual === [],
            'notEmpty' => $actual !== null && $actual !== '' && $actual !== [],
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'startsWith' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'endsWith' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            default => $this->fail('FORM_LIST_CONDITION_INVALID'),
        };
    }

    private function fail(string $code): never { throw new InvalidArgumentException($code); }
}
