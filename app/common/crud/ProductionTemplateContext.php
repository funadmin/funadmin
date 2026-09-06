<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 将严格 Definition 编译为确定性的生产模板上下文。
 */
final class ProductionTemplateContext
{
    public static function build(CrudDefinition $definition): array
    {
        $data = $definition->toArray();
        $class = self::studly((string) $data['entity']);
        $primary = array_values(array_filter(
            $data['fields'],
            static fn (array $field): bool => ($field['primary'] ?? false) === true
        ))[0];

        return [
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
        if (($data['features']['softDelete'] ?? false) && !in_array('deleted_at', $names, true)) {
            $columns[] = '  `deleted_at` datetime NULL';
        }
        $lines = array_merge($columns, ["  PRIMARY KEY (`{$primary['name']}`)"], $indexes);
        return "-- Generated forward migration; review before applying.\n"
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
        foreach ($data['relations'] as $relation) {
            $target = '\\app\\console\\model\\' . $relation['target'] . '::class';
            $arguments = match ($relation['type']) {
                'belongsTo', 'hasOne', 'hasMany' => "$target, '{$relation['field']}', '{$relation['targetField']}'",
                default => "$target, '{$relation['pivotTable']}', '"
                    . ($relation['pivotTargetKey'] ?? $relation['targetField']) . "', '"
                    . ($relation['pivotLocalKey'] ?? $relation['field']) . "'",
            };
            $methods[] = "    public function {$relation['name']}()\n    {\n"
                . "        return \$this->{$relation['type']}({$arguments});\n    }";
        }
        $softImport = ($data['features']['softDelete'] ?? false)
            ? "use app\\common\\model\\concern\\LaravelSoftDelete;\n" : '';
        $softTrait = ($data['features']['softDelete'] ?? false) ? "    use LaravelSoftDelete;\n\n" : '';
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace app\\console\\model;\n\n"
            . $softImport
            . "\nfinal class {$class} extends BackendModel\n{\n{$softTrait}"
            . "    protected \$name = '" . preg_replace('/^fun_/', '', $data['table']) . "';\n"
            . "    protected \$pk = '{$primary['name']}';\n"
            . '    protected $type = ' . self::phpArray($casts) . ";\n\n"
            . implode("\n\n", $methods) . "\n}\n";
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
                $parts[] = "unique:\\app\\console\\model\\{$class},{$field['name']},{{$primary['name']}},{$primary['name']}";
            }
            $compiled = array_values(array_filter(array_merge($parts, $field['rules'] ?? []), static fn (string $rule): bool => $rule !== ''));
            if ($compiled !== []) {
                $rules[$field['name']] = implode('|', $compiled);
            }
        }
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace app\\console\\validate;\n\nuse think\\Validate;\n\n"
            . "final class {$class}Validate extends Validate\n{\n"
            . '    protected $rule = ' . self::phpArray($rules) . ";\n\n"
            . "    public function forUpdate(int|string \$id): self\n    {\n"
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
                    $value = preg_match('/(?:tinyint|smallint|mediumint|bigint|int)/', strtolower((string) ($localField['dbType'] ?? '')))
                        ? "(int) \$row['{$source['valueField']}']"
                        : "\$row['{$source['valueField']}']";
                    if ($data['dataScope']['enabled'] && $relation['field'] === $data['dataScope']['field']) {
                        $method = self::camel($source['name']);
                        $optionArms[] = "            '{$source['name']}' => \$this->{$method}(\$departmentIds),";
                        $relationOptionMethods[] = "\n    private function {$method}(?array \$departmentIds): array\n    {\n        \$query = \\app\\console\\model\\{$relation['target']}::order('{$source['valueField']}', 'asc')->field('{$source['valueField']},{$source['labelField']}');\n        if (\$departmentIds !== null) \$query->whereIn('{$source['valueField']}', \$departmentIds ?: [0]);\n        return array_map(static fn (array \$row): array => ['label' => (string) \$row['{$source['labelField']}'], 'value' => {$value}], \$query->select()->toArray());\n    }\n";
                    } else {
                        $optionArms[] = "            '{$source['name']}' => array_map(static fn (array \$row): array => ['label' => (string) \$row['{$source['labelField']}'], 'value' => {$value}], \\app\\console\\model\\{$relation['target']}::order('{$source['valueField']}', 'asc')->field('{$source['valueField']},{$source['labelField']}')->select()->toArray()),";
                    }
                }
            }
        }
        $dictionaryMethod = $usesDictionary
            ? "\n    private function dictionaryOptions(string \$code): array\n    {\n        \$type = \\app\\common\\model\\DictType::where('code', \$code)->where('status', 1)->find();\n        if (!\$type) return [];\n        return \\app\\common\\model\\DictItem::where('type_id', \$type->id)->where('status', 1)->order('sort_order', 'asc')->field('value,label')->select()->toArray();\n    }\n"
            : '';
        $optionsMethod = $optionArms === [] ? '' : "\n    public function options(string \$source, ?array \$departmentIds = null): array\n    {\n        return match (\$source) {\n"
            . implode("\n", $optionArms)
            . "\n            default => throw new \\InvalidArgumentException('未知 optionsSource'),\n        };\n    }\n"
            . implode('', $relationOptionMethods)
            . $dictionaryMethod;
        $referenceMethod = ($data['features']['referenceProtection'] ?? false) === true
            ? "    public function assertNotReferenced(iterable \$models, bool \$force): ?string\n    {\n        \$ids = [];\n        foreach (\$models as \$model) {\n            \$ids[] = \$model->{$primary['name']};\n        }\n        if (\$ids === []) return null;\n        \$references = Db::query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?', ['{$data['table']}', '{$primary['name']}']);\n        foreach (\$references as \$reference) {\n            \$table = (string) (\$reference['TABLE_NAME'] ?? \$reference['table_name'] ?? '');\n            \$column = (string) (\$reference['COLUMN_NAME'] ?? \$reference['column_name'] ?? '');\n            if (!preg_match('/^[a-z_][a-z0-9_]*$/', \$table) || !preg_match('/^[a-z_][a-z0-9_]*$/', \$column)) {\n                throw new \\RuntimeException('数据库引用元数据包含非法标识符');\n            }\n            if (Db::table(\$table)->whereIn(\$column, \$ids)->limit(1)->count() > 0) {\n                return '记录仍被 ' . \$table . '.' . \$column . ' 引用，无法删除';\n            }\n        }\n        return null;\n    }\n"
            : "    public function assertNotReferenced(iterable \$models, bool \$force): ?string\n    {\n        return null;\n    }\n";
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace app\\console\\service;\n\n"
            . "use app\\console\\model\\{$class};\n{$uuidImport}use think\\facade\\Db;\n\n"
            . "final class {$class}Service\n{\n"
            . '    public const WRITABLE_FIELDS = ' . self::phpArray($writable) . ";\n"
            . '    public const WITH_RELATIONS = ' . self::phpArray($with) . ";\n\n"
            . $prepareCreateMethod
            . "    public function query(?array \$departmentIds = null, bool \$recycled = false)\n    {\n"
            . "        \$query = \$recycled ? {$class}::onlyTrashed()->with(self::WITH_RELATIONS) : {$class}::with(self::WITH_RELATIONS);\n{$scope}        return \$query;\n    }\n\n"
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
        $search = $exact = $range = $sort = [];
        foreach ($data['fields'] as $field) {
            if (($field['search'] ?? false) === true) {
                $operator = $field['searchOperator'] ?? 'eq';
                $parameter = self::camel($field['name']) . ($operator === 'range' ? 'Range' : '');
                if ($operator === 'like') $search[$parameter] = $field['name'];
                elseif ($operator === 'range') $range[$parameter] = $field['name'];
                else $exact[$parameter] = $field['name'];
            }
            if (($field['sortable'] ?? false) === true) $sort[self::camel($field['name'])] = $field['name'];
        }
        $dto = [];
        foreach ($data['fields'] as $field) {
            if (($field['detail'] ?? true) === false) continue;
            $dto[] = "            '" . self::camel($field['name']) . "' => "
                . self::dtoValue($field, "\$model->{$field['name']}") . ',';
        }
        foreach ($data['relations'] as $relation) {
            $dto[] = "            '{$relation['name']}' => \$model->{$relation['name']}?->toArray(),";
        }
        $features = $data['features'];
        $enabled = self::enabledCapabilities($data);
        $statusTraitAlias = $enabled['status'] ? "        status as private crudStatus; status as private;\n" : '';
        $methods = [];
        if ($enabled['list']) $methods[] = "    #[Get('')]\n    public function index(): Response { return \$this->crudIndex(); }";
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
            $methods[] = "    #[Get('options/:source')]\n    #[Pattern('source', '[a-z][a-z0-9_]*')]\n    public function options(string \$source): Response\n    {\n{$optionsScope}        return \$this->ok(data: (new {$class}Service())->options({$optionsArguments}));\n    }";
        }
        if ($enabled['softDelete']) {
            $methods[] = "    #[Delete(':id')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function remove(int|string \$id): Response { return \$this->crudRemove(\$id); }";
            $methods[] = "    #[Post(':id/restore')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function restore(int|string \$id): Response { return \$this->crudRestoreOne(\$id); }";
            $methods[] = "    #[Delete(':id/destroy')]\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\n    public function destroy(int|string \$id): Response { return \$this->crudDestroyOne(\$id); }";
        }
        if ($enabled['batchDelete']) {
            $methods[] = "    #[Delete('')]\n    public function recycle(): Response { return \$this->crudRecycle(); }";
        }
        if ($enabled['batchDelete']) {
            $methods[] = "    #[Post('restore')]\n    public function restoreMany(): Response { return \$this->crudRestoreMany(); }";
            $methods[] = "    #[Delete('destroy')]\n    public function destroyMany(): Response { return \$this->crudDestroyMany(); }";
        }
        if ($enabled['import']) $methods[] = "    #[Post('import')]\n    public function import(): Response { return \$this->crudImport(); }";
        if ($enabled['export']) $methods[] = "    #[Get('export')]\n    public function export(): Response { return \$this->crudExport(); }";
        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace app\\console\\controller\\generated;\n\n"
            . "use app\\console\\controller\\base\\AdminApiController;\nuse app\\console\\middleware\\CheckAdminApiCsrf;\n"
            . "use app\\console\\middleware\\CheckAdminApiRole;\nuse app\\console\\middleware\\SystemLog;\n"
            . "use app\\console\\model\\{$class};\nuse app\\console\\service\\DataScopeService;\nuse app\\console\\service\\{$class}Service;\n"
            . "use app\\console\\validate\\{$class}Validate;\nuse app\\common\\traits\\Crud;\n"
            . "use think\\annotation\\route\\Delete;\nuse think\\annotation\\route\\Get;\nuse think\\annotation\\route\\Group;\n"
            . "use think\\annotation\\route\\Pattern;\nuse think\\annotation\\route\\Post;\nuse think\\annotation\\route\\Put;\n"
            . "use think\\Model;\nuse think\\Response;\n\n#[Group('" . ltrim($data['apiPrefix'], '/') . "')]\n"
            . "final class {$class}Controller extends AdminApiController\n{\n    use Crud {\n        index as private crudIndex; index as private;\n        detail as private crudDetail; detail as private;\n        create as private crudCreate; create as private;\n        update as private crudUpdate; update as private;\n{$statusTraitAlias}        remove as private crudRemove; remove as private;\n        restoreOne as private crudRestoreOne; restoreOne as private;\n        destroyOne as private crudDestroyOne; destroyOne as private;\n        recycle as private crudRecycle; recycle as private;\n        restore as private crudRestoreMany; restore as private;\n        destroy as private crudDestroyMany; destroy as private;\n        import as private crudImport; import as private;\n        export as private crudExport; export as private;\n        baseQuery as private crudUnscopedBaseQuery;\n    }\n"
            . "    protected array \$middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];\n"
            . "    protected string \$model = {$class}::class;\n\n" . implode("\n\n", $methods) . "\n\n"
            . '    protected function searchFields(): array { return ' . self::phpArray($search) . "; }\n"
            . '    protected function exactFilters(): array { return ' . self::phpArray($exact) . "; }\n"
            . '    protected function rangeFilters(): array { return ' . self::phpArray($range) . "; }\n"
            . '    protected function sortFields(): array { return ' . self::phpArray($sort) . "; }\n"
            . "    protected function primaryKey(): string { return '{$primary['name']}'; }\n"
            . "    protected function primaryKeyType(): string { return '" . self::primaryKeyType($primary) . "'; }\n"
            . "    protected function primaryKeyPattern(): ?string { return " . var_export(self::primaryKeyPattern($primary), true) . "; }\n"
            . ($data['dataScope']['enabled']
                ? "    protected function baseQuery(bool \$onlyTrashed, bool \$withTrashed)\n    {\n        \$query = \$this->crudUnscopedBaseQuery(\$onlyTrashed, \$withTrashed);\n        \$scope = (new DataScopeService())->resolve();\n        return \$scope['all'] ? \$query : \$query->whereIn('{$data['dataScope']['field']}', \$scope['departmentIds'] ?: [0]);\n    }\n"
                : '')
            . "    protected function importFields(): array { return array_combine({$class}Service::WRITABLE_FIELDS, {$class}Service::WRITABLE_FIELDS); }\n"
            . "    protected function importPayload(array \$row): array { return (new {$class}Service())->prepareCreatePayload(\$this->mapImportRow(\$row)); }\n"
            . "    protected function exportFields(): array { return " . self::phpArray(array_values(array_map(static fn (array $field): string => self::camel($field['name']), array_filter($data['fields'], static fn (array $field): bool => ($field['detail'] ?? true) === true)))) . "; }\n"
            . "    protected function importLimit(): int { return {$features['importLimit']}; }\n"
            . "    protected function exportLimit(): int { return {$features['exportLimit']}; }\n"
            . "    protected function payload(?Model \$model = null): array\n    {\n"
            . "        \$payload = array_intersect_key(\$this->request->post(), array_flip({$class}Service::WRITABLE_FIELDS));\n"
            . "        return \$model === null ? (new {$class}Service())->prepareCreatePayload(\$payload) : \$payload;\n    }\n"
            . "    protected function validatePayload(array &\$data, ?Model \$model = null): ?string\n    {\n"
            . ($data['dataScope']['enabled']
                ? "        \$scope = (new DataScopeService())->resolve();\n        if (!\$scope['all']) {\n            if (\$model === null && !array_key_exists('{$data['dataScope']['field']}', \$data)) {\n                return '数据范围字段 {$data['dataScope']['field']} 必填';\n            }\n            \$scopeValue = \$data['{$data['dataScope']['field']}'] ?? \$model?->{$data['dataScope']['field']};\n            if (!in_array((int) \$scopeValue, array_map('intval', \$scope['departmentIds']), true)) {\n                return '无权写入指定数据范围';\n            }\n        }\n"
                : '')
            . "        \$validate = new {$class}Validate();\n"
            . "        if (\$model !== null) \$validate->forUpdate(\$model->{$primary['name']});\n"
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
        $enabled = self::enabledCapabilities($data);
        $actionCodes = [
            'index' => 'list', 'detail' => 'detail', 'create' => 'create', 'update' => 'update',
            'status' => 'status', 'options' => 'options', 'remove' => 'delete', 'restore' => 'restore', 'destroy' => 'destroy',
            'recycle' => 'batch-delete', 'restoreMany' => 'batch-restore', 'destroyMany' => 'batch-destroy',
            'import' => 'import', 'export' => 'export',
        ];
        $actions = array_values(array_filter(
            array_keys($actionCodes),
            static fn (string $action): bool => match ($action) {
                'index' => $enabled['list'],
                'options' => $enabled['options'],
                'remove', 'restore', 'destroy' => $enabled['softDelete'],
                'recycle', 'restoreMany', 'destroyMany' => $enabled['batchDelete'],
                default => $enabled[$action] ?? false,
            }
        ));
        $sourceName = self::sqlLiteral($data['entity']);
        $title = self::sqlLiteral($data['title']);
        $controller = self::sqlLiteral('console/generated.' . strtolower(self::studly((string) $data['entity'])) . 'controller');
        $values = [];
        foreach ($actions as $index => $action) {
            $values[] = "(@permission_group_id, 'console', " . self::sqlLiteral($data['permissionPrefix'] . ':' . $actionCodes[$action])
                . ", {$controller}, " . self::sqlLiteral($action) . ', ' . self::sqlLiteral($data['title'] . ' ' . $action)
                . ", 'route', 1, 0, " . (($index + 1) * 10) . ", 'generated', {$sourceName}, NOW(), NOW())";
        }
        $menuPath = self::sqlLiteral('/' . ltrim($data['routePath'], '/'));
        $menuQuery = self::sqlLiteral("component=generated/{$data['entity']}/index&type=C&permission={$data['permissionPrefix']}:list");
        return "-- Generated forward permission/menu migration; review before applying.\n"
            . "INSERT INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, sort_order, source_type, source_name, created_at, updated_at) "
            . "SELECT 0, 'console', NULL, '', '', {$title}, 'group', 1, 0, 0, 'generated', {$sourceName}, NOW(), NOW() "
            . "WHERE NOT EXISTS (SELECT 1 FROM fun_permission WHERE source_type = 'generated' AND source_name = {$sourceName} AND resource_type = 'group');\n"
            . "SET @permission_group_id = (SELECT id FROM fun_permission WHERE source_type = 'generated' AND source_name = {$sourceName} AND resource_type = 'group' ORDER BY id LIMIT 1);\n"
            . "INSERT INTO fun_admin_menu (pid, permission_id, app_name, name, href, query, target, icon, status, sort_order, source_type, source_name, created_at, updated_at) "
            . "SELECT 0, @permission_group_id, 'console', {$title}, {$menuPath}, {$menuQuery}, '_self', 'i-ep-document', 1, 0, 'generated', {$sourceName}, NOW(), NOW() "
            . "WHERE NOT EXISTS (SELECT 1 FROM fun_admin_menu WHERE source_type = 'generated' AND source_name = {$sourceName});\n"
            . ($values === [] ? '' : "INSERT IGNORE INTO fun_permission (pid, app_name, code, obj, act, name, resource_type, status, is_public, sort_order, source_type, source_name, created_at, updated_at) VALUES\n"
                . implode(",\n", $values) . ";\n");
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
        $base = rtrim($data['apiPrefix'], '/');
        $endpointOptions = [];
        foreach (self::enabledOptionSources($data, $enabled) as $source) {
            if ($source['type'] !== 'endpoint') {
                continue;
            }
            $endpointOptions[] = "    case '{$source['name']}': {\n"
                . "      const rows = await request.get<Array<{ {$source['labelField']}: unknown; {$source['valueField']}: string | number }>>("
                . self::json($source['endpoint']) . ");\n"
                . "      return rows.map(item => ({ label: String(item.{$source['labelField']} ?? ''), value: item.{$source['valueField']} }));\n"
                . "    }";
        }
        $optionsBody = $endpointOptions === []
            ? "request.get<Array<{ label: string; value: string | number }>>(`{$base}/options/\${source}`)"
            : "(async () => {\n  switch (source) {\n" . implode("\n", $endpointOptions)
                . "\n    default:\n      return request.get<Array<{ label: string; value: string | number }>>(`{$base}/options/\${source}`);\n  }\n})()";
        $methods = [];
        if ($enabled['list']) $methods[] = "  list: (params: {$type}Query) => request.get<API.PageResult<{$type}>>('{$base}', params)";
        if ($enabled['detail']) $methods[] = "  detail: (id: {$type}Id) => request.get<{$type}>(`{$base}/\${id}`)";
        if ($enabled['create']) $methods[] = "  create: (data: {$type}Payload) => request.post<{$type}>('{$base}', data)";
        if ($enabled['update']) $methods[] = "  update: (id: {$type}Id, data: {$type}Payload) => request.put<{$type}>(`{$base}/\${id}`, data)";
        if ($enabled['softDelete']) {
            $methods[] = "  remove: (id: {$type}Id) => request.delete(`{$base}/\${id}`)";
            $methods[] = "  restore: (id: {$type}Id) => request.post(`{$base}/\${id}/restore`)";
            $methods[] = "  forceDelete: (id: {$type}Id) => request.delete(`{$base}/\${id}/destroy`)";
        }
        if ($enabled['batchDelete']) {
            $methods[] = "  removeMany: (ids: {$type}Id[]) => request.delete('{$base}', { ids })";
            $methods[] = "  restoreMany: (ids: {$type}Id[]) => request.post('{$base}/restore', { ids })";
            $methods[] = "  forceDeleteMany: (ids: {$type}Id[]) => request.delete('{$base}/destroy', { ids })";
        }
        if ($enabled['status']) $methods[] = "  status: (id: {$type}Id, status: number) => request.post(`{$base}/\${id}/status`, { status })";
        if ($enabled['options']) $methods[] = "  options: (source: string) => {$optionsBody}";
        if ($enabled['import']) $methods[] = "  importRows: (rows: {$type}Payload[]) => request.post('{$base}/import', { rows })";
        if ($enabled['export']) $methods[] = "  exportRows: (params: Partial<{$type}Query>) => request.get<{$type}[]>('{$base}/export', params)";
        return "import request from '@/utils/http';\n\nexport interface {$type} {\n"
            . implode("\n", $fields) . "\n}\nexport type {$type}Id = {$idType};\n"
            . "export interface {$type}Query { page: number; pageSize: number; recycled?: 0 | 1; sort?: string; order?: 'asc' | 'desc'; [key: string]: unknown }\n"
            . "export type {$type}Payload = Partial<Omit<{$type}, '{$primaryName}'>>;\n\nexport const {$camel}Api = {\n"
            . implode(",\n", $methods) . "\n};\n";
    }

    private static function view(array $data, string $class, array $primary): string
    {
        $camel = self::camel($class);
        $type = self::tsTypeName($class);
        $columns = [];
        $csvColumns = [];
        foreach ($data['fields'] as $field) {
            $key = self::camel($field['name']);
            $labelText = (string) ($field['label'] ?? $field['comment'] ?? $field['name']);
            if (($field['list'] ?? false) === true) {
                $columns[] = "          <el-table-column prop=\"{$key}\" label=\"" . htmlspecialchars($labelText, ENT_QUOTES) . "\" />";
            }
            if (($field['writable'] ?? true) && !($field['primary'] ?? false)) {
                $csvColumns[] = ['key' => $key, 'label' => $labelText];
            }
        }
        $primaryName = self::camel($primary['name']);
        $enabled = self::enabledCapabilities($data);
        if (!$enabled['list']) {
            return "<template><PageWrapper title=\"" . htmlspecialchars($data['title'], ENT_QUOTES) . "\" /></template>\n";
        }
        $statusColumn = $enabled['status']
            ? "          <el-table-column label=\"状态操作\"><template #default=\"scope\"><el-switch :model-value=\"Number(scope.row.status) === 1\" :disabled=\"recycled\" @change=\"value => changeStatus(scope.row as {$type}, value === true)\" /></template></el-table-column>\n"
            : '';
        $searchSlot = $enabled['search'] ? "      <template #search><SearchForm :model=\"query\" :loading=\"loading\" @search=\"onSearch\" @reset=\"onReset\" /></template>\n" : '';
        $toolbar = [];
        if ($enabled['softDelete']) {
            $toolbar[] = '<el-button @click="switchMode(false)">正常列表</el-button><el-button @click="switchMode(true)">回收站</el-button>';
        }
        if ($enabled['create'] && ($data['capabilities']['form'] ?? true)) $toolbar[] = '<el-button v-if="!recycled" type="primary" @click="onAdd">新增</el-button>';
        if ($enabled['batchDelete']) $toolbar[] = '<el-button v-if="!recycled" type="danger" :disabled="!selection.length" @click="onBatchDelete">批量删除</el-button><el-button v-if="recycled" type="success" :disabled="!selection.length" @click="restoreSelected">批量恢复</el-button><el-button v-if="recycled" type="danger" :disabled="!selection.length" @click="forceDeleteSelected">批量永久删除</el-button>';
        if ($enabled['import']) $toolbar[] = '<el-button @click="fileInput?.click()">导入</el-button><input ref="fileInput" class="hidden" type="file" accept=".csv,text/csv" @change="importCsv" />';
        if ($enabled['export']) $toolbar[] = '<el-button @click="exportRows">导出</el-button>';
        $editButton = $enabled['update'] && ($data['capabilities']['form'] ?? true) ? "<el-button v-if=\"!recycled\" link @click=\"onEdit(scope.row as {$type})\">编辑</el-button>" : '';
        $detailButton = $enabled['detail'] ? "<el-button v-if=\"!recycled\" link @click=\"onOpenDrawer(scope.row as {$type})\">详情</el-button>" : '';
        $deleteButtons = $enabled['softDelete']
            ? "<el-button v-if=\"!recycled\" link type=\"danger\" @click=\"removeRow(scope.row as {$type})\">删除</el-button><el-button v-else link type=\"success\" @click=\"restoreRow(scope.row as {$type})\">恢复</el-button><el-button v-if=\"recycled\" link type=\"danger\" @click=\"forceDeleteRow(scope.row as {$type})\">永久删除</el-button>"
            : '';
        $operationColumn = $editButton . $detailButton . $deleteButtons === '' ? '' : "          <el-table-column label=\"操作\"><template #default=\"scope\">{$editButton}{$detailButton}{$deleteButtons}</template></el-table-column>\n";
        $formEnabled = ($enabled['create'] || $enabled['update']) && ($data['capabilities']['form'] ?? true);
        $formComponent = $formEnabled ? "<{$class}Form v-model=\"dialogVisible\" :row=\"current\" @success=\"loadData\" />" : '';
        $detailComponent = $enabled['detail'] ? "<{$class}Detail v-model=\"drawerVisible\" :row=\"current\" />" : '';
        $crudBindings = ['loading', 'list', 'total', 'query', 'loadData'];
        if ($enabled['search']) array_push($crudBindings, 'onSearch', 'onReset');
        if ($enabled['batchDelete']) array_push($crudBindings, 'selection', 'onSelectionChange', 'onBatchDelete');
        if ($formEnabled || $enabled['detail']) $crudBindings[] = 'current';
        if ($formEnabled) $crudBindings[] = 'dialogVisible';
        if ($enabled['create'] && $formEnabled) $crudBindings[] = 'onAdd';
        if ($enabled['update'] && $formEnabled) $crudBindings[] = 'onEdit';
        if ($enabled['detail']) array_push($crudBindings, 'drawerVisible', 'onOpenDrawer');
        $selectionColumn = $enabled['batchDelete'] ? "          <el-table-column type=\"selection\" width=\"48\" />\n" : '';
        $selectionChange = $enabled['batchDelete'] ? ' @selection-change="handleSelectionChange"' : '';
        $vueImports = [];
        if ($enabled['softDelete']) $vueImports[] = 'computed';
        if ($enabled['import']) $vueImports[] = 'ref';
        $vueImport = $vueImports === [] ? '' : "import { " . implode(', ', $vueImports) . " } from 'vue';\n";
        $csvImport = $enabled['import'] || $enabled['export']
            ? "import { downloadCsv, parseCsv, readFileAsText, toCsv, type CsvColumn } from '@/utils/csv';\n"
            : '';
        return "<template>\n  <PageWrapper title=\"" . htmlspecialchars($data['title'], ENT_QUOTES) . "\">\n"
            . "    <DataTableShell storage-key=\"generated-{$data['entity']}\" :loading=\"loading\" @refresh=\"loadData\">\n"
            . $searchSlot
            . "      <template #toolbar-left>" . implode('', $toolbar) . "</template>\n"
            . "      <template #default=\"{ size, stripe, border, headerCellStyle }\"><el-table :data=\"list\" :size=\"size\" :stripe=\"stripe\" :border=\"border\" :header-cell-style=\"headerCellStyle\"{$selectionChange}>\n"
            . $selectionColumn . implode("\n", $columns) . "\n" . $statusColumn
            . $operationColumn
            . "        </el-table><el-pagination v-model:current-page=\"query.page\" v-model:page-size=\"query.pageSize\" :total=\"total\" @change=\"loadData\" /></template>\n"
            . "    </DataTableShell>{$formComponent}{$detailComponent}\n"
            . "  </PageWrapper>\n</template>\n<script setup lang=\"ts\">\n" . $vueImport
            . ($enabled['softDelete'] ? "import { ElMessageBox } from 'element-plus';\n" : '')
            . "import { useCrud } from '@/composables/useCrud';\n" . $csvImport
            . "import { {$camel}Api, type {$type}, type {$type}Payload, type {$type}Query } from '@/api/generated/{$data['entity']}';\n"
            . ($formEnabled ? "import {$class}Form from './components/{$class}Form.vue';\n" : '')
            . ($enabled['detail'] ? "import {$class}Detail from './components/{$class}Detail.vue';\n" : '')
            . 'const { ' . implode(', ', $crudBindings) . " } = useCrud<{$type}, {$type}Query, {$type}['{$primaryName}']>({ api: { list: {$camel}Api.list"
            . ($enabled['batchDelete'] ? ", removeMany: {$camel}Api.removeMany" : '')
            . " }, initialQuery: () => ({ page: 1, pageSize: 20, recycled: 0 }), rowKey: '{$primaryName}', pagination: true });\n"
            . ($enabled['softDelete'] ? "const recycled = computed(() => query.recycled === 1);\n" : "const recycled = false;\n")
            . ($enabled['batchDelete'] ? "const selectedIds = () => selection.value.map(row => row.{$primaryName});\nconst handleSelectionChange = (rows: unknown[]) => onSelectionChange(rows as {$type}[]);\n" : '')
            . ($enabled['import'] ? "const fileInput = ref<HTMLInputElement>();\n" : '')
            . (($enabled['import'] || $enabled['export']) ? "const csvColumns = " . self::json($csvColumns) . " as CsvColumn<{$type}Payload>[];\n" : '')
            . ($enabled['softDelete'] ? "function switchMode(value: boolean) { query.recycled = value ? 1 : 0; query.page = 1; void loadData(); }\nasync function removeRow(row: {$type}) { await ElMessageBox.confirm('确认删除该记录？', '删除确认', { type: 'warning' }); await {$camel}Api.remove(row.{$primaryName}); await loadData(); }\nasync function restoreRow(row: {$type}) { await {$camel}Api.restore(row.{$primaryName}); await loadData(); }\nasync function forceDeleteRow(row: {$type}) { await ElMessageBox.confirm('确认永久删除该记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await {$camel}Api.forceDelete(row.{$primaryName}); await loadData(); }\n" : '')
            . ($enabled['batchDelete'] ? "async function restoreSelected() { await {$camel}Api.restoreMany(selectedIds()); await loadData(); }\nasync function forceDeleteSelected() { await ElMessageBox.confirm('确认永久删除选中记录？此操作不可恢复。', '永久删除确认', { type: 'error' }); await {$camel}Api.forceDeleteMany(selectedIds()); await loadData(); }\n" : '')
            . ($enabled['status'] ? "async function changeStatus(row: {$type}, enabled: boolean) { await {$camel}Api.status(row.{$primaryName}, enabled ? 1 : 0); await loadData(); }\n" : '')
            . ($enabled['import'] ? "async function importCsv(event: Event) { const input = event.target as HTMLInputElement; const file = input.files?.[0]; input.value = ''; if (!file) return; const rows = parseCsv<{$type}Payload>(await readFileAsText(file), csvColumns); await {$camel}Api.importRows(rows); await loadData(); }\n" : '')
            . ($enabled['export'] ? "async function exportRows() { const rows = await {$camel}Api.exportRows(query); downloadCsv('{$data['entity']}-export', toCsv(rows, csvColumns as CsvColumn<{$type}>[])); }\n" : '')
            . "</script>\n";
    }

    private static function form(array $data, string $class): string
    {
        $camel = self::camel($class);
        $type = self::tsTypeName($class);
        $enabled = self::enabledCapabilities($data);
        $tag = $data['features']['formMode'] === 'drawer' ? 'el-drawer' : 'el-dialog';
        $fields = [];
        $optionNames = [];
        $usesUpload = false;
        $enabledOptionSources = array_column(self::enabledOptionSources($data, $enabled), null, 'name');
        foreach ($data['fields'] as $field) {
            if (!($field['form'] ?? false) || ($field['primary'] ?? false) || !($field['writable'] ?? true)) {
                continue;
            }
            $key = self::camel($field['name']);
            $label = htmlspecialchars($field['label'] ?? $field['comment'] ?? $field['name'], ENT_QUOTES);
            if (($field['upload'] ?? false) === true && $enabled['upload']) {
                $usesUpload = true;
                $fieldName = strtolower((string) $field['name']);
                $component = strtolower((string) ($field['component'] ?? ''));
                $multiple = in_array($component, ['images', 'files'], true)
                    || preg_match('/(?:^|_)(?:images|files)$/', $fieldName) === 1;
                $image = in_array($component, ['image', 'images'], true)
                    || preg_match('/(?:^|_)(?:image|images|avatar|thumb)(?:_|$)/', $fieldName) === 1;
                $uploadType = $image ? ($multiple ? 'images' : 'image') : 'file';
                $bizType = $image ? 'image' : 'file';
                $control = "<Upload v-model=\"form.{$key}\" type=\"{$uploadType}\" biz-type=\"{$bizType}\" />";
            } elseif (($field['optionsSource'] ?? '') !== '' && isset($enabledOptionSources[$field['optionsSource']])) {
                $source = (string) $field['optionsSource'];
                $optionNames[$source] = true;
                $control = "<el-select v-model=\"form.{$key}\" filterable clearable class=\"w-full\"><el-option v-for=\"item in optionLists.{$source}\" :key=\"item.value\" :label=\"item.label\" :value=\"item.value\" /></el-select>";
            } elseif (isset($field['enum'])) {
                $control = "<el-select v-model=\"form.{$key}\" class=\"w-full\">";
                foreach ($field['enum'] as $value) {
                    $literal = htmlspecialchars((string) $value, ENT_QUOTES);
                    $control .= "<el-option label=\"{$literal}\" :value=\"" . self::json($value) . "\" />";
                }
                $control .= '</el-select>';
            } else {
                $control = "<el-input v-model=\"form.{$key}\" />";
            }
            $fields[] = "<el-form-item label=\"{$label}\" prop=\"{$key}\">{$control}</el-form-item>";
        }
        $optionNames = array_keys($optionNames);
        $optionState = [];
        foreach ($optionNames as $name) {
            $optionState[] = "{$name}: []";
        }
        $loadOptions = $optionNames === []
            ? ''
            : "async function loadOptions() { await Promise.all(" . self::json($optionNames) . ".map(async source => { optionLists[source] = await {$camel}Api.options(source); })); }\n";
        $loadOptionsWhenOpen = $optionNames === [] ? '' : ' if (open) void loadOptions();';
        $uploadImport = $usesUpload ? "import Upload from '@/components/Upload/index.vue';\n" : '';
        return "<template><{$tag} v-model=\"visible\" title=\"编辑\"><el-form :model=\"form\">"
            . implode('', $fields)
            . "</el-form><template #footer><el-button @click=\"visible=false\">取消</el-button><el-button type=\"primary\" @click=\"submit\">保存</el-button></template></{$tag}></template>\n"
            . "<script setup lang=\"ts\">\nimport { computed, reactive, watch } from 'vue';\n{$uploadImport}"
            . "import { {$camel}Api, type {$type}, type {$type}Payload } from '@/api/generated/{$data['entity']}';\n"
            . "const props = defineProps<{ modelValue: boolean; row: {$type} | null }>();\nconst emit = defineEmits<{ 'update:modelValue': [boolean]; success: [] }>();\n"
            . "const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });\nconst form = reactive<{$type}Payload>({});\n"
            . "const optionLists = reactive<Record<string, Array<{ label: string; value: string | number }>>>({ " . implode(', ', $optionState) . " });\n"
            . $loadOptions
            . "watch(() => [props.row, props.modelValue] as const, ([row, open]) => { Object.keys(form).forEach(key => delete form[key as keyof {$type}Payload]); Object.assign(form, row || {});{$loadOptionsWhenOpen} }, { immediate: true });\n"
            . "async function submit() { "
            . ($enabled['update'] ? "if (props.row) await {$camel}Api.update(props.row." . self::camel(self::primary($data)['name']) . ", form); " : '')
            . ($enabled['create'] ? ($enabled['update'] ? "else " : '') . "await {$camel}Api.create(form); " : '')
            . "visible.value = false; emit('success'); }\n</script>\n";
    }

    private static function detail(array $data, string $class): string
    {
        $type = self::tsTypeName($class);
        return "<template><el-drawer v-model=\"visible\" title=\"详情\"><el-descriptions v-if=\"row\" :column=\"1\"><el-descriptions-item v-for=\"(value, key) in row\" :key=\"key\" :label=\"String(key)\"><span v-text=\"String(value ?? '')\" /></el-descriptions-item></el-descriptions></el-drawer></template>\n"
            . "<script setup lang=\"ts\">import { computed } from 'vue'; import type { {$type} } from '@/api/generated/{$data['entity']}'; const props=defineProps<{modelValue:boolean;row:{$type}|null}>(); const emit=defineEmits<{ 'update:modelValue':[boolean] }>(); const visible=computed({get:()=>props.modelValue,set:value=>emit('update:modelValue',value)});</script>\n";
    }

    private static function phpTest(array $data, string $class): string
    {
        return "<?php\n\ndeclare(strict_types=1);\n\nuse app\\console\\model\\{$class};\n\n"
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
        $softDelete = $enabled('delete') && ($features['softDelete'] ?? false);
        $dictionary = $enabled('form') && ($features['dictionary'] ?? false);
        $optionSources = self::enabledOptionSources($data, ['dictionary' => $dictionary]);
        return [
            'list' => $enabled('list'),
            'search' => $enabled('search') && $enabled('list'),
            'detail' => $enabled('detail') && ($features['detail'] ?? true),
            'create' => $enabled('create'),
            'update' => $enabled('update'),
            'softDelete' => $softDelete,
            'batchDelete' => $softDelete && ($features['batchDelete'] ?? false),
            'status' => $enabled('update') && ($features['status'] ?? false),
            'import' => $enabled('import') && ($features['import'] ?? false),
            'export' => $enabled('export') && ($features['export'] ?? false),
            'upload' => $enabled('form') && ($features['upload'] ?? false),
            'dictionary' => $dictionary,
            'options' => $enabled('form') && $optionSources !== [],
            'serverOptions' => $enabled('form') && array_filter(
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
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string) $value;
        return "'" . str_replace("'", "''", (string) $value) . "'";
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
