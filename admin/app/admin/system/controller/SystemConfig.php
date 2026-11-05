<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\common\model\Config;
use app\common\model\ConfigGroup;
use app\common\model\FieldType;
use app\common\model\FieldVerify;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Response;

/**
 * Admin Web 运行时配置定义、分组和值管理。
 */
#[RouteGroup('/admin/system')]
class SystemConfig extends AdminApiController
{

    #[Get('/config')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Config::orderBy('id', 'asc');
        $keyword = trim((string) $this->request->get('keyword', ''));
        $group = trim((string) $this->request->get('group', ''));
        $type = trim((string) $this->request->get('type', ''));
        $status = $this->request->get('status', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword): void {
                $where->whereLike('code', '%' . $keyword . '%')->orWhere('remark', 'like', '%' . $keyword . '%');
            });
        }
        if ($group !== '') {
            $query->where('group', $group);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        $result = $query->paginate($pageSize, ['*'], 'page', $page);
        return $this->ok(data: $this->paginationData(
            array_map(fn (Config $config): array => $this->configData($config), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get('/config/{id:\d+}')]
    public function detail(int $id): Response
    {
        $config = Config::find($id);
        return $config ? $this->ok(data: $this->configData($config)) : $this->fail(msg: '配置项不存在', code: 404);
    }

    #[Get('/config/options')]
    public function options(): Response
    {
        $groups = ConfigGroup::orderBy('id', 'asc')->get();
        $types = FieldType::where('status', 1)->orderBy('sort_order', 'asc')->orderBy('id', 'asc')->get();
        $verifies = FieldVerify::orderBy('verify', 'asc')->get();
        $builtInTypes = [
            'date' => ['title' => '日期', 'requiresOptions' => false],
            'json' => ['title' => 'JSON', 'requiresOptions' => false],
        ];
        $typeOptions = [];
        foreach ($types as $type) {
            $name = trim((string) $type->getAttribute('name'));
            if ($name === '' || isset($typeOptions[$name])) {
                continue;
            }
            $typeOptions[$name] = [
                'name' => $name,
                'title' => trim((string) $type->title) ?: $name,
                'requiresOptions' => (int) $type->isoption === 1,
            ];
        }
        foreach ($builtInTypes as $name => $type) {
            $typeOptions[$name] ??= ['name' => $name] + $type;
        }
        return $this->ok(data: [
            'groups' => array_map(fn (ConfigGroup $group): array => $this->groupData($group), $groups->all()),
            'types' => array_values($typeOptions),
            'verifies' => array_values(array_map(static fn (FieldVerify $verify): array => [
                'value' => (string) $verify->verify,
                'title' => (string) $verify->title,
            ], $verifies->all())),
        ]);
    }

    #[Post('/config')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Config::withTrashed()->where('code', $data['code'])->first()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config = Config::forceCreate($data + ['is_system' => 0]);
        $this->clearConfigCache();
        return $this->ok('创建成功', $this->configData($config));
    }

    #[Put('/config/{id:\d+}')]
    public function update(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        $data = $this->payload($config);
        if ((string) $config->type === 'password' && $data['value'] === '') {
            $data['value'] = (string) $config->value;
        }
        if ((int) $config->is_system === 1 && $data['code'] !== (string) $config->code) {
            return $this->fail(msg: '系统配置编码不能修改', code: 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Config::withTrashed()->where('code', $data['code'])->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config->forceFill($data)->save();
        $this->clearConfigCache();
        return $this->ok('保存成功', $this->configData($config));
    }

    #[Put('/config/{id:\d+}/value')]
    public function value(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        [$value, $error] = $this->normalizeValue((string) $config->type, $this->request->post('value', ''), (string) ($config->extra ?? ''));
        if ($error === null) $error = $this->validateRule((string) ($config->verify ?? ''), $value);
        if ($error) {
            return $this->fail(msg: $error, code: 422);
        }
        $config->forceFill(['value' => $value])->save();
        $this->clearConfigCache();
        return $this->ok('配置值已更新', $this->configData($config));
    }

    #[Post('/config/{id:\d+}/status')]
    public function status(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        $status = $this->request->post()['status'] ?? null;
        if (!in_array($status, [0, 1, '0', '1', true, false], true)) return $this->fail(msg: '状态必须为 0 或 1', code: 422);
        $config->forceFill(['status' => (int) $status])->save();
        $this->clearConfigCache();
        return $this->ok('状态更新成功', $this->configData($config));
    }

    #[Delete('/config')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的配置项', code: 422);
        }
        $configs = Config::whereIn('id', $ids)->get();
        if (count($configs) !== count($ids)) {
            return $this->fail(msg: '部分配置项不存在', code: 404);
        }
        foreach ($configs as $config) {
            if ((int) $config->is_system === 1) {
                return $this->fail(msg: '系统配置不能删除', code: 422);
            }
        }
        foreach ($configs as $config) {
            $config->forceDelete();
        }
        $this->clearConfigCache();
        return $this->ok('删除成功', ['removed' => count($configs)]);
    }

    #[Get('/config-group')]
    public function groups(): Response
    {
        $groups = ConfigGroup::orderBy('id', 'asc')->get();
        return $this->ok(data: array_map(fn (ConfigGroup $group): array => $this->groupData($group), $groups->all()));
    }

    #[Post('/config-group')]
    public function createGroup(): Response
    {
        $data = $this->groupPayload();
        if ($error = $this->validateGroup($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->first()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group = ConfigGroup::forceCreate($data);
            $this->clearConfigCache();
            return $this->ok('创建成功', $this->groupData($group));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '配置分组编码已存在', code: 422);
            }
            throw $exception;
        }
    }

    #[Put('/config-group/{id:\d+}')]
    public function updateGroup(int $id): Response
    {
        $group = ConfigGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '配置分组不存在', code: 404);
        }
        $data = $this->groupPayload($group);
        if ($error = $this->validateGroup($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if ($data['name'] !== (string) $group->getAttribute('name') && Config::withTrashed()->where('group', (string) $group->getAttribute('name'))->count() > 0) {
            return $this->fail(msg: '配置分组已被配置项引用，不能修改编码', code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->first()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group->forceFill($data)->save();
            $this->clearConfigCache();
            return $this->ok('保存成功', $this->groupData($group));
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry')) {
                return $this->fail(msg: '配置分组编码已存在', code: 422);
            }
            throw $exception;
        }
    }

    #[Delete('/config-group/{id:\d+}')]
    public function deleteGroup(int $id): Response
    {
        $group = ConfigGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '配置分组不存在', code: 404);
        }
        if (Config::withTrashed()->where('group', (string) $group->getAttribute('name'))->count() > 0) {
            return $this->fail(msg: '配置分组仍被配置项引用，不能删除', code: 422);
        }
        $group->forceDelete();
        $this->clearConfigCache();
        return $this->ok('删除成功');
    }

    private function payload(?Config $config = null): array
    {
        return array_replace([
            'code' => $config?->code ?? '',
            'group' => $config?->group ?? 'site',
            'type' => $config?->type ?? 'text',
            'verify' => $config?->verify ?? '',
            'value' => $config?->value ?? '',
            'extra' => $config?->extra ?? '',
            'remark' => $config?->remark ?? '',
            'status' => $config?->status ?? 1,
        ], array_intersect_key($this->request->post(), array_flip(['code', 'group', 'type', 'verify', 'value', 'extra', 'remark', 'status'])));
    }

    private function validatePayload(array &$data): ?string
    {
        foreach (['code', 'group', 'type', 'verify', 'extra', 'remark'] as $field) {
            if (!is_string($data[$field])) return '配置文本参数必须为字符串';
            $data[$field] = trim($data[$field]);
        }
        if (!in_array($data['status'], [0, 1, '0', '1', true, false], true)) return '状态必须为 0 或 1';
        $data['status'] = (int) $data['status'];
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,29}$/', $data['code'])) {
            return '配置编码必须以字母开头，且只能包含字母、数字、点、横线和下划线，最长 30 位';
        }
        if ($data['group'] === '' || strlen($data['group']) > 80 || !ConfigGroup::where('name', $data['group'])->first()) {
            return '请选择有效的配置分组';
        }
        $builtInTypes = ['date', 'json'];
        $typeExists = in_array($data['type'], $builtInTypes, true)
            || FieldType::where('name', $data['type'])->where('status', 1)->first();
        if ($data['type'] === '' || strlen($data['type']) > 30 || !$typeExists) {
            return '请选择有效的字段类型';
        }
        if (strlen($data['verify']) > 30 || ($data['verify'] !== '' && $data['verify'] !== '0' && !FieldVerify::where('verify', $data['verify'])->first())) {
            return '请选择有效的验证规则';
        }
        if (strlen($data['extra']) > 255) {
            return '选项定义不能超过 255 个字符';
        }
        if ((function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark'])) > 100) {
            return '配置备注不能超过 100 个字符';
        }
        [$data['value'], $error] = $this->normalizeValue($data['type'], $data['value'], $data['extra']);
        if ($error !== null) return $error;
        return $this->validateRule($data['verify'], $data['value']);
    }

    private function normalizeValue(string $type, mixed $raw, string $extra): array
    {
        $multiple = in_array($type, ['checkbox', 'array', 'tags', 'images', 'files'], true);
        if ((!$multiple && !is_scalar($raw) && $raw !== null)
            || ($multiple && is_array($raw) && count(array_filter($raw, static fn ($item): bool => !is_scalar($item))) > 0)
            || ($multiple && !is_array($raw) && !is_scalar($raw) && $raw !== null)) {
            return ['', '配置值格式不合法'];
        }
        if (in_array($type, ['checkbox', 'array', 'tags', 'images', 'files'], true)) {
            $items = is_array($raw) ? $raw : preg_split('/[\r\n,]+/', (string) $raw);
            $value = implode("\n", array_values(array_unique(array_filter(array_map(static fn ($item): string => trim((string) $item), $items), static fn (string $item): bool => $item !== ''))));
        } elseif ($type === 'switch') {
            $value = in_array($raw, [1, '1', true, 'true', 'on'], true) ? '1' : '0';
        } else {
            $value = is_scalar($raw) || $raw === null ? (string) $raw : '';
        }
        if ($type === 'number' && $value !== '' && !preg_match('/^-?\d+$/', $value)) {
            return ['', '配置值必须为整数'];
        }
        if (in_array($type, ['float', 'decimal'], true) && $value !== '' && !is_numeric($value)) {
            return ['', '配置值必须为数字'];
        }
        if ($type === 'json' && $value !== '') {
            json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['', '配置值必须是合法的 JSON'];
            }
        }
        if (in_array($type, ['date', 'datetime'], true) && $value !== '') {
            $format = $type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s';
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);
            if (!$date || $date->format($format) !== $value) {
                return ['', $type === 'date' ? '配置值必须是有效日期' : '配置值必须是有效日期时间'];
            }
        }
        if ($type === 'range' && $value !== '') {
            $range = preg_split('/\s+-\s+/', $value, 2);
            if (count($range) !== 2) {
                return ['', '配置值必须是有效日期时间范围'];
            }
            foreach ($range as $item) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $item);
                if (!$date || $date->format('Y-m-d H:i:s') !== $item) {
                    return ['', '配置值必须是有效日期时间范围'];
                }
            }
        }
        if (in_array($type, ['radio', 'select', 'checkbox'], true) && $extra !== '') {
            $allowed = array_map('strval', array_keys($this->parseOptions($extra)));
            $values = $type === 'checkbox' ? array_filter(explode("\n", $value), static fn (string $item): bool => $item !== '') : [$value];
            foreach ($values as $item) {
                if ($item !== '' && !in_array($item, $allowed, true)) {
                    return ['', '配置值不在允许的选项范围内'];
                }
            }
        }
        return [$value, null];
    }

    private function validateRule(string $verify, string $value): ?string
    {
        if ($verify === '' || $verify === '0') return null;
        if ($verify === 'required' && trim($value) === '') return '配置值不能为空';
        if ($value === '') return null;
        return match ($verify) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : '配置值必须是合法邮箱',
            'url' => filter_var($value, FILTER_VALIDATE_URL) ? null : '配置值必须是合法地址',
            'number' => preg_match('/^-?\d+(?:\.\d+)?$/', $value) === 1 ? null : '配置值必须是数字',
            default => null,
        };
    }

    private function parseOptions(string $extra): array
    {
        $options = [];
        foreach (preg_split('/\r?\n/', $extra) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$value, $label] = array_pad(explode(':', $line, 2), 2, $line);
            $options[trim($value)] = trim($label);
        }
        return $options;
    }

    private function groupPayload(?ConfigGroup $group = null): array
    {
        return array_replace([
            'name' => $group?->getAttribute('name') ?? '',
            'title' => $group?->title ?? '',
            'status' => $group?->status ?? 1,
        ], array_intersect_key($this->request->post(), array_flip(['name', 'title', 'status'])));
    }

    private function validateGroup(array $data): ?string
    {
        if (!is_string($data['name']) || !is_string($data['title'])) return '分组文本参数必须为字符串';
        if (!in_array($data['status'], [0, 1, '0', '1', true, false], true)) return '状态必须为 0 或 1';
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,29}$/', $data['name'])) {
            return '分组编码必须以字母开头，且只能包含字母、数字、横线和下划线，最长 30 位';
        }
        if ($data['title'] === '' || (function_exists('mb_strlen') ? mb_strlen($data['title']) : strlen($data['title'])) > 60) {
            return '分组标题不能为空且不能超过 60 个字符';
        }
        return null;
    }

    private function configData(Config $config): array
    {
        return [
            'id' => (int) $config->id,
            'code' => (string) $config->code,
            'group' => (string) $config->group,
            'type' => (string) $config->type,
            'verify' => (string) ($config->verify ?? ''),
            'value' => in_array((string) $config->type, ['password', 'hidden'], true) ? '' : (string) ($config->value ?? ''),
            'extra' => (string) ($config->extra ?? ''),
            'remark' => (string) ($config->remark ?? ''),
            'status' => (int) $config->status,
            'isSystem' => (int) $config->is_system === 1 ? 1 : 0,
            'createdAt' => $this->formatTime($config->created_at),
            'updatedAt' => $this->formatTime($config->updated_at),
        ];
    }

    private function groupData(ConfigGroup $group): array
    {
        return [
            'id' => (int) $group->id,
            'name' => (string) $group->getAttribute('name'),
            'title' => (string) $group->title,
            'status' => (int) $group->status,
            'createdAt' => $this->formatTime($group->created_at),
            'updatedAt' => $this->formatTime($group->updated_at),
        ];
    }

    private function clearConfigCache(): void
    {
        Cache::clear();
    }
}
