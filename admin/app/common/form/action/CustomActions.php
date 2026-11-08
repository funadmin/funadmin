<?php

declare(strict_types=1);

namespace app\common\form\action;

/**
 * 设计器声明的列表自定义动作（Schema list.customActions）与注册表定义之间的唯一换算规则。
 *
 * - 注册键：generated.{entity}.{key}，entity 为表单标识的 kebab 形式（与生成权限前缀一致）。
 * - 权限码：generated:{entity}:action-{key 的 kebab 形式}，权限行 obj/act 按 PermissionCode::resource 推导。
 * - 处理方法：{key 的 camel 形式}，位于 app\admin\generated\{dir}\action\{Class}Actions。
 */
final class CustomActions
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,40}$/D';
    public const PARAMETER_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/D';
    public const LOCATIONS = ['row', 'toolbar'];
    public const PARAMETER_TYPES = ['string', 'integer', 'number', 'boolean'];
    public const MAX_ACTIONS = 50;
    public const MAX_PARAMETERS = 5;
    public const MAX_REGISTRY_KEY = 64;
    public const CAPABILITY_VERSION = '1';

    public static function entity(string $formKey): string
    {
        return str_replace('_', '-', $formKey);
    }

    public static function registryKey(string $entity, string $key): string
    {
        return 'generated.' . $entity . '.' . $key;
    }

    public static function codeSuffix(string $key): string
    {
        return 'action-' . str_replace('_', '-', $key);
    }

    public static function permission(string $entity, string $key): string
    {
        return 'generated:' . $entity . ':' . self::codeSuffix($key);
    }

    public static function method(string $key): string
    {
        return (string) preg_replace_callback('/_+([a-z0-9])/', static fn (array $match): string => strtoupper($match[1]), $key);
    }

    /** row→record；toolbar→selection（toolbar 动作总是批量作用于已选记录）。 */
    public static function targets(array $action): array
    {
        $targets = [];
        foreach ((array) ($action['locations'] ?? []) as $location) {
            $targets[] = $location === 'row' ? 'record' : 'selection';
        }
        return array_values(array_unique($targets));
    }

    /** 与 FormActionRegistry::definitions() 同形的公开描述（不含处理器）。 */
    public static function descriptor(string $entity, array $action): array
    {
        $parameters = array_values(array_map(static fn (array $parameter): string => (string) $parameter['name'], (array) ($action['parameters'] ?? [])));
        $types = [];
        foreach ((array) ($action['parameters'] ?? []) as $parameter) {
            $types[(string) $parameter['name']] = (string) $parameter['type'];
        }
        return [
            'permission' => self::permission($entity, (string) $action['key']),
            'parameters' => $parameters,
            'capabilityVersion' => self::CAPABILITY_VERSION,
            'parameterTypes' => $types,
            'locations' => array_values((array) $action['locations']),
            'targets' => self::targets($action),
            'effect' => (string) $action['effect'],
            'batch' => ($action['batch'] ?? false) === true,
            'requiresConfirmation' => ($action['requiresConfirmation'] ?? true) === true,
            'resultContract' => 'json',
        ];
    }

    /**
     * Schema 自带的自定义动作描述，按注册键索引；未声明时为空。
     *
     * @return array<string, array>
     */
    public static function fromSchema(array $schema): array
    {
        $key = $schema['key'] ?? null;
        $actions = $schema['list']['customActions'] ?? [];
        if (!is_string($key) || $key === '' || !is_array($actions)) return [];
        $entity = self::entity($key);
        $result = [];
        foreach ($actions as $action) {
            if (!is_array($action) || !is_string($action['key'] ?? null) || !is_array($action['locations'] ?? null)) continue;
            $result[self::registryKey($entity, $action['key'])] = self::descriptor($entity, $action);
        }
        return $result;
    }
}
