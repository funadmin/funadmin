<?php

declare(strict_types=1);

namespace app\common\form\dependency;

use app\common\crud\CrudDefinition;
use app\common\form\action\FormActionRegistry;
use app\common\form\component\PluginFormComponentRegistry;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidatorRegistry;

/** 发布前验证 FormSchema 对生产能力注册表的全部依赖。 */
final class FormSchemaDependencyChecker
{
    public function __construct(
        private readonly FormActionRegistry $actions,
        private readonly FormDataSourceRegistry $dataSources,
        private readonly FormAsyncValidatorRegistry $asyncValidators,
        private readonly PluginFormComponentRegistry $pluginComponents,
        private readonly mixed $listAdapterReady = null
    ) {
    }

    /** @return array{diagnostics: array<int, array{path: string, code: string, message: string}>, dependencyHash: string} */
    public function check(array $schema, string $listHost = ''): array
    {
        $diagnostics = [];
        $dependencies = ['components' => [], 'dataSources' => [], 'validators' => [], 'actions' => []];
        $this->checkNodes((array) ($schema['nodes'] ?? []), '/nodes', $diagnostics, $dependencies);
        foreach ((array) ($schema['dataSources'] ?? []) as $index => $definition) {
            if (is_array($definition)) {
                $this->checkDataSource($definition, '/dataSources/' . $index, $diagnostics, $dependencies);
            }
        }
        $this->checkActions((array) ($schema['actions'] ?? []), '/actions', $diagnostics, $dependencies);
        $this->checkListButtons((array) ($schema['list']['buttons'] ?? []), $diagnostics, $dependencies, $listHost);
        return [
            'diagnostics' => $diagnostics,
            'dependencyHash' => hash('sha256', CrudDefinition::canonicalJson($dependencies)),
        ];
    }

    /** 未完成宿主授权与发布绑定的能力不能借旧 request 入口发布。 */
    private function checkListButtons(array $collections, array &$diagnostics, array &$dependencies, string $host): void
    {
        foreach ($collections as $location => $buttons) {
            foreach ((array) $buttons as $index => $button) {
                if (!is_array($button) || !is_array($button['action'] ?? null)) continue;
                $action = $button['action'];
                $path = '/list/buttons/' . $this->escape((string) $location) . '/' . $index;
                $type = $action['type'] ?? '';
                if ($type === 'registered') {
                    $key = (string) ($action['key'] ?? '');
                    $definition = $this->actions->definitions()[$key] ?? null;
                    if ($definition === null) {
                        $this->diagnostic($diagnostics, $path . '/action/key', 'FORM_ACTION_NOT_REGISTERED', '列表动作未注册：' . $key);
                        continue;
                    }
                    $this->checkVersion($action, $definition, $path . '/action', 'FORM_ACTION', $diagnostics);
                    $permission = $definition['permission'] ?? '';
                    if ($permission === '' || ($button['permission'] ?? null) !== $permission) {
                        $this->diagnostic($diagnostics, $path . '/permission', 'FORM_ACTION_PERMISSION_MISMATCH', '权限字段与注册定义不一致');
                    }
                    foreach (array_keys((array) ($button['params'] ?? [])) as $parameter) {
                        if (!in_array($parameter, $definition['parameters'], true)) {
                            $this->diagnostic($diagnostics, $path . '/params/' . $this->escape((string) $parameter), 'FORM_ACTION_PARAMETER_NOT_ALLOWED', '参数未在注册定义中声明');
                        }
                    }
                    $target = match ($location) { 'row' => 'record', 'toolbar' => 'selection', 'categoryNode' => 'category', 'categoryToolbar' => 'none', default => '' };
                    $contract = isset($this->actions->listCatalog(static fn (): bool => true, (string) $location, $target)[$key])
                        && ($location !== 'toolbar' || $definition['batch'])
                        && !array_diff($definition['parameters'], array_keys((array) ($button['params'] ?? [])));
                    $ready = in_array($host, ['core-dynamic', 'core-generated'], true) && $contract && is_callable($this->listAdapterReady) && ($this->listAdapterReady)();
                    $dependencies['listActions'][$key] = $definition;
                    $dependencies['listHost'] = ['host' => $host, 'ready' => $ready, 'version' => '2'];
                    if ($ready) continue;
                }
                if (!empty($button['params']) || in_array($type, ['registered', 'navigate', 'external', 'download', 'copy'], true)) {
                    $this->diagnostic($diagnostics, $path . '/action', 'FORM_LIST_ACTION_ADAPTER_UNAVAILABLE', '该列表动作尚无完整的安全宿主适配，禁止发布');
                }
            }
        }
    }

    private function checkNodes(array $nodes, string $path, array &$diagnostics, array &$dependencies): void
    {
        foreach ($nodes as $index => $node) {
            if (!is_array($node)) {
                continue;
            }
            $nodePath = $path . '/' . $index;
            $type = (string) ($node['type'] ?? '');
            if (str_contains($type, ':')) {
                $definition = $this->pluginComponents->definition($type);
                if ($definition === null) {
                    $this->diagnostic($diagnostics, $nodePath . '/type', 'FORM_COMPONENT_NOT_ENABLED', '插件组件未启用：' . $type);
                } else {
                    $this->checkVersion($node, $definition, $nodePath, 'FORM_COMPONENT', $diagnostics);
                    $dependencies['components'][$type] = $this->version($definition);
                }
            }
            foreach ((array) ($node['validation'] ?? []) as $ruleIndex => $rule) {
                if (is_array($rule) && ($rule['type'] ?? '') === 'async') {
                    $this->checkValidator($rule, $nodePath . '/validation/' . $ruleIndex, $diagnostics, $dependencies);
                }
            }
            if (is_array($node['dataSource'] ?? null) && !isset($node['dataSource']['ref'])) {
                $this->checkDataSource($node['dataSource'], $nodePath . '/dataSource', $diagnostics, $dependencies);
            }
            foreach ((array) ($node['events'] ?? []) as $event => $actions) {
                $this->checkActions((array) $actions, $nodePath . '/events/' . $this->escape((string) $event), $diagnostics, $dependencies);
            }
            $this->checkNodes((array) ($node['children'] ?? []), $nodePath . '/children', $diagnostics, $dependencies);
        }
    }

    private function checkValidator(array $rule, string $path, array &$diagnostics, array &$dependencies): void
    {
        $validator = is_array($rule['validator'] ?? null) ? $rule['validator'] : $rule;
        $validatorPath = isset($rule['validator']) ? $path . '/validator' : $path;
        $key = (string) ($validator['key'] ?? '');
        $definition = $this->asyncValidators->definitions()[$key] ?? null;
        if ($definition === null) {
            $this->diagnostic($diagnostics, $validatorPath . '/key', 'FORM_ASYNC_VALIDATOR_NOT_REGISTERED', '异步验证器未注册：' . $key);
            return;
        }
        $this->checkVersion($validator, $definition, $validatorPath, 'FORM_ASYNC_VALIDATOR', $diagnostics);
        $dependencies['validators'][$key] = $this->version($definition);
    }

    private function checkDataSource(array $source, string $path, array &$diagnostics, array &$dependencies): void
    {
        if (($source['kind'] ?? $source['mode'] ?? '') !== 'endpoint') {
            return;
        }
        $key = (string) ($source['endpoint'] ?? '');
        $definition = $this->dataSources->definitions()[$key] ?? null;
        if ($definition === null) {
            $this->diagnostic($diagnostics, $path . '/endpoint', 'FORM_DATA_SOURCE_ENDPOINT_NOT_REGISTERED', '数据源端点未注册：' . $key);
            return;
        }
        $this->checkContract($source, $definition, $path, 'FORM_DATA_SOURCE', $diagnostics);
        $dependencies['dataSources'][$key] = $this->version($definition);
    }

    private function checkActions(array $actions, string $path, array &$diagnostics, array &$dependencies): void
    {
        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }
            if (!array_key_exists('type', $action)) {
                $this->checkActions((array) ($action['steps'] ?? []), $path . '/' . $index . '/steps', $diagnostics, $dependencies);
                continue;
            }
            if (($action['type'] ?? '') !== 'request') {
                continue;
            }
            $actionPath = $path . '/' . $index;
            $key = (string) ($action['key'] ?? '');
            $definition = $this->actions->definitions()[$key] ?? null;
            if ($definition === null) {
                $this->diagnostic($diagnostics, $actionPath . '/key', 'FORM_ACTION_NOT_REGISTERED', '请求动作未注册：' . $key);
                continue;
            }
            $this->checkContract($action, $definition, $actionPath, 'FORM_ACTION', $diagnostics);
            $dependencies['actions'][$key] = $this->version($definition);
        }
    }

    private function checkContract(array $usage, array $definition, string $path, string $prefix, array &$diagnostics): void
    {
        $permission = (string) ($definition['permission'] ?? '');
        if ($permission === '' || ($usage['permission'] ?? null) !== $permission) {
            $this->diagnostic($diagnostics, $path . '/permission', $prefix . '_PERMISSION_MISMATCH', '权限字段与注册定义不一致');
        }
        $parameters = $usage['parameters'] ?? $usage['params'] ?? [];
        $allowed = array_fill_keys((array) ($definition['parameters'] ?? []), true);
        foreach (array_keys(is_array($parameters) ? $parameters : []) as $parameter) {
            if (!isset($allowed[$parameter])) {
                $parameterKey = array_key_exists('parameters', $usage) ? 'parameters' : 'params';
                $this->diagnostic($diagnostics, $path . '/' . $parameterKey . '/' . $this->escape((string) $parameter), $prefix . '_PARAMETER_NOT_ALLOWED', '参数未在注册定义中声明：' . $parameter);
            }
        }
        $this->checkVersion($usage, $definition, $path, $prefix, $diagnostics);
    }

    private function checkVersion(array $usage, array $definition, string $path, string $prefix, array &$diagnostics): void
    {
        if ((string) ($usage['capabilityVersion'] ?? '') !== $this->version($definition)) {
            $this->diagnostic($diagnostics, $path . '/capabilityVersion', $prefix . '_VERSION_MISMATCH', 'capabilityVersion 与注册定义不一致');
        }
    }

    private function version(array $definition): string
    {
        return (string) ($definition['capabilityVersion'] ?? '1');
    }

    private function diagnostic(array &$diagnostics, string $path, string $code, string $message): void
    {
        $diagnostics[] = ['path' => $path, 'code' => $code, 'message' => $message];
    }

    private function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
