<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * 将严格 Definition 编译为确定性的生产模板上下文。
 */
final class ProductionTemplateContext
{
    public static function build(CrudDefinition $definition, array $target = []): array
    {
        self::$i18nKeys = [];
        self::$backendLangKeys = [];
        $data = $definition->toArray();
        $data['_namespace'] = (string) ($target['namespace'] ?? 'app\\admin');
        $data['_controllerGroup'] = (string) ($target['controllerGroup'] ?? ltrim((string) $data['apiPrefix'], '/'));
        $data['_apiPrefix'] = (string) ($target['apiPrefix'] ?? $data['apiPrefix']);
        $data['_frontendApiImport'] = (string) ($target['frontendApiImport'] ?? "@/api/generated/{$data['entity']}");
        $data['_frontendComponentApiImport'] = (string) ($target['frontendComponentApiImport'] ?? $data['_frontendApiImport']);
        $data['_modelBaseImport'] = (string) ($target['modelBaseImport'] ?? 'use app\\admin\\model\\BackendModel;');
        $data['_modelBaseClass'] = (string) ($target['modelBaseClass'] ?? 'BackendModel');
        $data['_consoleController'] = (bool) ($target['consoleController'] ?? true);
        $data['_listActionHost'] = ($target['type'] ?? $data['target']['type'] ?? 'core') === 'core'
            && $data['_consoleController'] && ($data['capabilities']['list'] ?? true) && !empty($data['formSchema']['key']);
        $data['_modelNamespace'] = (string) ($target['modelNamespace'] ?? $data['_namespace'] . '\\model');
        $data['_validateNamespace'] = (string) ($target['validateNamespace'] ?? $data['_namespace'] . '\\validate');
        $data['_serviceNamespace'] = (string) ($target['serviceNamespace'] ?? $data['_namespace'] . '\\service');
        $data['_controllerNamespace'] = (string) ($target['controllerNamespace'] ?? $data['_namespace'] . '\\controller');
        if (($target['type'] ?? 'core') === 'core') {
            foreach (['model', 'validate', 'service', 'controller'] as $artifact) {
                if (str_contains((string) ($data['generationTargets'][$artifact] ?? ''), "/{$artifact}/generated/")) {
                    $namespaceKey = '_' . $artifact . 'Namespace';
                    $data[$namespaceKey] .= '\\generated';
                }
            }
        }
        $class = self::studly((string) $data['entity']);
        $primary = array_values(array_filter(
            $data['fields'],
            static fn (array $field): bool => ($field['primary'] ?? false) === true
        ))[0];
        // 字段标签统一收集，列表、表单、详情、搜索与导出共用同一 key。
        foreach ($data['fields'] as $field) {
            if (!self::sensitiveField($field)) {
                self::fieldLabelKey($data, $field);
            }
        }

        $result = [
            'name' => (string) $data['entity'],
            'phpClass' => $class,
            'title' => (string) $data['title'],
            'table' => (string) $data['table'],
            'apiPrefix' => (string) $data['apiPrefix'],
            'routePrefix' => ltrim((string) $data['apiPrefix'], '/'),
            'permissionPrefix' => (string) $data['permissionPrefix'],
            'fieldsJson' => self::json($data['fields']),
            'migrationContent' => self::migration($data, $primary),
            'modelContent' => self::model($data, $class, $primary),
            'validateContent' => self::validator($data, $class, $primary),
            'serviceContent' => self::service($data, $class, $primary),
            'controllerContent' => self::controller($data, $class, $primary),
            'permissionMigrationContent' => self::permissionMigration($data),
            'apiContent' => self::api($data, $class, $primary),
            'viewContent' => self::view($data, $class, $primary),
            'formContent' => self::form($data, $class),
            'detailContent' => self::detail($data, $class),
            'phpTestContent' => self::phpTest($data, $class),
            'vitestTestContent' => self::vitestTest($data, $class),
        ];

        $backendFullKeys = [];
        foreach (self::$backendLangKeys as $purpose => $zh) {
            $backendFullKeys[$data['entity'] . '.' . $purpose] = $zh;
        }
        $allKeys = $backendFullKeys + self::$i18nKeys;
        $translations = [];
        $translator = $target['translator'] ?? null;
        if (is_callable($translator) && $allKeys !== []) {
            try {
                $translations = (array) $translator($allKeys);
            } catch (\Throwable) {
                $translations = [];
            }
        }
        $result['langMigrationContent'] = self::langMigration($data, $translations);
        $result['langZhContent'] = self::langFile((string) $data['entity'], self::$backendLangKeys);
        $backendEn = [];
        foreach (self::$backendLangKeys as $purpose => $zh) {
            $backendEn[$purpose] = self::translationFor($translations, (string) $data['entity'] . '.' . $purpose);
        }
        $result['langEnContent'] = self::langFile((string) $data['entity'], $backendEn);
        // 前端语言行结构化包：managed 生成路径据此派生语言资源确定性入库，与 lang.sql 内容一致。
        $enFrontend = [];
        $placeholders = [];
        foreach (self::$i18nKeys as $key => $zh) {
            $translated = $translations[$key] ?? null;
            if (!is_string($translated) || trim($translated) === '') {
                $placeholders[] = $key;
            }
            $enFrontend[$key] = self::translationFor($translations, $key);
        }
        $result['languagePack'] = ['zh-cn' => self::$i18nKeys, 'en-us' => $enFrontend, 'placeholders' => $placeholders];
        return $result;
    }

    private static function migration(array $data, array $primary): string
    {
        $columns = [];
        $indexes = [];
        foreach ($data['fields'] as $field) {
            $name = $field['name'];
            $null = $field['nullable'] ? 'NULL' : 'NOT NULL';
            $auto = ($field['primary'] ?? false) && str_contains(strtolower($field['dbType']), 'int') ? ' AUTO_INCREMENT' : '';
            $default = array_key_exists('default', $field) && $field['default'] !== null
                ? ' DEFAULT ' . self::sqlLiteral($field['default']) : '';
            $columns[] = "  `{$name}` {$field['dbType']} {$null}{$default}{$auto}";
            if (($field['unique'] ?? false) === true) {
                $indexes[] = "  UNIQUE KEY `uk_{$data['table']}_{$name}` (`{$name}`)";
            } elseif (($field['search'] ?? false) || ($field['relation'] ?? '') !== '') {
                $indexes[] = "  KEY `idx_{$data['table']}_{$name}` (`{$name}`)";
            }
        }
        $names = array_column($data['fields'], 'name');
        if ($data['softDeletes'] && !in_array('deleted_at', $names, true)) {
            $columns[] = '  `deleted_at` datetime NULL';
        }
        $lines = array_merge($columns, ["  PRIMARY KEY (`{$primary['name']}`)"], $indexes);
        return "-- funadmin-physical-table\n-- Generated forward migration; review before applying.\n"
            . "CREATE TABLE IF NOT EXISTS `{$data['table']}` (\n"
            . implode(",\n", $lines)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT=" . self::sqlLiteral($data['title']) . ";\n";
    }

    private static function model(array $data, string $class, array $primary): string
    {
        $casts = [];
        foreach ($data['fields'] as $field) {
            $cast = $field['cast'] ?? self::castFor($field);
            if ($cast !== null) {
                $casts[$field['name']] = $cast;
            }
        }
        $methods = [];
        $pivotModels = [];
        foreach ($data['relations'] as $relation) {
            $pivotClass = $class . self::studly($relation['name']) . 'Pivot';
            if ($relation['type'] === 'belongsToMany') {
                // ORM 的字符串 middle 按逻辑表处理；使用显式物理表模型避免二次前缀。
                $pivotModels[] = "\nfinal class {$pivotClass} extends \\think\\model\\Pivot\n{\n"
                    . '    protected string $table = ' . var_export($relation['pivotTable'], true) . ";\n"
                    . '    protected $connection = ' . var_export((string) ($data['connection'] ?? 'mysql'), true) . ";\n}\n";
            }
            $target = '\\' . $data['_modelNamespace'] . '\\' . $relation['target'] . '::class';
            $arguments = match ($relation['type']) {
                'belongsTo', 'hasOne', 'hasMany' => "$target, '{$relation['field']}', '{$relation['targetField']}'",
                default => "$target, {$pivotClass}::class, '"
                    . ($relation['pivotTargetKey'] ?? $relation['targetField']) . "', '"
                    . ($relation['pivotLocalKey'] ?? $relation['field']) . "'",
            };
            $methods[] = "    public function {$relation['name']}()\n    {\n"
                . "        return \$this->{$relation['type']}({$arguments});\n    }";
        }
        $softImport = $data['softDeletes']
            ? "use app\\common\\model\\concern\\LaravelSoftDelete;\n" : '';
        $softTrait = $data['softDeletes'] ? "    use LaravelSoftDelete;\n\n" : '';
        if (!empty($data['formSchema']['key'])) {
            $key = $data['formSchema']['key'];
            $connection = var_export((string) ($data['connection'] ?? 'mysql'), true);
            if ($data['softDeletes']) $softTrait = "    use LaravelSoftDelete { delete as private treeDelete; restore as private treeRestore; }\n\n";
            $delete = $data['softDeletes'] ? '$this->treeDelete()' : 'parent::delete()';
            $methods[] = "    public function save(array|object \$data = [], \$where = [], bool \$refresh = false): bool\n    {\n        return \\think\\facade\\Db::connect({$connection})->transaction(function () use (\$data, \$where, \$refresh): bool {\n            \$payload = array_replace(\$this->getData(), (array) \$data);\n            (new \\app\\admin\\form\\service\\FormDataService())->guardTreeWrite('{$key}', (string) (\$payload['{$primary['name']}'] ?? ''), \$payload);\n            return parent::save(\$data, \$where, \$refresh);\n        });\n    }";
            $methods[] = "    public function delete(): bool\n    {\n        return \\think\\facade\\Db::connect({$connection})->transaction(function (): bool {\n            (new \\app\\admin\\form\\service\\FormDataService())->guardTreeWrite('{$key}', (string) \$this->getAttr('{$primary['name']}'), [], true);\n            return {$delete};\n        });\n    }";
            if ($data['softDeletes']) $methods[] = "    public function restore(array \$where = []): bool\n    {\n        return \\think\\facade\\Db::connect({$connection})->transaction(function () use (\$where): bool {\n            (new \\app\\admin\\form\\service\\FormDataService())->guardTreeWrite('{$key}', (string) \$this->getAttr('{$primary['name']}'), \$this->getData());\n            return \$this->treeRestore(\$where);\n        });\n    }";
        }
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$data['_modelNamespace']};\n\n"
            . ($data['_modelBaseImport'] === '' ? '' : $data['_modelBaseImport'] . "\n")
            . $softImport
            . "\nfinal class {$class} extends {$data['_modelBaseClass']}\n{\n{$softTrait}"
            . '    protected string $table = ' . var_export((string) $data['table'], true) . ";\n"
            . '    protected $connection = ' . var_export((string) ($data['connection'] ?? 'mysql'), true) . ";\n"
            . "    protected string \$pk = '{$primary['name']}';\n"
            . '    protected array $type = ' . self::phpArray($casts) . ";\n\n"
            . implode("\n\n", $methods) . "\n}\n" . implode('', $pivotModels);
    }

    private static function validator(array $data, string $class, array $primary): string
    {
        $rules = [];
        foreach ($data['fields'] as $field) {
            if (($field['primary'] ?? false) || ($field['writable'] ?? true) === false) {
                continue;
            }
            $parts = [];
            if (($field['required'] ?? !$field['nullable']) === true) $parts[] = 'require';
            if (isset($field['minLength'])) $parts[] = 'min:' . $field['minLength'];
            if (isset($field['maxLength'])) $parts[] = 'max:' . $field['maxLength'];
            if (isset($field['min'])) $parts[] = 'egt:' . $field['min'];
            if (isset($field['max'])) $parts[] = 'elt:' . $field['max'];
            if (isset($field['enum'])) $parts[] = 'in:' . implode(',', $field['enum']);
            if (isset($field['format'])) {
                $parts[] = match ($field['format']) {
                    'email' => 'email', 'url' => 'url', 'date', 'datetime' => 'date', 'ip' => 'ip',
                    default => 'regex:/^[0-9a-f-]+$/i',
                };
            }
            if (($field['unique'] ?? false) === true) {
                $parts[] = "unique:\\{$data['_modelNamespace']}\\{$class},{$field['name']},{{$primary['name']}},{$primary['name']}";
            }
            $compiled = array_values(array_filter(array_merge($parts, $field['rules'] ?? []), static fn (string $rule): bool => $rule !== ''));
            if ($compiled !== []) {
                $rules[$field['name']] = implode('|', $compiled);
            }
        }
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$data['_validateNamespace']};\n\nuse think\\Validate;\n\n"
            . "final class {$class}Validate extends Validate\n{\n"
            . '    protected $rule = ' . self::phpArray($rules) . ";\n\n"
            . "    public function forUpdate(int|string \$id, array \$data = []): self\n    {\n"
            . '        foreach (' . self::phpArray(array_values(array_map(static fn (array $field): string => $field['name'], array_filter($data['fields'], static fn (array $field): bool => self::sensitiveField($field))))) . " as \$field) {\n            if (!array_key_exists(\$field, \$data)) unset(\$this->rule[\$field]);\n        }\n"
            . "        foreach (\$this->rule as &\$rule) {\n"
            . "            \$rule = str_replace('{{$primary['name']}}', (string) \$id, \$rule);\n"
            . "        }\n        return \$this;\n    }\n}\n";
    }

    private static function service(array $data, string $class, array $primary): string
    {
        $writable = array_values(array_map(
            static fn (array $field): string => $field['name'],
            array_filter($data['fields'], static fn (array $field): bool => !($field['primary'] ?? false) && ($field['writable'] ?? true))
        ));
        $with = array_values(array_map(
            static fn (array $relation): string => $relation['name'],
            array_filter($data['relations'], static fn (array $relation): bool => ($relation['with'] ?? false) === true)
        ));
        $scope = $data['dataScope']['enabled']
            ? "        if (\$departmentIds !== null) \$query->whereIn('{$data['dataScope']['field']}', \$departmentIds ?: [0]);\n" : '';
        $uuidPrimary = self::primaryKeyType($primary) === 'uuid';
        $uuidImport = $uuidPrimary ? "use Ramsey\\Uuid\\Uuid;\n" : '';
        $prepareCreateMethod = $uuidPrimary
            ? "    public function prepareCreatePayload(array \$payload): array\n    {\n        \$payload['{$primary['name']}'] = Uuid::uuid4()->toString();\n        return \$payload;\n    }\n\n"
            : "    public function prepareCreatePayload(array \$payload): array\n    {\n        return \$payload;\n    }\n\n";
        $enabled = self::enabledCapabilities($data);
        $optionArms = [];
        $relationOptionMethods = [];
        $usesDictionary = false;
        foreach (self::enabledOptionSources($data, $enabled) as $source) {
            if ($source['type'] === 'dictionary') {
                $usesDictionary = true;
                $optionArms[] = "            '{$source['name']}' => \$this->dictionaryOptions('{$source['dictionary']}'),";
                continue;
            }
            if ($source['type'] === 'relation') {
                $relation = array_values(array_filter(
                    $data['relations'],
                    static fn (array $item): bool => ($item['optionsSource'] ?? '') === $source['name']
                ))[0] ?? null;
                if ($relation !== null) {
                    $localField = array_values(array_filter(
                        $data['fields'],
                        static fn (array $field): bool => $field['name'] === $relation['field']
                    ))[0] ?? [];
                    $relationModel = '\\' . $data['_modelNamespace'] . '\\' . $relation['target'];
                    $value = preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', strtolower((string) ($localField['dbType'] ?? '')))
                        ? "(int) \$row['{$source['valueField']}']"
                        : "\$row['{$source['valueField']}']";
                    if ($data['dataScope']['enabled'] && $relation['field'] === $data['dataScope']['field']) {
                        $method = self::camel($source['name']);
                        $optionArms[] = "            '{$source['name']}' => \$this->{$method}(\$departmentIds),";
                        $relationOptionMethods[] = "\n    private function {$method}(?array \$departmentIds): array\n    {\n        \$query = {$relationModel}::order('{$source['valueField']}', 'asc')->field('{$source['valueField']},{$source['labelField']}');\n        if (\$departmentIds !== null) \$query->whereIn('{$source['valueField']}', \$departmentIds ?: [0]);\n        return array_map(static fn (array \$row): array => ['label' => (string) \$row['{$source['labelField']}'], 'value' => {$value}], \$query->select()->toArray());\n    }\n";
                    } else {
                        $optionArms[] = "            '{$source['name']}' => array_map(static fn (array \$row): array => ['label' => (string) \$row['{$source['labelField']}'], 'value' => {$value}], {$relationModel}::order('{$source['valueField']}', 'asc')->field('{$source['valueField']},{$source['labelField']}')->select()->toArray()),";
                    }
                }
            }
        }
        $dictionaryMethod = $usesDictionary
            ? "\n    private function dictionaryOptions(string \$code): array\n    {\n        \$type = \\app\\common\\model\\DictType::where('code', \$code)->where('status', 1)->find();\n        if (!\$type) return [];\n        return \\app\\common\\model\\DictItem::where('type_id', \$type->id)->where('status', 1)->order('sort_order', 'asc')->field('value,label')->select()->toArray();\n    }\n"
            : '';
        if ($optionArms !== []) {
            self::collectBackend('optionsParamsUndeclared', 'optionsSource 参数未声明');
            self::collectBackend('optionsKeywordInvalid', 'keyword 必须为字符串');
            self::collectBackend('optionsPageInvalid', '分页参数不合法');
            self::collectBackend('optionsSourceUnknown', '未知 optionsSource');
        }
        $optionsMethod = $optionArms === [] ? '' : "\n    public function options(string \$source, ?array \$departmentIds = null, array \$params = []): array\n    {\n        if (array_diff(array_keys(\$params), ['keyword', 'page', 'pageSize']) !== []) throw new \\InvalidArgumentException(lang('{$data['entity']}.optionsParamsUndeclared'));\n        if (isset(\$params['keyword']) && !is_string(\$params['keyword'])) throw new \\InvalidArgumentException(lang('{$data['entity']}.optionsKeywordInvalid'));\n        foreach (['page', 'pageSize'] as \$name) {\n            if (isset(\$params[\$name]) && filter_var(\$params[\$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) throw new \\InvalidArgumentException(lang('{$data['entity']}.optionsPageInvalid'));\n        }\n        \$options = match (\$source) {\n"
            . implode("\n", $optionArms)
            . "\n            default => throw new \\InvalidArgumentException(lang('{$data['entity']}.optionsSourceUnknown')),\n        };\n        \$keyword = \$params['keyword'] ?? '';\n        if (\$keyword !== '') \$options = array_values(array_filter(\$options, static fn (array \$item): bool => str_contains((string) \$item['label'], \$keyword)));\n        if (isset(\$params['page']) || isset(\$params['pageSize'])) {\n            \$size = min(200, (int) (\$params['pageSize'] ?? 20));\n            \$options = array_slice(\$options, ((int) (\$params['page'] ?? 1) - 1) * \$size, \$size);\n        }\n        return \$options;\n    }\n"
            . implode('', $relationOptionMethods)
            . $dictionaryMethod;
        $referenceProtection = ($data['features']['referenceProtection'] ?? false) === true;
        if ($referenceProtection) {
            self::collectBackend('referenceMetadataInvalid', '数据库引用元数据包含非法标识符');
            self::collectBackend('referenced', '记录仍被 {:target} 引用，无法删除');
        }
        $referenceMethod = $referenceProtection
            ? "    public function assertNotReferenced(iterable \$models, bool \$force): ?string\n    {\n        \$ids = [];\n        foreach (\$models as \$model) {\n            \$ids[] = \$model->{$primary['name']};\n        }\n        if (\$ids === []) return null;\n        \$references = Db::query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', ['{$data['table']}', '{$primary['name']}']);\n        foreach (\$references as \$reference) {\n            \$table = (string) (\$reference['TABLE_NAME'] ?? \$reference['table_name'] ?? '');\n            \$column = (string) (\$reference['COLUMN_NAME'] ?? \$reference['column_name'] ?? '');\n            if (!preg_match('/^[a-z_][a-z0-9_]*$/', \$table) || !preg_match('/^[a-z_][a-z0-9_]*$/', \$column)) {\n                throw new \\RuntimeException(lang('{$data['entity']}.referenceMetadataInvalid'));\n            }\n            if (Db::table(\$table)->whereIn(\$column, \$ids)->limit(1)->count() > 0) {\n                return lang('{$data['entity']}.referenced', ['target' => \$table . '.' . \$column]);\n            }\n        }\n        return null;\n    }\n"
            : "    public function assertNotReferenced(iterable \$models, bool \$force): ?string\n    {\n        return null;\n    }\n";
        $querySource = $data['softDeletes']
            ? "        \$query = \$recycled ? {$class}::onlyTrashed()->with(self::WITH_RELATIONS) : {$class}::with(self::WITH_RELATIONS);\n"
            : "        \$query = {$class}::with(self::WITH_RELATIONS);\n";
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$data['_serviceNamespace']};\n\n"
            . "use {$data['_modelNamespace']}\\{$class};\n{$uuidImport}use think\\facade\\Db;\n\n"
            . "final class {$class}Service\n{\n"
            . '    public const WRITABLE_FIELDS = ' . self::phpArray($writable) . ";\n"
            . '    public const WITH_RELATIONS = ' . self::phpArray($with) . ";\n\n"
            . $prepareCreateMethod
            . "    public function query(?array \$departmentIds = null, bool \$recycled = false)\n    {\n"
            . $querySource
            . "{$scope}        return \$query;\n    }\n\n"
            . "    public function save(?{$class} \$model, array \$payload, callable \$relations): {$class}\n    {\n"
            . "        return Db::transaction(function () use (\$model, \$payload, \$relations): {$class} {\n"
            . "            \$model ??= new {$class}();\n"
            . "            \$model->save(array_intersect_key(\$payload, array_flip(self::WRITABLE_FIELDS)));\n"
            . "            \$relations(\$model, \$payload);\n            return \$model;\n        });\n    }\n\n"
            . $referenceMethod
            . $optionsMethod . "}\n";
    }

    private static function controller(array $data, string $class, array $primary): string
    {
        $search = $exact = $range = $operators = $sort = [];
        foreach ($data['fields'] as $field) {
            if (($field['search'] ?? false) === true && ($field['component'] ?? '') !== 'hidden' && !self::sensitiveField($field)) {
                $operator = $field['searchOperator'] ?? 'eq';
                $parameter = self::camel($field['name']) . (in_array($operator, ['range', 'date'], true) ? 'Range' : '');
                if ($operator === 'like') $search[$parameter] = $field['name'];
                elseif (in_array($operator, ['range', 'date'], true)) $range[$parameter] = $field['name'];
                elseif ($operator === 'eq') $exact[$parameter] = $field['name'];
                else $operators[$parameter] = ['field' => $field['name'], 'operator' => $operator];
            }
            if (($field['sortable'] ?? false) === true && !self::sensitiveField($field)) $sort[self::camel($field['name'])] = $field['name'];
        }
        if (($data['list']['category']['enabled'] ?? false) === true) $exact['__category'] = $data['list']['category']['field'];
        $dto = [];
        foreach ($data['fields'] as $field) {
            if (self::sensitiveField($field)) continue;
            $dto[] = "            '" . self::camel($field['name']) . "' => "
                . self::dtoValue($field, "\$model->{$field['name']}") . ',';
        }
        foreach ($data['relations'] as $relation) {
            // 同名字段优先由 ORM 返回标量，不能作为关联对象序列化。
            if (in_array($relation['name'], array_column($data['fields'], 'name'), true)) continue;
            $dto[] = "            '{$relation['name']}' => \$model->{$relation['name']}?->toArray(),";
        }
        $features = $data['features'];
        $enabled = self::enabledCapabilities($data);
        if (!$data['_consoleController']) {
            // both 的管理能力只用于 console，会员应用只生成列表和详情。
            foreach ($enabled as $ability => $value) {
                if (!in_array($ability, ['list', 'search', 'detail'], true)) $enabled[$ability] = false;
            }
            $data['dataScope']['enabled'] = false;
        }
        $leftTree = ($data['list']['leftTree']['enabled'] ?? false) === true;
        $leftMethods = '';
        $leftAlias = '';
        if ($leftTree && $data['_consoleController']) {
            $key = $data['formSchema']['key'] ?? str_replace('-', '_', $data['entity']);
            $hash = $data['formSchemaHash'] ?? '';
            $field = $data['list']['leftTree']['mapping']['targetField'];
            $leftAlias = "        applyFilters as private crudOriginalFilters;\n";
            $leftMethods = "\n    private function treeService(): \\app\\admin\\form\\service\\FormDataService\n    {\n        return new \\app\\admin\\form\\service\\FormDataService(new \\app\\common\\form\\validation\\FormAsyncValidatorRegistry((array) config('form.validators', [])), \\app\\common\\form\\dataSource\\FormDataSourceRegistry::core((array) config('form.data_sources', [])), permissionChecker: fn (string \$route): bool => (new \\app\\admin\\authorization\\service\\AdminAuthorizationService())->nodeAccess(\$route));\n    }\n"
                . "    #[Get('left-tree')]\n    public function leftTree(): Response { return \$this->ok(data: \$this->treeService()->leftTree('{$key}')); }\n"
                . "    #[Get('left-tree-form/:operation')]\n    #[Pattern('operation', 'create|addChild|edit')]\n    public function leftTreeForm(string \$operation): Response { return \$this->ok(data: \$this->treeService()->leftTreeForm('{$key}', \$operation, (string) \$this->request->get('id', ''), '{$hash}', (string) \$this->request->get('optionField', ''), (array) \$this->request->get('context', []))); }\n"
                . "    #[Post('left-tree/:operation')]\n    #[Pattern('operation', 'create|addChild|edit|delete')]\n    public function mutateLeftTree(string \$operation): Response\n    {\n        \$payload = \$this->request->post('data', []);\n        if (!is_array(\$payload)) throw new \\InvalidArgumentException('data 必须为对象');\n        \$post = \$this->request->post();\n        \$post['data'] = '[REDACTED]';\n        \$this->request->withPost(\$post);\n        return \$this->ok(data: \$this->treeService()->mutateLeftTree('{$key}', \$operation, (string) \$this->request->post('id', ''), \$payload, '{$hash}', (string) \$this->request->post('sourceSchemaHash', '')));\n    }\n"
                . "    protected function applyFilters(\$query)\n    {\n        \$query = \$this->crudOriginalFilters(\$query);\n        \$selected = \$this->request->get('__leftTree', []);\n        if (is_string(\$selected)) \$selected = json_decode(\$selected, true, 512, JSON_THROW_ON_ERROR);\n        if (!is_array(\$selected)) throw new \\InvalidArgumentException('左树选择必须为数组');\n        \$values = \$this->treeService()->resolveLeftTreeFilter('{$key}', \$selected, '{$hash}');\n        return \$values === [] ? \$query : \$query->whereIn('{$field}', \$values);\n    }\n";
        }
        $statusTraitAlias = ($enabled['status'] || !$data['_consoleController']) ? "        status as private crudStatus; status as private;\n" : '';
        $methods = $leftMethods === '' ? [] : [$leftMethods];
        if ($data['_listActionHost']) {
            $key = $data['formSchema']['key'];
            $binding = self::phpArray(['formKey' => $key, 'schemaHash' => $data['formSchemaHash'], 'route' => 'admin/generated.' . strtolower($class) . 'controller', 'table' => $data['table'], 'connection' => $data['connection'] ?? 'mysql']);
            $methods[] = "    private function listButtonService(): \\app\\admin\\form\\service\\FormDataService\n    {\n        return new \\app\\admin\\form\\service\\FormDataService(permissionChecker: fn (string \$route): bool => (new \\app\\admin\\authorization\\service\\AdminAuthorizationService())->nodeAccess(\$route), productionBinding: {$binding});\n    }\n"
                . "    #[Get('list-actions')]\n    public function listActions(): Response\n    {\n        return \$this->listButtonResponse(fn (): array => \$this->listButtonService()->listActionCatalog('{$key}', (string) \$this->request->get('location', 'row'), \\app\\common\\form\\registry\\FormRegistryFactory::production()->actions()));\n    }\n"
                . "    #[Post('list-action')]\n    public function listAction(): Response\n    {\n        \$payload = \$this->request->post();\n        \$this->request->withPost(['buttonId' => \$payload['buttonId'] ?? '', 'input' => '[REDACTED]']);\n        return \$this->listButtonResponse(fn (): array => \$this->listButtonService()->executeListButton('{$key}', \$payload));\n    }\n"
                . "    private function listButtonResponse(callable \$operation): Response\n    {\n        try { return \$this->ok(data: \$operation()); }\n        catch (\\Throwable \$error) {\n            return \$this->listActionFailure(\$error);\n        }\n    }";
        }
        if ($enabled['list']) {
            if (($data['list']['tree']['enabled'] ?? false) === true) {
                self::collectBackend('treeLimit', '树形列表超过 1000 条，请缩小筛选范围');
                $treeLimitExpr = "lang('{$data['entity']}.treeLimit')";
                $methods[] = "    #[Get('')]\n    public function index(): Response\n    {\n        \$query = \$this->crudOrderedQuery(\$this->crudRecycled());\n        if ((clone \$query)->count() > 1000) return \$this->fail(msg: {$treeLimitExpr}, code: 422);\n        \$models = \$query->limit(1001)->select()->all();\n        if (count(\$models) > 1000) return \$this->fail(msg: {$treeLimitExpr}, code: 422);\n        return \$this->ok(data: \$this->paginationData(array_map(fn (Model \$model): array => \$this->transformData(\$model), \$models), count(\$models), 1, 1000));\n    }";
            } else {
                $methods[] = "    #[Get('')]\n    public function index(): Response { return \$this->crudIndex(); }";
            }
        }
        if ($enabled['detail']) $methods[] = "    #[Get(':id')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function detail(int|string \$id): Response { return \$this->crudDetail(\$id); }";
        if ($enabled['create']) $methods[] = "    #[Post('')]\n    public function create(): Response { return \$this->crudCreate(); }";
        if ($enabled['update']) $methods[] = "    #[Put(':id')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function update(int|string \$id): Response { return \$this->crudUpdate(\$id); }";
        if ($enabled['status']) $methods[] = "    #[Post(':id/status')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function status(int|string \$id): Response { return \$this->crudStatus(\$id); }";
        if ($enabled['serverOptions']) {
            $optionsArguments = "\$source";
            $optionsScope = '';
            if ($data['dataScope']['enabled']) {
                $optionsScope = "        \$scope = (new DataScopeService())->resolve();\n";
                $optionsArguments .= ", \$scope['all'] ? null : \$scope['departmentIds']";
            }
            if (!$data['dataScope']['enabled']) $optionsArguments .= ', null';
            $optionsArguments .= ', $this->request->get()';
            $methods[] = "    #[Get('options/:source')]\n    #[Pattern('source', '[a-z][a-z0-9_]*')]\n    public function options(string \$source): Response\n    {\n{$optionsScope}        return \$this->ok(data: (new {$class}Service())->options({$optionsArguments}));\n    }";
        }
        if ($enabled['delete']) {
            $methods[] = "    #[Delete(':id')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function remove(int|string \$id): Response { return \$this->crudRemove(\$id); }";
        }
        if ($enabled['softDelete']) {
            $methods[] = "    #[Post(':id/restore')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function restore(int|string \$id): Response { return \$this->crudRestoreOne(\$id); }";
            $methods[] = "    #[Delete(':id/destroy')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function destroy(int|string \$id): Response { return \$this->crudDestroyOne(\$id); }";
        }
        if ($enabled['batchDelete']) {
            $methods[] = "    #[Delete('')]\n    public function recycle(): Response { return \$this->crudRecycle(); }";
        }
        if ($enabled['batchSoftDelete']) {
            $methods[] = "    #[Post('restore')]\n    public function restoreMany(): Response { return \$this->crudRestoreMany(); }";
            $methods[] = "    #[Delete('destroy')]\n    public function destroyMany(): Response { return \$this->crudDestroyMany(); }";
        }
        if ($enabled['import']) $methods[] = "    #[Post('import')]\n    public function import(): Response { return \$this->crudImport(); }";
        if ($enabled['export']) $methods[] = "    #[Get('export')]\n    public function export(): Response { return \$this->crudExport(); }";
        $controllerImports = $data['_consoleController']
            ? "use app\\admin\\controller\\base\\AdminApiController;\nuse app\\admin\\middleware\\CheckAdminApiCsrf;\n"
                . "use app\\admin\\middleware\\CheckAdminApiRole;\nuse app\\admin\\middleware\\SystemLog;\n"
            : "use app\\BaseController;\nuse app\\common\\middleware\\MApi;\nuse app\\admin\\traits\\AdminCrudRequest;\nuse app\\admin\\traits\\AdminPagination;\n"
                . "use app\\common\\traits\\JsonResponse;\n";
        $controllerDeclaration = $data['_consoleController']
            ? "final class {$class}Controller extends AdminApiController"
            : "final class {$class}Controller extends BaseController";
        $controllerTraits = $data['_consoleController'] ? '' : "    use AdminCrudRequest;\n    use AdminPagination;\n    use JsonResponse;\n\n";
        $controllerMiddleware = $data['_consoleController']
            ? "    protected array \$middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];\n"
            : "    protected array \$middleware = [MApi::class];\n";
        if ($data['dataScope']['enabled']) {
            self::collectBackend('dataScopeRequired', '数据范围字段 {:field} 必填');
            self::collectBackend('dataScopeDenied', '无权写入指定数据范围');
        }
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace {$data['_controllerNamespace']};\n\n"
            . $controllerImports
            . "use {$data['_modelNamespace']}\\{$class};\nuse app\\admin\\authorization\\service\\DataScopeService;\nuse {$data['_serviceNamespace']}\\{$class}Service;\n"
            . "use {$data['_validateNamespace']}\\{$class}Validate;\nuse app\\common\\traits\\Crud;\n"
            . "use think\\annotation\\route\\Delete;\nuse think\\annotation\\route\\Get;\nuse think\\annotation\\route\\Group;\n"
            . "use think\\annotation\\route\\Pattern;\nuse think\\annotation\\route\\Post;\nuse think\\annotation\\route\\Put;\n"
            . "use think\\Model;\nuse think\\Response;\n\n#[Group('{$data['_controllerGroup']}')]\n"
            . "{$controllerDeclaration}\n{\n{$controllerTraits}    use Crud {\n{$leftAlias}        index as private crudIndex; index as private;\n        detail as private crudDetail; detail as private;\n        create as private crudCreate; create as private;\n        update as private crudUpdate; update as private;\n{$statusTraitAlias}        remove as private crudRemove; remove as private;\n        restoreOne as private crudRestoreOne; restoreOne as private;\n        destroyOne as private crudDestroyOne; destroyOne as private;\n        recycle as private crudRecycle; recycle as private;\n        restore as private crudRestoreMany; restore as private;\n        destroy as private crudDestroyMany; destroy as private;\n        import as private crudImport; import as private;\n        export as private crudExport; export as private;\n        baseQuery as private crudUnscopedBaseQuery;\n    }\n"
            . $controllerMiddleware
            . "    protected string \$model = {$class}::class;\n\n" . implode("\n\n", $methods) . "\n\n"
            . '    protected function searchFields(): array { return ' . self::phpArray($search) . "; }\n"
            . '    protected function exactFilters(): array { return ' . self::phpArray($exact) . "; }\n"
            . '    protected function rangeFilters(): array { return ' . self::phpArray($range) . "; }\n"
            . '    protected function operatorFilters(): array { return ' . self::phpArray($operators) . "; }\n"
            . '    protected function sortFields(): array { return ' . self::phpArray($sort) . "; }\n"
            . "    protected function primaryKey(): string { return '{$primary['name']}'; }\n"
            . "    protected function primaryKeyType(): string { return '" . self::primaryKeyType($primary) . "'; }\n"
            . "    protected function primaryKeyPattern(): ?string { return " . var_export(self::primaryKeyPattern($primary), true) . "; }\n"
            . "    protected function usesSoftDeletes(): bool { return " . ($data['softDeletes'] ? 'true' : 'false') . "; }\n"
            . (!$data['_consoleController']
                ? "    // 会员读取永远不包含回收站；忽略客户端 recycled 和详情的 withTrashed。\n    protected function baseQuery(bool \$onlyTrashed, bool \$withTrashed)\n    {\n        return \$this->crudUnscopedBaseQuery(false, false);\n    }\n"
                : '')
            . ($data['dataScope']['enabled']
                ? "    protected function baseQuery(bool \$onlyTrashed, bool \$withTrashed)\n    {\n        \$query = \$this->crudUnscopedBaseQuery(\$onlyTrashed, \$withTrashed);\n        \$scope = (new DataScopeService())->resolve();\n        return \$scope['all'] ? \$query : \$query->whereIn('{$data['dataScope']['field']}', \$scope['departmentIds'] ?: [0]);\n    }\n"
                : '')
            . "    protected function importFields(): array { return array_combine({$class}Service::WRITABLE_FIELDS, {$class}Service::WRITABLE_FIELDS); }\n"
            . "    protected function importPayload(array \$row): array { return (new {$class}Service())->prepareCreatePayload(\$this->mapImportRow(\$row)); }\n"
            . "    protected function exportFields(): array { return " . self::phpArray(array_values(array_map(static fn (array $field): string => self::camel($field['name']), array_filter($data['fields'], static fn (array $field): bool => ($field['detail'] ?? true) === true && !self::sensitiveField($field)))) ?: ['__no_export_fields__']) . "; }\n"
            . "    protected function importLimit(): int { return {$features['importLimit']}; }\n"
            . "    protected function exportLimit(): int { return {$features['exportLimit']}; }\n"
            . "    protected function payload(?Model \$model = null): array\n    {\n"
            . "        \$payload = array_intersect_key(\$this->request->post(), array_flip({$class}Service::WRITABLE_FIELDS));\n"
            . "        return \$model === null ? (new {$class}Service())->prepareCreatePayload(\$payload) : \$payload;\n    }\n"
            . "    protected function validatePayload(array &\$data, ?Model \$model = null): ?string\n    {\n"
            . ($data['dataScope']['enabled']
                ? "        \$scope = (new DataScopeService())->resolve();\n        if (!\$scope['all']) {\n            if (\$model === null && !array_key_exists('{$data['dataScope']['field']}', \$data)) {\n                return lang('{$data['entity']}.dataScopeRequired', ['field' => '{$data['dataScope']['field']}']);\n            }\n            \$scopeValue = \$data['{$data['dataScope']['field']}'] ?? \$model?->{$data['dataScope']['field']};\n            if (!in_array((int) \$scopeValue, array_map('intval', \$scope['departmentIds']), true)) {\n                return lang('{$data['entity']}.dataScopeDenied');\n            }\n        }\n"
                : '')
            . "        \$validate = new {$class}Validate();\n"
            . "        if (\$model !== null) \$validate->forUpdate(\$model->{$primary['name']}, \$data);\n"
            . "        return \$validate->check(\$data) ? null : \$validate->getError();\n    }\n"
            . "    protected function beforeDelete(iterable \$models, bool \$force): ?Response\n    {\n"
            . "        \$error = (new {$class}Service())->assertNotReferenced(\$models, \$force);\n"
            . "        return \$error === null ? null : \$this->fail(msg: \$error, code: 422);\n    }\n"
            . "    protected function transformData(Model \$model): array\n    {\n        return [\n"
            . implode("\n", $dto) . "\n        ];\n    }\n"
            . "    protected function resourceName(): string { return '" . addslashes($data['title']) . "'; }\n}\n";
    }

    private static function permissionMigration(array $data): string
    {
        $menu = $data['menu'];
        $permission = $data['permission'];
        $sourceName = self::sqlLiteral($data['entity']);
        $sql = "-- Generated forward permission/menu migration; review before applying.\n";
        $groupId = '0';
        if ($permission['enabled']) {
            $groupName = self::sqlLiteral($permission['groupName']);
            $sql .= "INSERT INTO `fun_permission` (pid, app_name, code, obj, act, name, resource_type, status, is_public, sort, source_type, source_name, created_at, updated_at, sort_order, deleted_at)\n"
                . "SELECT 0,'admin',NULL,'','',{$groupName},'group',1,0,0,'generated',{$sourceName},NOW(),NOW(),0,NULL\n"
                . "WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type` = 'generated' AND `source_name` = {$sourceName} AND `resource_type` = 'group');\n"
                . "SET @permission_group_id = (SELECT `id` FROM `fun_permission` WHERE `source_type` = 'generated' AND `source_name` = {$sourceName} AND `resource_type` = 'group' ORDER BY `id` LIMIT 1);\n"
                . "UPDATE `fun_permission` SET `pid`=0,`app_name`='admin',`code`=NULL,`obj`='',`act`='',`name`={$groupName},`resource_type`='group',`status`=1,`is_public`=0,`sort`=0,`sort_order`=0,`updated_at`=NOW(),`deleted_at`=NULL WHERE `id`=@permission_group_id AND `source_type`='generated' AND `source_name`={$sourceName} AND `resource_type`='group';\n";
            $groupId = '@permission_group_id';
            $controller = self::sqlLiteral('admin/generated.' . strtolower(self::studly((string) $data['entity'])) . 'controller');
            $codes = [];
            foreach ($permission['actions'] as $index => $action) {
                $code = self::sqlLiteral($data['permissionPrefix'] . ':' . $action['codeSuffix']);
                $codes[] = $code;
                $label = self::sqlLiteral($action['label']);
                $act = self::sqlLiteral($action['action']);
                $sort = ($index + 1) * 10;
                $sql .= "INSERT INTO `fun_permission` (pid, app_name, code, obj, act, name, resource_type, status, is_public, sort, source_type, source_name, created_at, updated_at, sort_order, deleted_at)\n"
                    . "SELECT @permission_group_id, 'admin', {$code}, {$controller}, {$act}, {$label}, 'route', 1, 0, {$sort}, 'generated', {$sourceName}, NOW(), NOW(), {$sort}, NULL\n"
                    . "WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type` = 'generated' AND `source_name` = {$sourceName} AND `resource_type` = 'route' AND `code` = {$code});\n"
                    . "UPDATE `fun_permission` SET `pid`=@permission_group_id,`app_name`='admin',`obj`={$controller},`act`={$act},`name`={$label},`status`=1,`sort`={$sort},`sort_order`={$sort},`updated_at`=NOW(),`deleted_at`=NULL WHERE `source_type`='generated' AND `source_name`={$sourceName} AND `resource_type`='route' AND `code`={$code};\n";
            }
            $sql .= $codes === []
                ? "UPDATE `fun_permission` SET `status`=0,`updated_at`=NOW() WHERE `source_type`='generated' AND `source_name`={$sourceName} AND `resource_type`='route';\n"
                : "UPDATE `fun_permission` SET `status`=0,`updated_at`=NOW() WHERE `source_type`='generated' AND `source_name`={$sourceName} AND `resource_type`='route' AND `code` NOT IN (" . implode(',', $codes) . ");\n";
        }
        if (!$menu['enabled']) return $sql;

        $listPermission = $permission['enabled'] ? self::listPermissionCode($data) : '';
        if ($listPermission === '') {
            throw new InvalidArgumentException('启用菜单时必须同时启用列表权限');
        }
        $listPermissionCode = self::sqlLiteral($listPermission);
        $sql .= "SET @menu_permission_id = (SELECT `id` FROM `fun_permission` WHERE `code`={$listPermissionCode} AND `resource_type` IN ('route','capability') AND `status`=1 AND `deleted_at` IS NULL ORDER BY `id` LIMIT 1);\n";
        $parent = $menu['parentSourceName'] !== ''
            ? "COALESCE((SELECT `id` FROM `fun_admin_menu` WHERE `source_type` IN ('admin_web','generated','plugin') AND `source_name` = " . self::sqlLiteral($menu['parentSourceName']) . " ORDER BY `id` LIMIT 1),0)"
            : (string) ($menu['parentId'] ?? 0);
        $href = '/' . ltrim((string) $data['routePath'], '/');
        $query = self::menuQuery($data);
        $fields = [self::sqlLiteral($menu['name']), self::sqlLiteral($href), self::sqlLiteral($query), self::sqlLiteral($menu['target']), self::sqlLiteral($menu['icon'])];
        [$name, $path, $menuQuery, $target, $icon] = $fields;
        $sort = (int) $menu['sortOrder'];
        return $sql
            . "INSERT INTO `fun_admin_menu` (pid, permission_id, app_name, name, href, query, target, icon, status, sort, source_type, source_name, created_at, updated_at, sort_order, deleted_at)\n"
            . "SELECT {$parent},@menu_permission_id,'admin',{$name},{$path},{$menuQuery},{$target},{$icon},1,{$sort},'generated',{$sourceName},NOW(),NOW(),{$sort},NULL\n"
            . "WHERE @menu_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = 'generated' AND `source_name` = {$sourceName});\n"
            . "UPDATE `fun_admin_menu` SET `pid`={$parent},`permission_id`=@menu_permission_id,`app_name`='admin',`name`={$name},`href`={$path},`query`={$menuQuery},`target`={$target},`icon`={$icon},`status`=1,`sort`={$sort},`sort_order`={$sort},`updated_at`=NOW(),`deleted_at`=NULL WHERE @menu_permission_id IS NOT NULL AND `source_type`='generated' AND `source_name`={$sourceName};\n";
    }

    /** SQL 模板与受管资源事务共享菜单元数据，避免正式生成丢失组件身份。 */
    public static function menuQuery(array $data): string
    {
        $menu = $data['menu'];
        $listPermission = $data['permission']['enabled'] ? self::listPermissionCode($data) : '';
        return 'component=generated/' . $data['entity'] . '/index&name=' . self::studly((string) $data['entity'])
            . '&type=C&formKey=' . str_replace('-', '_', (string) $data['entity'])
            . ($listPermission === '' ? '' : '&permission=' . $listPermission)
            . '&hidden=' . ($menu['hidden'] ? '1' : '0') . '&keepAlive=' . ($menu['keepAlive'] ? '1' : '0')
            . '&affix=' . ($menu['affix'] ? '1' : '0');
    }

    private static function listPermissionCode(array $data): string
    {
        foreach ($data['permission']['actions'] as $action) {
            if ($action['action'] === 'index') return $data['permissionPrefix'] . ':' . $action['codeSuffix'];
        }
        return '';
    }

    private static function api(array $data, string $class, array $primary): string
    {
        $type = self::tsTypeName($class);
        $camel = self::camel($class);
        $enabled = self::enabledCapabilities($data);
        $fields = [];
        foreach ($data['fields'] as $field) {
            $fields[] = '  ' . self::camel($field['name']) . ($field['nullable'] ? '?' : '')
                . ': ' . self::tsType($field) . ';';
        }
        $idType = self::tsType($primary) === 'number' ? 'number' : 'string';
        $primaryName = self::camel($primary['name']);
        $base = rtrim($data['_apiPrefix'], '/');
        $endpointOptions = [];
        foreach (self::enabledOptionSources($data, $enabled) as $source) {
            if ($source['type'] !== 'endpoint') {
                continue;
            }
            $endpointOptions[] = "    case '{$source['name']}': {\n"
                . "      const rows = await request.get<Array<{ {$source['labelField']}: unknown; {$source['valueField']}: string | number }>>("
                . self::json($source['endpoint']) . ", params, { signal });\n"
                . "      return rows.map(item => ({ label: String(item.{$source['labelField']} ?? ''), value: item.{$source['valueField']} }));\n"
                . "    }";
        }
        $optionsBody = $endpointOptions === []
            ? "request.get<Array<{ label: string; value: string | number }>>(`{$base}/options/\${source}`, params, { signal })"
            : "(async () => {\n  switch (source) {\n" . implode("\n", $endpointOptions)
                . "\n    default:\n      return request.get<Array<{ label: string; value: string | number }>>(`{$base}/options/\${source}`, params, { signal });\n  }\n})()";
        $methods = [];
        if (($data['list']['leftTree']['enabled'] ?? false) === true) {
            $methods[] = "  leftTree: (_key?: string) => request.get<import('@/api/formData').FormLeftTreeResult>('{$base}/left-tree')";
            $methods[] = "  leftTreeForm: (_key: string, operation: 'create' | 'addChild' | 'edit', id: string | number, schemaHash: string, optionField = '', context: Record<string, unknown> = {}) => request.get<{ meta: import('@/api/formData').FormDataMeta; row: Record<string, unknown>; options?: Array<{ label: string; value: string | number }>; total?: number }>(`{$base}/left-tree-form/\${operation}`, { id, schemaHash, optionField, context })";
            $methods[] = "  mutateLeftTree: (_key: string, operation: 'create' | 'addChild' | 'edit' | 'delete', id: string | number, data: Record<string, unknown>, schemaHash: string, sourceSchemaHash: string) => request.post(`{$base}/left-tree/\${operation}`, { id, data, schemaHash, sourceSchemaHash })";
        }
        if ($data['_listActionHost']) {
            $methods[] = "  listActions: (_key: string, location: import('@/views/form/schema/types').FormListButtonLocation, signal?: AbortSignal) => request.get<import('@/api/formData').FormListActionCatalog>('{$base}/list-actions', { location }, { signal, requestOptions: { showErrorMsg: false } })";
            $methods[] = "  listAction: (_key: string, payload: import('@/api/formData').FormListActionRequest) => request.post<import('@/api/formData').FormListActionReply>('{$base}/list-action', payload, { requestOptions: { showErrorMsg: false } })";
            $map = [];
            foreach ($data['fields'] as $field) {
                $alias = self::camel($field['name']);
                if (in_array($alias, $map, true)) throw new \InvalidArgumentException('列表字段 snake/camel 映射冲突');
                $map[$field['name']] = $alias;
            }
            $methods[] = '  listButtonAdapter: ' . self::json(['formKey' => $data['formSchema']['key'], 'schemaHash' => $data['formSchemaHash'], 'catalogPermission' => $data['permissionPrefix'] . ':list-actions', 'executePermission' => $data['permissionPrefix'] . ':list-action', 'fieldMap' => $map]) . ' as const';
        }
        if ($enabled['list']) $methods[] = "  list: (params: {$type}Query) => request.get<API.PageResult<{$type}>>('{$base}', params)";
        if ($enabled['detail']) $methods[] = "  detail: (id: {$type}Id) => request.get<{$type}>(`{$base}/\${id}`)";
        if ($enabled['create']) $methods[] = "  create: (data: {$type}Payload) => request.post<{$type}>('{$base}', data)";
        if ($enabled['update']) $methods[] = "  update: (id: {$type}Id, data: {$type}Payload) => request.put<{$type}>(`{$base}/\${id}`, data)";
        if ($enabled['delete']) {
            $methods[] = "  remove: (id: {$type}Id) => request.delete(`{$base}/\${id}`)";
        }
        if ($enabled['softDelete']) {
            $methods[] = "  restore: (id: {$type}Id) => request.post(`{$base}/\${id}/restore`)";
            $methods[] = "  forceDelete: (id: {$type}Id) => request.delete(`{$base}/\${id}/destroy`)";
        }
        if ($enabled['batchDelete']) {
            $methods[] = "  removeMany: (ids: {$type}Id[]) => request.delete('{$base}', { ids })";
        }
        if ($enabled['batchSoftDelete']) {
            $methods[] = "  restoreMany: (ids: {$type}Id[]) => request.post('{$base}/restore', { ids })";
            $methods[] = "  forceDeleteMany: (ids: {$type}Id[]) => request.delete('{$base}/destroy', { ids })";
        }
        if ($enabled['status']) $methods[] = "  status: (id: {$type}Id, status: number) => request.post(`{$base}/\${id}/status`, { status })";
        if ($enabled['options']) $methods[] = "  options: (source: string, params: Record<string, unknown> = {}, signal?: AbortSignal) => {$optionsBody}";
        if ($enabled['import']) $methods[] = "  importRows: (rows: {$type}Payload[]) => request.post('{$base}/import', { rows })";
        if ($enabled['export']) $methods[] = "  exportRows: (params: Partial<{$type}Query>) => request.get<{$type}[]>('{$base}/export', params)";
        return "import request from '@/utils/http';\n\nexport interface {$type} {\n"
            . implode("\n", $fields) . "\n}\nexport type {$type}Id = {$idType};\n"
            . "export interface {$type}Query { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: string | number | undefined }\n"
            . "export type {$type}Payload = Partial<Omit<{$type}, '{$primaryName}'>>;\n\nexport const {$camel}Api = {\n"
            . implode(",\n", $methods) . "\n};\n";
    }

    private static function view(array $data, string $class, array $primary): string
    {
        $camel = self::camel($class);
        $type = self::tsTypeName($class);
        $csvColumns = [];
        $csvLabels = [];
        foreach ($data['fields'] as $field) {
            $key = self::camel($field['name']);
            $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
            if (($field['writable'] ?? true) && !($field['primary'] ?? false) && !self::sensitiveField($field)) {
                $csvColumns[] = ['key' => $key, 'label' => $labelText];
                $csvLabels[$key] = self::tCall(self::fieldLabelKey($data, $field), $labelText);
            }
        }
        $primaryName = self::camel($primary['name']);
        $enabled = self::enabledCapabilities($data);
        $pageTitleExpr = self::tCall(self::collectFrontend($data, 'page.title', (string) $data['title']), (string) $data['title']);
        if (!$enabled['list']) {
            return "<template><PageWrapper :title=\"" . $pageTitleExpr . "\" /></template>\n"
                . "<script setup lang=\"ts\">\nimport { useI18n } from 'vue-i18n';\nconst { t } = useI18n();\n</script>\n";
        }
        $searchItems = [];
        foreach ($data['fields'] as $field) {
            if (($field['search'] ?? false) !== true || ($field['component'] ?? '') === 'hidden' || self::sensitiveField($field)) continue;
            $key = self::camel($field['name']);
            $operator = (string) ($field['searchOperator'] ?? 'eq');
            $parameter = $key . (in_array($operator, ['range', 'date'], true) ? 'Range' : '');
            $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
            $labelExpr = self::tCall(self::fieldLabelKey($data, $field), $labelText);
            if (in_array($operator, ['range', 'date'], true)) {
                $control = "<el-input v-model=\"query.{$parameter}\" :placeholder=\"" . self::tCall(self::collectFrontend($data, 'search.range', '起,止'), '起,止') . "\" clearable />";
            } else {
                $control = "<el-input v-model=\"query.{$parameter}\" :placeholder=\"t(" . self::tsString(self::collectFrontend($data, 'search.input', '请输入{label}')) . ", { label: {$labelExpr} }, { default: " . self::tsString('请输入{label}') . " })\" clearable />";
            }
            if (in_array($operator, ['eq', 'neq'], true)) {
                $control = "<el-select v-if=\"filterNodes.some(node => node.id === '{$key}' && node.dataSource?.kind && node.dataSource.kind !== 'static')\" v-model=\"query.{$parameter}\" :placeholder=\"filterOptions.placeholder('{$key}') || " . self::tCall(self::collectFrontend($data, 'search.select', '请选择'), '请选择') . "\" :loading=\"filterOptions.pending.value.{$key}\" clearable><el-option v-for=\"option in filterOptions.supplied.value.{$key} ?? []\" :key=\"String(option.value)\" :label=\"option.label\" :value=\"option.value as string | number\" /></el-select>" . str_replace('<el-input ', '<el-input v-else ', $control);
            }
            $searchItems[] = "<el-form-item :label=\"{$labelExpr}\">{$control}</el-form-item>";
        }
        $searchSlot = $enabled['search'] && ($data['list']['tools']['search'] ?? true)
            ? "      <template #search><SearchForm :model=\"query\" :loading=\"loading\" @search=\"onSearch\" @reset=\"onReset\">" . implode('', $searchItems) . "</SearchForm></template>\n"
            : '';
        $formEnabled = ($enabled['create'] || $enabled['update']) && ($data['capabilities']['form'] ?? true);
        $formComponent = $formEnabled ? "<{$class}Form :lock=\"buttonLock\" v-model=\"dialogVisible\" :row=\"current\" @success=\"refreshAfterSave\" />" : '';
        $detailComponent = $enabled['detail'] ? "<{$class}Detail v-model=\"drawerVisible\" :row=\"current\" />" : '';
        $crudBindings = ['loading', 'list', 'total', 'query', 'loadData'];
        if ($enabled['search']) array_push($crudBindings, 'onSearch', 'onReset');
        if ($enabled['batchDelete']) array_push($crudBindings, 'selection', 'onSelectionChange', 'onBatchDelete');
        if ($formEnabled || $enabled['detail']) $crudBindings[] = 'current';
        if ($formEnabled) $crudBindings[] = 'dialogVisible';
        if ($enabled['create'] && $formEnabled) $crudBindings[] = 'onAdd';
        if ($enabled['update'] && $formEnabled) $crudBindings[] = 'onEdit';
        if ($enabled['detail']) array_push($crudBindings, 'drawerVisible', 'onOpenDrawer');
        $tree = ($data['list']['tree']['enabled'] ?? false) === true;
        $category = ($data['list']['category']['enabled'] ?? false) === true && !($data['list']['leftTree']['enabled'] ?? false);
        $listImports = '';
        $listSetup = '';
        $categoryPanel = '';
        if ($tree) {
            $parentName = self::camel($data['list']['tree']['parentField']);
            $listImports .= "import { buildListTree } from '@/views/form/runtime/listPresentation';\n";
            $listSetup .= "const displayRows = computed(() => buildListTree(list.value, '{$primaryName}', '{$parentName}'));\n";
        }
        if ($category) {
            $categoryField = array_values(array_filter($data['fields'], static fn (array $field): bool => $field['name'] === $data['list']['category']['field']))[0];
            $listImports .= "import ListCategoryPanel from '@/views/form/components/ListCategoryPanel.vue';\n";
            $listSetup .= 'const categoryOptions = ref<Array<{ label: string; value: string | number }>>(' . self::json($categoryField['options'] ?? []) . ");\n";
            if (isset($categoryField['optionsSource'])) $listSetup .= "onMounted(async () => { categoryOptions.value = await {$camel}Api.options('{$categoryField['optionsSource']}'); });\n";
            $listSetup .= "function onCategory(value: string | number | undefined) { query.__category = value; query.page = 1; void loadData(); }\n";
            $categoryPanel = '<ListCategoryPanel :options="categoryOptions" :model-value="query.__category" @change="onCategory" />';
        }
        $leftTree = ($data['list']['leftTree']['enabled'] ?? false) === true;
        if ($leftTree) {
            $key = $data['formSchema']['key'] ?? str_replace('-', '_', $data['entity']);
            $hash = $data['formSchemaHash'] ?? '';
            $listImports .= "import ListSourceTree from '@/views/form/components/ListSourceTree.vue';\nimport { useUserStore } from '@/store/modules/user';\n";
            $listSetup .= "const treeUser = useUserStore();\nconst treePermission = (code: string): boolean => treeUser.permissions.some(permission => permission === '*' || permission === '*:*:*' || permission === code);\n";
            $listSetup .= 'const leftTreeConfig = ' . self::json($data['list']['leftTree']) . " as const;\n";
            $listSetup .= "const leftSelection = ref<Array<string | number>>([]);\nfunction onLeftTree(values: Array<string | number>) { leftSelection.value = values; query.__leftTree = JSON.stringify(values); query.page = 1; void loadData(); }\n";
            $permissionPrefix = htmlspecialchars($data['permissionPrefix'], ENT_QUOTES);
            $categoryPanel .= '<ListSourceTree v-if="treePermission(\'' . $permissionPrefix . ':left-tree\')" :can-read-form="treePermission(\'' . $permissionPrefix . ':left-tree-form\')" :can-mutate="treePermission(\'' . $permissionPrefix . ':left-tree-mutate\')" form-key="' . $key . '" schema-hash="' . $hash . '" :config="leftTreeConfig" :model-value="leftSelection" :api="' . $camel . 'Api" @change="onLeftTree" @mutated="loadData" />';
        }
        // 生成层只声明宿主适配；交互、锁定及类型分派由共享组件处理。
        $listImports .= "import ListButtonBar from '@/views/form/components/ListButtonBar.vue';\nimport { resolveListButtons, buildListFieldMap } from '@/views/form/schema/listButtons';\nimport { provideListButtonAdapter, listButtonAdapterAllowed, listActionKey, type ListButtonHandlers } from '@/views/form/runtime/listButtonHost';\nimport type { ListButtonContext } from '@/views/form/runtime/listButtonExecutor';\nimport type { FormListConfiguration, FormListButton } from '@/views/form/schema/types';\n";
        if (!$leftTree) $listImports .= "import { useUserStore } from '@/store/modules/user';\n";
        $listSetup .= 'const listConfig = ' . self::json($data['list'] ?: new \stdClass()) . " as FormListConfiguration;\n";
        $listSetup .= "const buttonUser = useUserStore();\nconst buttonLock = reactive({ busy: false });\nlet hostActive = true;\nonBeforeUnmount(() => { hostActive = false; });\nasync function refreshAfterSave() { if (!hostActive) return; try { await refreshButtonHost(); } catch { if (hostActive) ElMessage.warning(" . self::tCall(self::collectFrontend($data, 'message.refreshFailed', '操作已成功，但列表刷新失败，请手动刷新，不要重复提交'), '操作已成功，但列表刷新失败，请手动刷新，不要重复提交') . "); } }\n";
        if ($data['_listActionHost']) {
            $listSetup .= "const buttonAdapter = { api: {$camel}Api, declaration: {$camel}Api.listButtonAdapter };\nprovideListButtonAdapter(buttonAdapter);\nconst buttonFieldMap = buttonAdapter.declaration.fieldMap;\n";
        } else {
            $listSetup .= "const buttonAdapter = undefined;\n";
            $listSetup .= 'const buttonFieldMap = buildListFieldMap(' . self::json(array_column($data['fields'], 'name')) . ", 'camel');\n";
        }
        $listSetup .= "const buttonSelection = ref<{$type}[]>([]);\nconst buttonContextVersion = ref(0);\nconst buttonTable = ref<{ clearSelection: () => void }>();\nconst clearButtonSelection = () => { buttonSelection.value = []; buttonTable.value?.clearSelection(); buttonContextVersion.value++; };\n";
        $filterBindings = [];
        foreach ($data['fields'] as $field) {
            if (!($field['search'] ?? false) || ($field['component'] ?? '') === 'hidden' || self::sensitiveField($field)
                || !empty($field['relation'])) continue;
            $name = $field['name'];
            $alias = self::camel($name);
            if (in_array($field['searchOperator'] ?? 'eq', ['range', 'date'], true)) {
                $filterBindings[] = "...String(query.{$alias}Range ?? '').split(',').slice(0, 2).map((value, index) => [index === 0 ? '{$name}_from' : '{$name}_to', value])";
            } else {
                $filterBindings[] = "['{$name}', query.{$alias}]";
            }
        }
        $listSetup .= "const buttonFilter = () => Object.fromEntries(([" . implode(', ', $filterBindings) . "] as unknown[][]).filter((entry): entry is [string, string | number] => typeof entry[0] === 'string' && (typeof entry[1] === 'string' || typeof entry[1] === 'number') && entry[1] !== ''));\n";
        $buttonIdentity = $data['_listActionHost'] ? 'formKey: buttonAdapter.declaration.formKey, schemaHash: buttonAdapter.declaration.schemaHash' : "formKey: '', schemaHash: ''";
        $listSetup .= "const buttonContext = (location: 'row' | 'toolbar', row?: Record<string, unknown>): ListButtonContext => ({ {$buttonIdentity}, location, filter: buttonFilter(), ids: (row ? [row] : buttonSelection.value).map(record => record['{$primaryName}'] as string | number) });\n";
        $close = ($formEnabled ? 'dialogVisible.value = false; ' : '') . ($enabled['detail'] ? 'drawerVisible.value = false; ' : '');
        $listSetup .= "const closeButtonHost = () => { {$close} };\n";
        $listSetup .= "const buttonValues = (row: Record<string, unknown>) => Object.fromEntries(Object.entries(buttonFieldMap).map(([field, alias]) => [field, row[alias]]));\n";
        $listSetup .= "function resolveButtonRow(row?: Record<string, unknown>): {$type} { const current = list.value.find(item => item.{$primaryName} === row?.['{$primaryName}']); if (!current) throw new Error(" . self::tCall(self::collectFrontend($data, 'message.contextExpired', '记录上下文已失效'), '记录上下文已失效') . "); return current; }\n";
        $defaults = ['toolbar' => [], 'row' => []];
        $handlers = ['refresh: () => loadData()'];
        $append = static function (string $location, string $key, string $label, string $handler, array $presentation = []) use (&$defaults, &$handlers): void {
            $defaults[$location][] = ['id' => strtolower($key), 'label' => $label, 'action' => ['type' => 'builtin', 'key' => $key]]
                + $presentation
                + (in_array($key, ['delete', 'batchDelete', 'destroy'], true) ? ['interaction' => ['type' => 'confirm', 'message' => '确认' . $label . '？此操作可能不可恢复。']] : []);
            $handlers[] = $key . ': ' . $handler;
        };
        if ($enabled['softDelete']) $append('toolbar', 'normal', '正常列表', '() => switchMode(false)', ['color' => 'primary', 'plain' => true, 'placement' => 'inline', 'order' => 10]);
        if ($enabled['softDelete']) $append('toolbar', 'recycle', '回收站', '() => switchMode(true)', ['color' => 'warning', 'plain' => true, 'placement' => 'inline', 'order' => 20]);
        if ($enabled['create'] && $formEnabled) $append('toolbar', 'create', '新增', '() => onAdd()', ['color' => 'primary', 'plain' => true, 'icon' => 'plus', 'placement' => 'inline', 'order' => 30]);
        if ($enabled['batchDelete']) $append('toolbar', 'batchDelete', '移入回收站', "async () => { await {$camel}Api.removeMany(selectedIds()); await refreshAfterSave(); }", ['color' => 'danger', 'plain' => true, 'icon' => 'delete', 'selection' => ['min' => 1], 'placement' => 'inline', 'order' => 40]);
        if ($enabled['import']) $append('toolbar', 'import', 'CSV 导入', '() => fileInput.value?.click()', ['color' => 'success', 'plain' => true, 'icon' => 'upload', 'placement' => 'inline', 'order' => 50]);
        if ($enabled['export']) $append('toolbar', 'export', 'CSV 导出', '() => exportRows()', ['color' => 'default', 'plain' => true, 'icon' => 'download', 'placement' => 'inline', 'order' => 60]);
        if ($enabled['update'] && $formEnabled) $append('row', 'edit', '编辑', "row => onEdit(resolveButtonRow(row))");
        if ($enabled['detail']) $append('row', 'detail', '详情', "row => onOpenDrawer(resolveButtonRow(row))");
        if ($enabled['delete']) $append('row', 'delete', '删除', "row => removeRow(resolveButtonRow(row))");
        if ($enabled['softDelete']) {
            $append('row', 'restore', '恢复', "row => restoreRow(resolveButtonRow(row))");
            $append('row', 'destroy', '永久删除', "row => forceDeleteRow(resolveButtonRow(row))");
        }
        if ($enabled['batchSoftDelete']) {
            $defaults['toolbar'][] = ['id' => 'restoreselected', 'label' => '批量恢复', 'action' => ['type' => 'builtin', 'key' => 'restore']];
            $defaults['toolbar'][] = ['id' => 'destroyselected', 'label' => '批量永久删除', 'action' => ['type' => 'builtin', 'key' => 'destroy'], 'interaction' => ['type' => 'confirm', 'message' => '确认永久删除选中记录？此操作不可恢复。']];
            $handlers = array_map(static fn (string $handler): string => str_starts_with($handler, 'restore:')
                ? 'restore: row => row ? restoreRow(resolveButtonRow(row)) : restoreSelected()'
                : (str_starts_with($handler, 'destroy:') ? 'destroy: row => row ? forceDeleteRow(resolveButtonRow(row)) : forceDeleteSelected()' : $handler), $handlers);
        }
        // 按钮文案运行时翻译，JSON 默认值仅作中文兜底。
        $actionLabels = [];
        $addActionLabel = static function (string $actionKey, string $purpose, string $zh) use (&$actionLabels, $data): void {
            $actionLabels[$actionKey] = self::tCall(self::collectFrontend($data, $purpose, $zh), $zh);
        };
        if ($enabled['softDelete']) {
            $addActionLabel('normal', 'action.normalList', '正常列表');
            $addActionLabel('recycle', 'action.recycleBin', '回收站');
        }
        if ($enabled['create'] && $formEnabled) $addActionLabel('create', 'action.create', '新增');
        if ($enabled['batchDelete']) $addActionLabel('batchDelete', 'action.batchDestroy', '移入回收站');
        if ($enabled['import']) $addActionLabel('import', 'action.import', 'CSV 导入');
        if ($enabled['export']) $addActionLabel('export', 'action.export', 'CSV 导出');
        if ($enabled['update'] && $formEnabled) $addActionLabel('edit', 'action.edit', '编辑');
        if ($enabled['detail']) $addActionLabel('detail', 'action.detail', '详情');
        if ($enabled['delete']) $addActionLabel('delete', 'action.destroy', '删除');
        if ($enabled['softDelete']) {
            $addActionLabel('restore', 'action.restore', '恢复');
            $addActionLabel('destroy', 'action.forceDestroy', '永久删除');
        }
        $batchLabels = [];
        if ($enabled['batchSoftDelete']) {
            $batchLabels['restoreselected'] = self::tCall(self::collectFrontend($data, 'action.batchRestore', '批量恢复'), '批量恢复');
            $batchLabels['destroyselected'] = self::tCall(self::collectFrontend($data, 'action.batchForceDestroy', '批量永久删除'), '批量永久删除');
        }
        $withConfirmMessages = $enabled['delete'] || $enabled['batchSoftDelete'];
        if ($withConfirmMessages) {
            $destroyConfirmKey = $enabled['delete']
                ? self::collectFrontend($data, 'message.destroyConfirm', '确认{label}？此操作可能不可恢复。')
                : 'crud.' . $data['entity'] . '.message.destroyConfirm';
            $batchDestroyConfirmKey = $enabled['batchSoftDelete']
                ? self::collectFrontend($data, 'message.batchForceDestroyConfirm', '确认永久删除选中记录？此操作不可恢复。')
                : $destroyConfirmKey;
        }
        $listSetup .= 'const buttonActionLabels = computed<Record<string, string>>(() => (' . self::tsRecord($actionLabels) . "));\n";
        $listSetup .= 'const buttonBatchLabels = computed<Record<string, string>>(() => (' . self::tsRecord($batchLabels) . "));\n";
        if ($withConfirmMessages) {
            $listSetup .= "const localizeButtons = (buttons: FormListButton[]): FormListButton[] => buttons.map(button => { const key = listActionKey(button); const label = buttonBatchLabels.value[button.id] ?? buttonActionLabels.value[key] ?? button.label; let interaction = button.interaction; if (interaction?.type === 'confirm') { interaction = { ...interaction, message: button.id === 'destroyselected' ? "
                . self::tCall($batchDestroyConfirmKey, '确认永久删除选中记录？此操作不可恢复。')
                . " : t(" . self::tsString($destroyConfirmKey) . ", { label }, { default: " . self::tsString('确认{label}？此操作可能不可恢复。') . " }) }; } return { ...button, label, interaction }; });\n";
        } else {
            $listSetup .= "const localizeButtons = (buttons: FormListButton[]): FormListButton[] => buttons.map(button => { const key = listActionKey(button); const label = buttonBatchLabels.value[button.id] ?? buttonActionLabels.value[key] ?? button.label; return { ...button, label }; });\n";
        }
        $listSetup .= "const buttonHandlers: ListButtonHandlers = { " . implode(', ', $handlers) . " }\n";
        $listSetup .= "const buttonPermission = (code: string) => buttonUser.permissions.some(value => value === '*' || value === '*:*:*' || value === code);\n";
        $listSetup .= "const toolbarButtonAllowed = (button: FormListButton) => buttonAllowed(button, true) && (!['restore', 'destroy'].includes(listActionKey(button)) || buttonSelection.value.length > 0);\n";
        $prefix = self::json($data['permissionPrefix']);
        $recycledValue = $enabled['softDelete'] ? 'recycled.value' : 'recycled';
        $batchRestoreGuard = $enabled['batchSoftDelete'] ? '' : "if (toolbar && ['restore', 'destroy'].includes(key)) return false;";
        $listSetup .= "const buttonAllowed = (button: FormListButton, toolbar = false) => { const key = listActionKey(button); const suffix: Record<string, string> = { edit: 'update', refresh: 'list', recycle: 'list', normal: 'list', batchDelete: 'batch-delete', destroy: 'destroy' }; if (button.permission && !buttonPermission(button.permission)) return false; if (['registered', 'navigate', 'external', 'copy', 'download'].includes(button.action.type)) return !{$recycledValue} && listButtonAdapterAllowed(buttonAdapter, buttonPermission, buttonContext('toolbar')); {$batchRestoreGuard} const permissionSuffix = toolbar && ['restore', 'destroy'].includes(key) ? 'batch-' + key : (suffix[key] ?? key); if (!buttonPermission({$prefix} + ':' + permissionSuffix)) return false; if (['restore', 'destroy'].includes(key)) return {$recycledValue}; if (['create', 'edit', 'detail', 'delete', 'batchDelete'].includes(key)) return !{$recycledValue}; return true; };\n";
        foreach ($defaults as $location => $buttons) {
            $presentation = $location === 'toolbar'
                ? ".map(button => { const key = listActionKey(button); if (key === 'batchDelete') return { ...button, selection: { ...button.selection, min: Math.max(1, button.selection?.min ?? 0) } }; if (['normal', 'recycle'].includes(key)) return { ...button, color: (key === 'normal' ? (!{$recycledValue} ? 'primary' : 'info') : ({$recycledValue} ? 'warning' : 'info')) as FormListButton['color'] }; return button; })"
                : '';
            $listSetup .= "const {$location}Buttons = computed(() => localizeButtons(resolveListButtons(listConfig, '{$location}', " . self::json($buttons) . " as FormListButton[]){$presentation}));\n";
        }
        $listSetup .= "const hasRowButtons = computed(() => rowButtons.value.some(button => !button.hidden && buttonAllowed(button)));\n";
        $toolbar = ['<ListButtonBar :buttons="toolbarButtons" :handlers="buttonHandlers" :allowed="toolbarButtonAllowed" :lock="buttonLock" :refresh="refreshButtonHost" :context="buttonContext(\'toolbar\')" :context-version="buttonContextVersion" :permission-check="buttonPermission" :clear-selection="clearButtonSelection" :close="closeButtonHost" />'];
        if ($enabled['import']) $toolbar[] = '<input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" />';
        if ($leftTree) $categoryPanel = str_replace(':config="leftTreeConfig"', ':config="leftTreeConfig" :list="listConfig" :permission-check="buttonPermission" :lock="buttonLock"', $categoryPanel);
        $listSetup .= "const refreshButtonHost = async () => { clearButtonSelection(); await loadData(); };\nwatch(query, clearButtonSelection, { deep: true, flush: 'sync' });\nwatch(list, clearButtonSelection);\n";
        $listSetup .= "const handleSelectionChange = (rows: {$type}[]) => { buttonSelection.value = rows; buttonContextVersion.value++; " . ($enabled['batchDelete'] ? 'onSelectionChange(rows);' : '') . " };\n";
        $vueImports = ['computed', 'onBeforeUnmount', 'ref', 'reactive', 'watch'];
        if ($enabled['batchDelete']) {
            $listSetup .= "watch(query, () => onSelectionChange([]), { deep: true, flush: 'sync' });\n";
        }
        if ($category) $vueImports[] = 'onMounted';
        $vueImport = $vueImports === [] ? '' : "import { " . implode(', ', $vueImports) . " } from 'vue';\n";
        $csvImport = $enabled['import'] || $enabled['export']
            ? "import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';\n"
            : '';
        $pageColumns = [];
        $columnLabels = [];
        $cellSlots = '';
        foreach ($data['fields'] as $field) {
            if (!($field['list'] ?? false) || self::sensitiveField($field)) continue;
            $key = self::camel($field['name']);
            $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
            $column = ['key' => $key, 'prop' => $key, 'slot' => $key, 'label' => $labelText];
            $columnLabels[$key] = self::tCall(self::fieldLabelKey($data, $field), $labelText);
            if (($field['listWidth'] ?? 0) > 0) $column['width'] = (int) $field['listWidth'];
            if ($field['sortable'] ?? false) $column['sortable'] = true;
            $pageColumns[] = $column;
            $cellSlots .= str_replace('#default="scope"', '#' . $key . '="scope"', self::listCell($key, (string) ($field['listFormatter'] ?? '')));
        }
        if ($enabled['status']) {
            $columnLabels['statusAction'] = self::tCall(self::collectFrontend($data, 'status.column', '状态操作'), '状态操作');
            $pageColumns[] = ['key' => 'statusAction', 'label' => '状态操作', 'slot' => 'statusAction'];
            $cellSlots .= '<template #statusAction="scope"><el-switch :model-value="Number(scope.row.status) === 1" :disabled="recycled || buttonLock.busy" @change="value => changeStatus(resolveButtonRow(scope.row), value === true)" /></template>';
        }
        $pageList = ['tools' => (object) ($data['list']['tools'] ?? [])];
        if ($tree) $pageList['tree'] = ['enabled' => true, 'parentField' => self::camel($data['list']['tree']['parentField'])];
        $page = ['pageSchemaVersion' => 1, 'key' => str_replace('-', '_', $data['entity']), 'primaryKey' => $primaryName, 'search' => [], 'toolbar' => [], 'rowActions' => [], 'list' => $pageList, 'pagination' => ['pageSize' => 20, 'pageSizes' => [10, 20, 50, 100], 'enabled' => !$tree]];
        $listImports .= "import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';\nimport type { PageSchema } from '@/components/DataTable/pageSchema';\nimport { formatFieldValue, resolveFieldOptions } from '@/views/form/runtime/fieldPresentation';
import { useSuppliedFieldOptions } from '@/views/form/runtime/fieldPresentation';\n";
        $actionsColumnLabel = self::tCall(self::collectFrontend($data, 'action.column', '操作'), '操作');
        $listSetup .= 'const columnLabels = computed<Record<string, string>>(() => (' . self::tsRecord($columnLabels) . "));\n";
        $listSetup .= 'const tableSchema = computed<PageSchema>(() => ({ ...' . self::json($page) . ', columns: [...(toolbarButtons.value.some(button => button.action.type === \'registered\')' . ($enabled['batchDelete'] ? ' || true' : '') . ' ? [{ key: \'selection\', label: \'\', type: \'selection\' as const, width: 48 }] : []), ...' . self::json($pageColumns) . '.map(column => ({ ...column, label: columnLabels.value[column.key] ?? column.label })), ...(hasRowButtons.value ? [{ key: \'actions\', label: ' . $actionsColumnLabel . ', slot: \'actions\' }] : [])] } as PageSchema));' . "\n";
        $actionSlot = '<template #actions="scope"><ListButtonBar :buttons="rowButtons" :handlers="buttonHandlers" :allowed="buttonAllowed" :row="scope.row" :values="buttonValues(scope.row)" :fields="Object.keys(buttonFieldMap)" :lock="buttonLock" :refresh="refreshButtonHost" :context="buttonContext(\'row\', scope.row)" :context-version="buttonContextVersion" :permission-check="buttonPermission" :clear-selection="clearButtonSelection" :close="closeButtonHost" link /></template>';
        return "<template>\n  <PageWrapper :title=\"" . $pageTitleExpr . "\">\n"
            . (($category || $leftTree) ? '<div class="flex flex-col gap-4 md:flex-row">' . $categoryPanel : '')
            . "<SchemaTablePage ref=\"buttonTable\" class=\"min-w-0 flex-1\" storage-key=\"generated-{$data['entity']}\" :schema=\"tableSchema\" :query=\"query\" :rows=\"list\" :total=\"total\" :loading=\"loading\" :lock=\"buttonLock\" :context=\"{ values: {}, permissions: buttonUser.permissions, handlers: {} }\" @refresh=\"refreshButtonHost\" @sort-change=\"({ prop, order }) => { query.sort = prop ?? ''; query.order = order === 'descending' ? 'desc' : 'asc'; loadData(); }\" @selection-change=\"handleSelectionChange\">"
            . $searchSlot . '<template #toolbar>' . implode('', $toolbar) . '</template>' . $cellSlots . $actionSlot
            . '</SchemaTablePage>' . (($category || $leftTree) ? '</div>' : '') . "{$formComponent}{$detailComponent}\n"
            . "  </PageWrapper>\n</template>\n<script setup lang=\"ts\">\n" . $vueImport
            . "import { ElMessage } from 'element-plus';\n"
            . "import { useI18n } from 'vue-i18n';\n"
            . "import { useCrud } from '@/composables/useCrud';\n" . $csvImport . $listImports
            . "import { {$camel}Api, type {$type}, type {$type}Payload, type {$type}Query } from '{$data['_frontendApiImport']}';\n"
            . ($formEnabled ? "import {$class}Form from './components/{$class}Form.vue';\n" : '')
            . ($enabled['detail'] ? "import {$class}Detail from './components/{$class}Detail.vue';\n" : '')
            . 'const { ' . implode(', ', $crudBindings) . " } = useCrud<{$type}, {$type}Query, {$type}['{$primaryName}']>({ api: { list: {$camel}Api.list"
            . ($enabled['batchDelete'] ? ", removeMany: {$camel}Api.removeMany" : '')
            . " }, initialQuery: () => ({ page: 1, pageSize: 20, recycled: 0" . ($category ? ', __category: undefined' : '') . " }), rowKey: '{$primaryName}', pagination: true });\n"
            . "const { t } = useI18n();\n"
            . self::fieldPresentationSetup($data, 'list')
            . $listSetup
            . ($enabled['softDelete'] ? "const recycled = computed(() => query.recycled === 1);\n" : "const recycled = false;\n")
            . ($enabled['batchDelete'] ? "const selectedIds = () => selection.value.map(row => row.{$primaryName});\n" : '')
            . ($enabled['import'] ? "const fileInput = ref<HTMLInputElement>();\n" : '')
            . (($enabled['import'] || $enabled['export']) ? 'const csvLabels = computed<Record<string, string>>(() => (' . self::tsRecord($csvLabels) . "));\nconst csvColumns = computed(() => " . self::json($csvColumns) . ".map(column => ({ ...column, label: csvLabels.value[column.key] ?? column.label })) as CsvColumn<{$type}Payload>[]);\n" : '')
            . ($enabled['softDelete'] ? "function switchMode(value: boolean) { query.recycled = value ? 1 : 0; query.page = 1; void loadData(); }\n" : '')
            . ($enabled['delete'] ? "async function removeRow(row: {$type}) { await {$camel}Api.remove(row.{$primaryName}); await refreshAfterSave(); }\n" : '')
            . ($enabled['softDelete'] ? "async function restoreRow(row: {$type}) { await {$camel}Api.restore(row.{$primaryName}); await refreshAfterSave(); }\nasync function forceDeleteRow(row: {$type}) { await {$camel}Api.forceDelete(row.{$primaryName}); await refreshAfterSave(); }\n" : '')
            . ($enabled['batchSoftDelete'] ? "async function restoreSelected() { await {$camel}Api.restoreMany(selectedIds()); await refreshAfterSave(); }\nasync function forceDeleteSelected() { await {$camel}Api.forceDeleteMany(selectedIds()); await refreshAfterSave(); }\n" : '')
            . ($enabled['status'] ? "async function changeStatus(row: {$type}, enabled: boolean) { if (buttonLock.busy || {$recycledValue} || !buttonPermission({$prefix} + ':status')) return; buttonLock.busy = true; try { await {$camel}Api.status(row.{$primaryName}, enabled ? 1 : 0); await refreshAfterSave(); } finally { buttonLock.busy = false; } }\n" : '')
            . ($enabled['import'] ? "async function importCsv(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; input.value = ''; if (!file || buttonLock.busy || !buttonPermission({$prefix} + ':import')) return; buttonLock.busy = true; try { const version = buttonContextVersion.value; const rows = parseCsv<{$type}Payload>(await readFileAsText(file), csvColumns.value); if (version !== buttonContextVersion.value || !buttonPermission({$prefix} + ':import')) return; await {$camel}Api.importRows(rows); await refreshAfterSave(); } finally { buttonLock.busy = false; } }\n" : '')
            . ($enabled['export'] ? "async function exportRows() { const rows = await {$camel}Api.exportRows(query); downloadCsv('{$data['entity']}-export', toCsv(rows, csvColumns.value as CsvColumn<{$type}>[])); }\n" : '')
            . "</script>\n";
    }

    private static function form(array $data, string $class): string
    {
        return self::schemaForm($data, $class);
    }

    private static function schemaForm(array $data, string $class): string
    {
        $camel = self::camel($class);
        $type = self::tsTypeName($class);
        $enabled = self::enabledCapabilities($data);
        $tag = $data['features']['formMode'] === 'drawer' ? 'el-drawer' : 'el-dialog';
        $nodes = $data['layoutSchema'] ?? [];
        $formFields = ($data['capabilities']['form'] ?? true) ? array_values(array_filter($data['fields'], static fn (array $field): bool => ($field['form'] ?? false) && !($field['primary'] ?? false) && !($field['managed'] ?? false))) : [];
        $writable = array_values(array_filter($formFields, static fn (array $field): bool => ($field['writable'] ?? true) && ($field['component'] ?? '') !== 'readonly'));
        if ($nodes === []) foreach ($formFields as $field) {
            $rules = [];
            if ($field['required'] ?? false) $rules[] = ['type' => 'required'];
            $nodes[] = ['id' => $field['name'], 'kind' => 'field', 'type' => $field['component'] ?? 'input', 'field' => $field['name'], 'title' => $field['label'] ?? $field['name'], 'defaultValue' => $field['default'] ?? '', 'props' => (object) ($field['controlProps'] ?? []), 'validation' => $rules, 'dataSource' => ['kind' => 'static', 'options' => $field['options'] ?? []], 'children' => []];
        }
        $document = $data['formSchema'] ?? ['schemaVersion' => 2, 'key' => str_replace('-', '_', (string) $data['entity']), 'title' => (string) ($data['title'] ?? ''), 'nodes' => $nodes];
        if (empty($document['nodes'])) $document['nodes'] = $nodes;
        $fieldsByName = array_column($formFields, null, 'name');
        $sources = array_column($data['optionsSource'], null, 'name');
        $allowedSources = array_column(self::enabledOptionSources($data, $enabled), null, 'name');
        $optionMap = [];
        $prune = static function (array $nodes) use (&$prune, &$optionMap, $fieldsByName, $sources, $allowedSources, $enabled): array {
            $result = [];
            foreach ($nodes as $node) {
                if (($node['kind'] ?? '') === 'field') {
                    $field = $fieldsByName[$node['field'] ?? ''] ?? null;
                    if ($field === null) continue;
                    $sourceName = $field['optionsSource'] ?? '';
                    $source = $sources[$sourceName] ?? null;
                    $kind = $node['dataSource']['kind'] ?? $node['dataSource']['mode'] ?? '';
                    $dictionaryDisabled = !$enabled['dictionary'] && (($source['type'] ?? '') === 'dictionary' || in_array($kind, ['dictionary', 'dict'], true) || $node['type'] === 'dictionary');
                    if ($dictionaryDisabled) {
                        $node['type'] = 'input';
                        $node['dataSource'] = ['kind' => 'static', 'options' => []];
                    } elseif ($enabled['options'] && isset($allowedSources[$sourceName])) {
                        $optionMap[$field['name']] = $sourceName;
                        $node['dataSource'] = array_merge((array) ($node['dataSource'] ?? []), ['kind' => 'remote']);
                        if ($node['type'] === 'input') $node['type'] = 'select';
                    }
                    if (in_array($node['type'], ['image', 'images', 'file', 'files'], true) || ($field['upload'] ?? false)) {
                        if (!$enabled['upload']) {
                            $node['type'] = 'input';
                        } elseif (!in_array($node['type'], ['image', 'images', 'file', 'files'], true)) {
                            $multiple = preg_match('/(?:^|_)(?:images|files)$/', $field['name']) === 1;
                            $image = preg_match('/(?:^|_)(?:image|images|avatar|thumb)(?:_|$)/', $field['name']) === 1;
                            $node['type'] = $image ? ($multiple ? 'images' : 'image') : ($multiple ? 'files' : 'file');
                        }
                    }
                    if ($node['type'] === 'inputNumber') $node['type'] = 'number';
                    if (($field['writable'] ?? true) === false || ($field['component'] ?? '') === 'readonly') $node['disabled'] = true;
                }
                if (isset($node['children'])) $node['children'] = $prune($node['children']);
                $result[] = $node;
            }
            return $result;
        };
        $document['nodes'] = ($data['capabilities']['form'] ?? true) ? $prune($document['nodes']) : [];
        // 节点标题运行时翻译：字段节点共用 field.<name> key，容器节点按 form.node.<id> 收集。
        $nodeTitles = [];
        $walkTitles = static function (array $nodes) use (&$walkTitles, &$nodeTitles, $data, $fieldsByName): void {
            foreach ($nodes as $node) {
                $nodeId = (string) ($node['id'] ?? '');
                if (($node['kind'] ?? '') === 'field') {
                    $field = $fieldsByName[$node['field'] ?? ''] ?? null;
                    if ($field !== null && !self::sensitiveField($field) && $nodeId !== '') {
                        $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
                        $nodeTitles[$nodeId] = self::tCall(self::fieldLabelKey($data, $field), $labelText);
                    }
                } elseif ((string) ($node['title'] ?? '') !== '' && $nodeId !== '') {
                    $nodeTitles[$nodeId] = self::tCall(
                        self::collectFrontend($data, 'form.node.' . $nodeId, (string) $node['title']),
                        (string) $node['title']
                    );
                }
                $walkTitles((array) ($node['children'] ?? []));
            }
        };
        $walkTitles($document['nodes']);
        $schema = self::json($document);
        $valueMap = self::json(array_values(array_map(
            static fn (array $field): array => ['source' => self::camel((string) $field['name']), 'target' => (string) $field['name']],
            $formFields
        )));
        $fieldMap = self::json(array_values(array_map(
            static fn (array $field): array => ['source' => self::camel((string) $field['name']), 'target' => (string) $field['name'], 'sensitive' => self::sensitiveField($field)],
            $writable
        )));
        $submissionFields = [];
        $collectSubmission = static function (array $nodes) use (&$collectSubmission, &$submissionFields): void {
            foreach ($nodes as $node) {
                if (($node['kind'] ?? '') === 'field') {
                    $submissionFields[] = [
                        'field_name' => $node['field'], 'type' => $node['type'],
                        'form_readonly' => ($node['disabled'] ?? false) ? 1 : 0,
                        'control_props' => array_replace((array) ($node['props'] ?? []), ['schemaAccess' => (array) ($node['access'] ?? [])]),
                        'options_source' => $node['dataSource'] ?? null,
                    ];
                }
                if (!in_array($node['type'] ?? '', ['repeatable', 'subform'], true)) $collectSubmission((array) ($node['children'] ?? []));
            }
        };
        $collectSubmission($document['nodes']);
        $submissionFields = self::json($submissionFields);
        $defaults = self::json((object) array_column($formFields, 'default', 'name'));
        $formKey = str_replace('-', '_', (string) $data['entity']);
        // 选项映射仅来自裁剪后真正交给 renderer 的节点。
        $optionsMissingExpr = self::tCall(self::collectFrontend($data, 'message.optionsSourceMissing', '选项来源未注册'), '选项来源未注册');
        $optionsSetup = 'const optionSources = ' . self::json((object) $optionMap) . " as Record<string,string>;\n"
            . "const optionsRequest = async (_key:string, field:string, params: Record<string, unknown>, signal?: AbortSignal) => { const source=Object.hasOwn(optionSources,field)?optionSources[field]:undefined; if(!source) throw Error({$optionsMissingExpr}); "
            . ($enabled['options'] ? "return {options:await {$camel}Api.options(source, params, signal)};" : 'throw Error(' . self::tCall(self::collectFrontend($data, 'message.optionsDisabled', '选项能力未启用'), '选项能力未启用') . ');') . " };\n";
        $permissionPrefix = self::json($data['permissionPrefix']);
        $updateDisabledExpr = $enabled['update'] ? '' : self::tCall(self::collectFrontend($data, 'message.updateDisabled', '编辑能力未启用'), '编辑能力未启用');
        $createDisabledExpr = $enabled['create'] ? '' : self::tCall(self::collectFrontend($data, 'message.createDisabled', '新增能力未启用'), '新增能力未启用');
        $write = 'if (row) { ' . ($enabled['update'] ? "await {$camel}Api.update(row." . self::camel(self::primary($data)['name']) . ',payload);' : "throw Error({$updateDisabledExpr});") . ' } else { ' . ($enabled['create'] ? "await {$camel}Api.create(payload);" : "throw Error({$createDisabledExpr});") . ' }';
        $editTitleExpr = self::tCall(self::collectFrontend($data, 'form.editTitle', '编辑'), '编辑');
        $createTitleExpr = self::tCall(self::collectFrontend($data, 'form.createTitle', '新增'), '新增');
        $cancelExpr = self::tCall(self::collectFrontend($data, 'form.cancel', '取消'), '取消');
        $saveExpr = self::tCall(self::collectFrontend($data, 'form.save', '保存'), '保存');
        return "<template><{$tag} v-model=\"visible\" :title=\"(row ? {$editTitleExpr} : {$createTitleExpr})\" width=\"720px\"><SchemaRenderer :key=\"generation\" ref=\"schemaFormRef\" :schema=\"formSchema\" :values=\"form\" :options-request=\"optionsRequest\" form-key=\"{$formKey}\" @change=\"(field, value) => { form[field] = value; changed.add(field); }\" /><template #footer><el-button @click=\"visible=false\">{{ {$cancelExpr} }}</el-button><el-button type=\"primary\" :loading=\"saving\" @click=\"submit\">{{ {$saveExpr} }}</el-button></template></{$tag}></template>\n"
            . "<script setup lang=\"ts\">\nimport { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';\nimport { useI18n } from 'vue-i18n';\nimport SchemaRenderer from '@/views/form/components/SchemaRenderer.vue';\nimport { useUserStore } from '@/store/modules/user';\n"
            . "import type { FormSchemaDocument } from '@/views/form/schema/types';\nimport type { FormFieldDef } from '@/api/form';\nimport { buildSubmissionPayload, resolveSubmissionInclude } from '@/views/form/runtime/submissionPolicy';\nimport { mapFieldErrors } from '@/views/form/validation/asyncValidatorRegistry';\nimport { {$camel}Api, type {$type}, type {$type}Payload } from '{$data['_frontendComponentApiImport']}';\n"
            . "const { t } = useI18n();\nconst props=defineProps<{modelValue:boolean;row:{$type}|null;lock?:{busy:boolean}}>(); const emit=defineEmits<{ 'update:modelValue':[boolean]; success:[] }>();\n"
            . "const visible=computed({get:()=>props.modelValue,set:value=>emit('update:modelValue',value)}); const form=reactive<Record<string,unknown>>({}); const changed=reactive(new Set<string>()); const schemaFormRef=ref<InstanceType<typeof SchemaRenderer>>(); const sourceSchema={$schema} as unknown as FormSchemaDocument; const fieldMap={$fieldMap}; const valueMap={$valueMap}; const defaults={$defaults} as Record<string,unknown>; const submissionFields={$submissionFields} as unknown as FormFieldDef[];\n"
            . 'const nodeTitles = computed<Record<string, string>>(() => (' . self::tsRecord($nodeTitles) . "));\n"
            . "const formSchema=computed(()=>{const project=(nodes:FormSchemaDocument['nodes']):FormSchemaDocument['nodes']=>nodes.map(node=>({...node,...(node.id !== undefined && nodeTitles.value[node.id] !== undefined ? { title: nodeTitles.value[node.id] } : {}),...(props.row && fieldMap.some(item=>item.target===node.field && item.sensitive) && !changed.has(node.field ?? '')?{validation:[]}:{}),children:project(node.children ?? [])}));return {...sourceSchema,nodes:project(sourceSchema.nodes)};});\n"
            . $optionsSetup
            . "const user=useUserStore(); const permitted=(edit:boolean)=>user.permissions.some(code=>code==='*'||code==='*:*:*'||code==={$permissionPrefix}+':'+(edit?'update':'create'));\n"
            . "const saving=ref(false); const generation=ref(0); let active=true; onBeforeUnmount(()=>{active=false; generation.value++;});\n"
            . "watch(()=>[props.row,props.modelValue] as const,([row])=>{generation.value++;changed.clear();Object.keys(form).forEach(key=>delete form[key]);for(const item of valueMap)form[item.target]=row && fieldMap.some(field=>field.target===item.target && field.sensitive)?'':row?.[item.source as keyof {$type}]??defaults[item.target]??'';},{immediate:true});\n"
            . "async function submit(){const lock=props.lock;if(!active||saving.value||lock?.busy||!permitted(!!props.row))return;saving.value=true;if(lock)lock.busy=true;const token=generation.value;const row=props.row;try{if(!active||token!==generation.value||!props.modelValue||!permitted(!!row))return;const renderer=schemaFormRef.value;if(!renderer)return;await renderer.submit();if(!active||token!==generation.value||!props.modelValue||!permitted(!!row))return;const submitted=buildSubmissionPayload(submissionFields,form,resolveSubmissionInclude({schema_document:sourceSchema}),permission=>user.permissions.some(code=>code==='*'||code==='*:*:*'||code===permission));const payload=Object.fromEntries(fieldMap.filter(item=>Object.hasOwn(submitted,item.target)&&(!item.sensitive || !row || changed.has(item.target))).map(item=>[item.source,submitted[item.target]])) as {$type}Payload;{$write}if(active&&token===generation.value&&props.modelValue){visible.value=false;emit('success');}}catch(reason){const response=reason&&typeof reason==='object'?reason as {data?:{fieldErrors?:Array<{path:string;message:string}>};fieldErrors?:Array<{path:string;message:string}>}:null;const errors=response?.data?.fieldErrors??response?.fieldErrors??[];if(Array.isArray(errors)&&errors.length){if(!active||token!==generation.value||!props.modelValue)return;const mapped=mapFieldErrors(errors);const normalized=Object.fromEntries(Object.entries(mapped).map(([key,message])=>[valueMap.find(item=>item.target===key)?.target??valueMap.find(item=>item.source===key)?.target??key,message]));await schemaFormRef.value?.setFieldErrors(normalized);return;}throw reason;}finally{saving.value=false;if(lock)lock.busy=false;}}\n</script>\n";
    }

    private static function formControl(array $field, string $key, string $dynamicSource, bool $uploadEnabled): string
    {
        $component = (string) ($field['component'] ?? 'input');
        if ($dynamicSource !== '' && $component === 'input') {
            $component = 'select';
        }
        if ($uploadEnabled && (in_array($component, ['image', 'images', 'file', 'files'], true) || ($field['upload'] ?? false) === true)) {
            $fieldName = strtolower((string) $field['name']);
            $multiple = in_array($component, ['images', 'files'], true)
                || preg_match('/(?:^|_)(?:images|files)$/', $fieldName) === 1;
            $image = in_array($component, ['image', 'images'], true)
                || preg_match('/(?:^|_)(?:image|images|avatar|thumb)(?:_|$)/', $fieldName) === 1;
            $uploadType = $image ? ($multiple ? 'images' : 'image') : 'file';
            $bizType = $image ? 'image' : 'file';
            return "<Upload v-model=\"form.{$key}\" type=\"{$uploadType}\" biz-type=\"{$bizType}\" />";
        }

        $options = (array) ($field['options'] ?? $field['enum'] ?? []);
        if ($dynamicSource !== '') {
            $children = match ($component) {
                'radio' => "<el-radio v-for=\"item in optionLists.{$dynamicSource}\" :key=\"item.value\" :value=\"item.value\">{{ item.label }}</el-radio>",
                'checkbox' => "<el-checkbox v-for=\"item in optionLists.{$dynamicSource}\" :key=\"item.value\" :value=\"item.value\">{{ item.label }}</el-checkbox>",
                default => "<el-option v-for=\"item in optionLists.{$dynamicSource}\" :key=\"item.value\" :label=\"item.label\" :value=\"item.value\" />",
            };
        } else {
            $children = '';
            foreach ($options as $option) {
                $label = is_array($option) ? ($option['label'] ?? $option['value'] ?? '') : $option;
                $value = is_array($option) ? ($option['value'] ?? '') : $option;
                $labelText = htmlspecialchars((string) $label, ENT_QUOTES);
                $valueText = self::json($value);
                $children .= match ($component) {
                    'radio' => "<el-radio :value='{$valueText}'>{$labelText}</el-radio>",
                    'checkbox' => "<el-checkbox :value='{$valueText}'>{$labelText}</el-checkbox>",
                    default => "<el-option label=\"{$labelText}\" :value='{$valueText}' />",
                };
            }
        }

        $placeholder = htmlspecialchars((string) ($field['placeholder'] ?? ''), ENT_QUOTES);
        $placeholderAttribute = $placeholder === '' ? '' : " placeholder=\"{$placeholder}\"";
        return match ($component) {
            'password' => "<el-input v-model=\"form.{$key}\" type=\"password\" show-password{$placeholderAttribute} />",
            'textarea', 'richtext', 'json', 'mention' => "<el-input v-model=\"form.{$key}\" type=\"textarea\" :rows=\"4\"{$placeholderAttribute} />",
            'inputNumber', 'slider', 'rate' => "<el-input-number v-model=\"form.{$key}\" class=\"w-full\" />",
            'select', 'selectV2', 'treeSelect', 'cascader', 'dictionary', 'relation', 'department', 'user' => "<el-select v-model=\"form.{$key}\" filterable clearable class=\"w-full\">{$children}</el-select>",
            'radio' => "<el-radio-group v-model=\"form.{$key}\">{$children}</el-radio-group>",
            'checkbox', 'transfer' => "<el-checkbox-group v-model=\"form.{$key}\">{$children}</el-checkbox-group>",
            'switch' => "<el-switch v-model=\"form.{$key}\" />",
            'date' => "<el-date-picker v-model=\"form.{$key}\" type=\"date\" class=\"w-full\" />",
            'daterange' => "<el-date-picker v-model=\"form.{$key}\" type=\"daterange\" class=\"w-full\" />",
            'datetimerange' => "<el-date-picker v-model=\"form.{$key}\" type=\"datetimerange\" class=\"w-full\" />",
            'time', 'timeSelect' => "<el-time-picker v-model=\"form.{$key}\" class=\"w-full\" />",
            'datetime' => "<el-date-picker v-model=\"form.{$key}\" type=\"datetime\" class=\"w-full\" />",
            'color' => "<el-color-picker v-model=\"form.{$key}\" />",
            'hidden' => "<input v-model=\"form.{$key}\" type=\"hidden\" />",
            'readonly' => "<el-input v-model=\"form.{$key}\" disabled />",
            default => "<el-input v-model=\"form.{$key}\"{$placeholderAttribute} />",
        };
    }

    private static function fieldPresentationSetup(array $data, string $view): string
    {
        $entries = [];
        $nodes = [];
        $sources = [];
        $schemaSources = [];
        $collect = static function (array $items) use (&$collect, &$schemaSources): void {
            foreach ($items as $node) {
                if (isset($node['field'], $node['dataSource'])) $schemaSources[$node['field']] = $node['dataSource'];
                $collect((array) ($node['children'] ?? []));
            }
        };
        $collect((array) ($data['formSchema']['nodes'] ?? []));
        foreach ($data['fields'] as $field) {
            if (!(($field[$view] ?? ($view === 'detail')) || ($view === 'list' && ($field['search'] ?? false))) || self::sensitiveField($field)) continue;
            $key = self::json(self::camel($field['name']));
            $formatter = (string) ($field['listFormatter'] ?? '');
            $options = array_map(static fn ($option) => is_array($option) ? $option : ['label' => (string) $option, 'value' => $option], array_values((array) ($field['options'] ?? $field['enum'] ?? [])));
            if ($options === [] && in_array($formatter, ['switch', 'boolean'], true)) $options = [['label' => '是', 'value' => 1], ['label' => '否', 'value' => 0]];
            $source = $schemaSources[$field['name']] ?? ['kind' => 'static', 'options' => $options];
            if (isset($field['optionsSource'])) {
                $source['kind'] = 'remote';
                $sources[$field['name']] = $field['optionsSource'];
            }
            $nodeData = ['id' => self::camel($field['name']), 'field' => $field['name'], 'dataSource' => $source];
            $nodes[] = $nodeData;
            $node = self::json($nodeData);
            $entries[] = "{$key}: { node: {$node}, formatter: " . self::json($formatter) . ' }';
        }
        $key = self::json($data['formSchema']['key'] ?? str_replace('-', '_', $data['entity']));
        $contextFields = [];
        foreach ($data['fields'] as $field) {
            if (self::sensitiveField($field)) continue;
            $contextFields[] = self::json($field['name']) . ': values[' . self::json(self::camel($field['name'])) . ']';
        }
        $camel = self::camel($data['entity']);
        $setup = 'const presentationValues = (values: Record<string, unknown>) => ({ ' . implode(', ', $contextFields) . " });\n";
        $context = $view === 'detail' ? '(props.modelValue && props.row ? [props.row] : [])' : 'list.value';
        $setup .= 'const fieldOptions = useSuppliedFieldOptions(() => ' . $context . ".map(row => presentationValues({ ...row })));\n";
        $setup .= 'const presentationSources: Record<string, string> = ' . self::json((object) $sources) . ";\n";
        $request = $sources === [] ? '' : ", async (_key, field, params, signal) => { if (!presentationSources[field]) return formDataApi.options(_key, field, params, signal); return { options: await {$camel}Api.options(presentationSources[field], params, signal) }; }";
        if ($sources !== []) $setup .= "import { formDataApi } from '@/api/formData';\n";
        $listNodes = $view === 'list' ? array_values(array_filter($nodes, static fn (array $node): bool => count(array_filter($data['fields'], static fn (array $field): bool => $field['name'] === $node['field'] && ($field['list'] ?? false))) > 0)) : $nodes;
        $setup .= 'void fieldOptions.load(' . $key . ', ' . self::json($listNodes) . $request . ");\n";
        if ($view === 'list') {
            $filterNodes = array_values(array_filter($nodes, static fn (array $node): bool => count(array_filter($data['fields'], static fn (array $field): bool => $field['name'] === $node['field'] && ($field['search'] ?? false) && ($field['component'] ?? '') !== 'hidden')) > 0));
            $setup .= 'const filterNodes = ' . self::json($filterNodes) . " as import('@/views/form/runtime/fieldPresentation').PresentationNode[];\n";
            $setup .= "const filterOptions = useSuppliedFieldOptions(() => presentationValues(query as Record<string, unknown>));\n";
            $setup .= 'void filterOptions.load(' . $key . ', filterNodes' . $request . ");\n";
        }
        $setup .= 'const fieldPresentations = { ' . implode(', ', $entries) . " };\n";
        $setup .= "const presentField = (field: keyof typeof fieldPresentations, row: Record<string, unknown>) => { const values = presentationValues(row); const item = fieldPresentations[field]; return fieldOptions.placeholder(field, values) || formatFieldValue(row[field], resolveFieldOptions(item.node, fieldOptions.forContext(values)), item.formatter, 'published'); };\n";
        return $setup;
    }

    private static function listCell(string $key, string $formatter): string
    {
        $value = "scope.row.{$key}";
        $shared = "presentField('{$key}', scope.row)";
        return match ($formatter) {
            'tag' => "<template #default=\"scope\"><el-tag>{{ {$shared} }}</el-tag></template>",
            'switch', 'boolean' => "<template #default=\"scope\"><el-tag :type=\"Number({$value}) === 1 ? 'success' : 'info'\">{{ {$shared} }}</el-tag></template>",
            'image' => "<template #default=\"scope\"><el-image :src=\"String({$value} ?? '')\" fit=\"cover\" class=\"h-10 w-10 rounded\" /></template>",
            'images' => "<template #default=\"scope\"><span>{{ Array.isArray({$value}) ? {$value}.length + ' 张' : '' }}</span></template>",
            'money', 'percent', 'number' => "<template #default=\"scope\"><span>{{ {$shared} }}</span></template>",
            'link' => "<template #default=\"scope\"><el-link :href=\"String({$value} ?? '')\" target=\"_blank\">{{ String({$value} ?? '') }}</el-link></template>",
            'email' => "<template #default=\"scope\"><el-link :href=\"'mailto:' + String({$value} ?? '')\">{{ String({$value} ?? '') }}</el-link></template>",
            'phone' => "<template #default=\"scope\"><el-link :href=\"'tel:' + String({$value} ?? '')\">{{ String({$value} ?? '') }}</el-link></template>",
            'json' => "<template #default=\"scope\"><code>{{ {$shared} }}</code></template>",
            default => "<template #default=\"scope\"><span>{{ {$shared} }}</span></template>",
        };
    }

    private static function sensitiveField(array $field): bool
    {
        return ($field['component'] ?? '') === 'password' || !empty($field['controlProps']['sensitive']) || !empty($field['controlProps']['writeOnly']) || !empty($field['controlProps']['schemaAccess']);
    }

    private static function detail(array $data, string $class): string
    {
        $type = self::tsTypeName($class);
        $camel = self::camel($data['entity']);
        $hasSources = count(array_filter($data['fields'], static fn (array $field): bool => isset($field['optionsSource']))) > 0;
        $apiImport = $hasSources ? "import { {$camel}Api } from '{$data['_frontendComponentApiImport']}';" : '';
        $items = [];
        foreach ($data['fields'] as $field) {
            if (($field['detail'] ?? true) === false || self::sensitiveField($field)) continue;
            $key = self::camel($field['name']);
            $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
            $labelExpr = self::tCall(self::fieldLabelKey($data, $field), $labelText);
            $cell = self::listCell($key, (string) ($field['listFormatter'] ?? ''));
            $cell = str_replace(['<template #default="scope">', '</template>', 'scope.row.', 'scope.row)'], ['', '', 'row.', '{ ...row })'], $cell);
            $items[] = "<el-descriptions-item :label=\"{$labelExpr}\">{$cell}</el-descriptions-item>";
        }
        $titleExpr = self::tCall(self::collectFrontend($data, 'detail.title', '详情'), '详情');
        return "<template><el-drawer v-model=\"visible\" :title=\"{$titleExpr}\"><el-descriptions v-if=\"row\" :column=\"1\">" . implode('', $items) . "</el-descriptions></el-drawer></template>\n"
            . "<script setup lang=\"ts\">import { computed, watch } from 'vue'; import { useI18n } from 'vue-i18n'; import { formatFieldValue, resolveFieldOptions } from '@/views/form/runtime/fieldPresentation';
import { useSuppliedFieldOptions } from '@/views/form/runtime/fieldPresentation'; {$apiImport} import type { {$type} } from '{$data['_frontendComponentApiImport']}'; const { t } = useI18n(); const props=defineProps<{modelValue:boolean;row:{$type}|null}>(); const emit=defineEmits<{ 'update:modelValue':[boolean] }>(); const visible=computed({get:()=>props.modelValue,set:value=>emit('update:modelValue',value)});\n" . self::fieldPresentationSetup($data, 'detail') . "</script>\n";
    }

    private static function phpTest(array $data, string $class): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nuse app\\admin\\model\\{$class};\n\n"
            . "if (!is_subclass_of({$class}::class, \\think\\Model::class)) {\n"
            . "    throw new RuntimeException('生成模型必须继承 ThinkPHP Model');\n}\n"
            . "echo 'generated {$data['entity']} PHP contract: PASS' . PHP_EOL;\n";
    }

    private static function vitestTest(array $data, string $class): string
    {
        $camel = self::camel($class);
        return "import { describe, expect, it } from 'vitest';\n"
            . "import { {$camel}Api } from '@/api/generated/{$data['entity']}';\n\n"
            . "describe('generated {$data['entity']} API contract', () => {\n"
            . "  it('exposes list API', () => { expect({$camel}Api.list).toBeTypeOf('function'); });\n"
            . "});\n";
    }

    private static function enabledCapabilities(array $data): array
    {
        $capabilities = $data['capabilities'] ?? [];
        $features = $data['features'];
        $enabled = static fn (string $name): bool => ($capabilities[$name] ?? true) === true;
        $delete = $enabled('delete');
        $softDelete = $delete && $data['softDeletes'];
        $categoryOptions = $enabled('list') && ($data['list']['category']['enabled'] ?? false);
        $dictionary = ($enabled('form') && ($features['dictionary'] ?? false)) || $categoryOptions;
        $optionSources = self::enabledOptionSources($data, ['dictionary' => $dictionary]);
        return [
            'list' => $enabled('list'),
            'search' => $enabled('search') && $enabled('list'),
            'detail' => $enabled('detail') && ($features['detail'] ?? true),
            'create' => $enabled('create'),
            'update' => $enabled('update'),
            'delete' => $delete,
            'softDelete' => $softDelete,
            'batchDelete' => $delete && ($features['batchDelete'] ?? false),
            'batchSoftDelete' => $softDelete && ($features['batchDelete'] ?? false),
            'status' => $enabled('update') && ($features['status'] ?? false),
            'import' => $enabled('import') && ($features['import'] ?? false),
            'export' => $enabled('export') && ($features['export'] ?? false),
            'upload' => $enabled('form') && ($features['upload'] ?? false),
            'dictionary' => $dictionary,
            'options' => ($enabled('form') || $categoryOptions) && $optionSources !== [],
            'serverOptions' => ($enabled('form') || $categoryOptions) && array_filter(
                $optionSources,
                static fn (array $source): bool => $source['type'] !== 'endpoint'
            ) !== [],
        ];
    }

    private static function enabledOptionSources(array $data, array $enabled): array
    {
        return array_values(array_filter(
            $data['optionsSource'],
            static fn (array $source): bool => $source['type'] !== 'dictionary' || ($enabled['dictionary'] ?? false)
        ));
    }

    private static function primary(array $data): array
    {
        return array_values(array_filter($data['fields'], static fn (array $field): bool => ($field['primary'] ?? false)))[0];
    }

    /** @var array<string,string> 构建期收集的前端文案 key => 中文兜底。 */
    private static array $i18nKeys = [];

    /** @var array<string,string> 构建期收集的后端消息 用途 => 中文。 */
    private static array $backendLangKeys = [];

    private static function collectFrontend(array $data, string $purpose, string $zh): string
    {
        $key = 'crud.' . $data['entity'] . '.' . $purpose;
        self::$i18nKeys[$key] ??= $zh;
        return $key;
    }

    private static function collectBackend(string $purpose, string $zh): void
    {
        self::$backendLangKeys[$purpose] ??= $zh;
    }

    private static function fieldLabelKey(array $data, array $field): string
    {
        return self::collectFrontend($data, 'field.' . $field['name'], (string) ($field['label'] ?? $field['comment'] ?? $field['name']));
    }

    /** 生成 TS 单引号字符串字面量；json 已将引号与标签 HEX 转义，可安全嵌入模板属性。 */
    private static function tsString(string $value): string
    {
        $json = self::json($value);
        return "'" . substr($json, 1, -1) . "'";
    }

    private static function tCall(string $key, string $zh): string
    {
        return 't(' . self::tsString($key) . ', ' . self::tsString($zh) . ')';
    }

    /** id => TS 表达式 的对象字面量。 */
    private static function tsRecord(array $entries): string
    {
        $items = [];
        foreach ($entries as $id => $expr) {
            $items[] = self::tsString((string) $id) . ': ' . $expr;
        }
        return '{ ' . implode(', ', $items) . ' }';
    }

    private static function translationFor(array $translations, string $key): string
    {
        $value = $translations[$key] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
        return self::placeholderEn($key);
    }

    /** key 派生英文占位：field.name => Name，action.batchDestroy => Batch destroy。 */
    private static function placeholderEn(string $key): string
    {
        $last = substr($key, (int) strrpos($key, '.') + 1);
        $words = (string) preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace(['-', '_'], ' ', $last));
        return ucfirst(strtolower($words));
    }

    /** 生成 ThinkPHP 分组语言文件：按 [entity => [key => message]] 嵌套返回，匹配 allow_group 的 group.key 查询结构。 */
    private static function langFile(string $group, array $messages): string
    {
        ksort($messages);
        return "<?php\n\ndeclare(strict_types=1);\n\n// 生成的后端消息包；重新生成会覆盖人工修改。\nreturn "
            . self::phpArray([$group => $messages]) . ";\n";
    }

    private static function langMigration(array $data, array $translations): string
    {
        $entity = (string) $data['entity'];
        $sql = "-- Generated language pack migration; INSERT IGNORE 保护人工修订，force-refresh 由安装器改写。\n";
        $rows = [];
        foreach (self::$i18nKeys as $key => $zh) {
            $rows[] = '(' . self::sqlLiteral('zh-cn') . ',' . self::sqlLiteral($key) . ',' . self::sqlLiteral($zh) . ',NOW(),NOW())';
        }
        if ($rows !== []) {
            $sql .= "INSERT IGNORE INTO `fun_language_line` (`locale`, `key`, `value`, `created_at`, `updated_at`) VALUES\n"
                . implode(",\n", $rows) . ";\n";
        }
        $rows = [];
        $placeholders = [];
        foreach (self::$i18nKeys as $key => $zh) {
            $translated = $translations[$key] ?? null;
            if (!is_string($translated) || trim($translated) === '') {
                $placeholders[] = $key;
            }
            $rows[] = '(' . self::sqlLiteral('en-us') . ',' . self::sqlLiteral($key) . ',' . self::sqlLiteral(self::translationFor($translations, $key)) . ',NOW(),NOW())';
        }
        if ($rows !== []) {
            $sql .= "INSERT IGNORE INTO `fun_language_line` (`locale`, `key`, `value`, `created_at`, `updated_at`) VALUES\n"
                . implode(",\n", $rows) . ";\n";
        }
        if ($placeholders !== []) {
            $sql .= '-- draft: placeholder（AI 预翻译缺失，待人工校正）：' . implode(', ', $placeholders) . "\n";
        }
        $sql .= "-- 卸载清理（手动执行）：DELETE FROM `fun_language_line` WHERE `locale` = 'zh-cn' AND `ns` = 'crud.{$entity}';\n";
        $sql .= "-- 卸载清理（手动执行）：DELETE FROM `fun_language_line` WHERE `locale` = 'en-us' AND `ns` = 'crud.{$entity}';\n";
        return $sql;
    }

    private static function relativePath(string $fromDirectory, string $target): string
    {
        $from = array_values(array_filter(explode('/', trim($fromDirectory, '/')), 'strlen'));
        $to = array_values(array_filter(explode('/', trim($target, '/')), 'strlen'));
        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }
        return str_repeat('../', count($from)) . implode('/', $to);
    }

    private static function primaryKeyType(array $primary): string
    {
        if (($primary['format'] ?? '') === 'uuid') {
            return 'uuid';
        }
        return preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', strtolower((string) $primary['dbType']))
            ? 'integer'
            : 'string';
    }

    private static function primaryKeyPattern(array $primary): ?string
    {
        return match (self::primaryKeyType($primary)) {
            'uuid' => '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',
            'string' => '/^[A-Za-z0-9][A-Za-z0-9_-]*$/D',
            default => null,
        };
    }

    private static function castFor(array $field): ?string
    {
        $type = strtolower($field['dbType']);
        if (str_contains($type, 'decimal') || ($field['valueType'] ?? '') === 'decimal') return 'string';
        if (preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', $type)) return 'integer';
        if (str_contains($type, 'json')) return 'json';
        if (str_contains($type, 'datetime') || str_contains($type, 'timestamp')) return 'datetime';
        return null;
    }

    private static function dtoValue(array $field, string $expression): string
    {
        $type = strtolower($field['dbType']);
        if (str_contains($type, 'decimal') || ($field['valueType'] ?? '') === 'decimal') return "(string) {$expression}";
        if (preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', $type)) return "(int) {$expression}";
        if (str_contains($type, 'json')) return "(array) {$expression}";
        return "(string) ({$expression} ?? '')";
    }

    private static function tsType(array $field): string
    {
        $type = strtolower($field['dbType']);
        if (str_contains($type, 'decimal') || ($field['valueType'] ?? '') === 'decimal') return 'string';
        if (preg_match('/(?:tinyint|smallint|mediumint|bigint|int|float|double)/', $type)) return 'number';
        if (str_contains($type, 'json')) return 'Record<string, unknown> | unknown[]';
        return 'string';
    }

    private static function phpArray(array $value): string
    {
        return var_export($value, true);
    }

    private static function sqlLiteral(mixed $value): string
    {
        return SqlLiteral::quote($value);
    }

    private static function json(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
    }

    private static function studly(string $value): string
    {
        return implode('', array_map('ucfirst', preg_split('/[-_]/', $value) ?: []));
    }

    private static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    private static function tsTypeName(string $class): string
    {
        return $class . 'Model';
    }
}
