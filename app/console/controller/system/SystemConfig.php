<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\common\model\Config;
use app\common\model\ConfigGroup;
use app\common\model\FieldType;
use app\common\model\FieldVerify;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;
use think\facade\Cache;

/**
 * Admin Web 运行时配置定义、分组和值管理。
 */
#[Group('system', ['complete_match' => true])]
class SystemConfig extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Get('config')]
    public function index(): Response
    {
        $page = $this->page();
        $pageSize = $this->pageSize();
        $query = Config::order('id', 'asc');
        $keyword = trim((string) $this->request->get('keyword', ''));
        $group = trim((string) $this->request->get('group', ''));
        $type = trim((string) $this->request->get('type', ''));
        $status = $this->request->get('status', null);
        if ($keyword !== '') {
            $query->where(function ($where) use ($keyword): void {
                $where->whereLike('code', '%' . $keyword . '%')->whereOr('remark', 'like', '%' . $keyword . '%');
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
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return $this->ok(data: $this->paginationData(
            array_map(fn (Config $config): array => $this->configData($config), $result->items()),
            $result->total(),
            $page,
            $pageSize
        ));
    }

    #[Get('config/:id')]
    #[Pattern('id', '\\d+')]
    public function detail(int $id): Response
    {
        $config = Config::find($id);
        return $config ? $this->ok(data: $this->configData($config)) : $this->fail(msg: '配置项不存在', code: 404);
    }

    #[Get('config/options')]
    public function options(): Response
    {
        $groups = ConfigGroup::order('id', 'asc')->select();
        $types = FieldType::where('status', 1)->order('sort_order', 'asc')->order('id', 'asc')->select();
        $verifies = FieldVerify::order('verify', 'asc')->select();
        $builtInTypes = [
            'json' => ['title' => 'JSON', 'requiresOptions' => false],
        ];
        $typeOptions = [];
        foreach ($types as $type) {
            $name = trim((string) $type->name);
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

    #[Post('config')]
    public function create(): Response
    {
        $data = $this->payload();
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Config::withTrashed()->where('code', $data['code'])->find()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config = Config::create($data + ['is_system' => 0]);
        $this->clearConfigCache();
        return $this->ok('创建成功', $this->configData($config));
    }

    #[Put('config/:id')]
    #[Pattern('id', '\\d+')]
    public function update(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        $data = $this->payload($config);
        if ((int) $config->is_system === 1 && $data['code'] !== (string) $config->code) {
            return $this->fail(msg: '系统配置编码不能修改', code: 422);
        }
        if ($error = $this->validatePayload($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (Config::withTrashed()->where('code', $data['code'])->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '配置编码已存在', code: 422);
        }
        $config->save($data);
        $this->clearConfigCache();
        return $this->ok('保存成功', $this->configData($config));
    }

    #[Put('config/:id/value')]
    #[Pattern('id', '\\d+')]
    public function value(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        [$value, $error] = $this->normalizeValue((string) $config->type, $this->request->post('value', ''), (string) ($config->extra ?? ''));
        if ($error) {
            return $this->fail(msg: $error, code: 422);
        }
        $config->save(['value' => $value]);
        $this->clearConfigCache();
        return $this->ok('配置值已更新', $this->configData($config));
    }

    #[Post('config/:id/status')]
    #[Pattern('id', '\\d+')]
    public function status(int $id): Response
    {
        $config = Config::find($id);
        if (!$config) {
            return $this->fail(msg: '配置项不存在', code: 404);
        }
        $config->save(['status' => $this->binaryStatus($this->request->post('status', 0))]);
        $this->clearConfigCache();
        return $this->ok('状态更新成功', $this->configData($config));
    }

    #[Delete('config')]
    public function delete(int $id = 0): Response
    {
        $ids = $this->ids();
        if (!$ids && $id > 0) {
            $ids = [$id];
        }
        if (!$ids) {
            return $this->fail(msg: '请选择要删除的配置项', code: 422);
        }
        $configs = Config::whereIn('id', $ids)->select();
        if (count($configs) !== count($ids)) {
            return $this->fail(msg: '部分配置项不存在', code: 404);
        }
        foreach ($configs as $config) {
            if ((int) $config->is_system === 1) {
                return $this->fail(msg: '系统配置不能删除', code: 422);
            }
        }
        foreach ($configs as $config) {
            $config->force()->delete();
        }
        $this->clearConfigCache();
        return $this->ok('删除成功', ['removed' => count($configs)]);
    }

    #[Get('config-group')]
    public function groups(): Response
    {
        $groups = ConfigGroup::order('id', 'asc')->select();
        return $this->ok(data: array_map(fn (ConfigGroup $group): array => $this->groupData($group), $groups->all()));
    }

    #[Post('config-group')]
    public function createGroup(): Response
    {
        $data = $this->groupPayload();
        if ($error = $this->validateGroup($data)) {
            return $this->fail(msg: $error, code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->find()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group = ConfigGroup::create($data);
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

    #[Put('config-group/:id')]
    #[Pattern('id', '\\d+')]
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
        if ($data['name'] !== (string) $group->name && Config::withTrashed()->where('group', (string) $group->name)->count() > 0) {
            return $this->fail(msg: '配置分组已被配置项引用，不能修改编码', code: 422);
        }
        if (ConfigGroup::withTrashed()->where('name', $data['name'])->where('id', '<>', $id)->find()) {
            return $this->fail(msg: '配置分组编码已存在', code: 422);
        }
        try {
            $group->save($data);
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

    #[Delete('config-group/:id')]
    #[Pattern('id', '\\d+')]
    public function deleteGroup(int $id): Response
    {
        $group = ConfigGroup::find($id);
        if (!$group) {
            return $this->fail(msg: '配置分组不存在', code: 404);
        }
        if (Config::withTrashed()->where('group', (string) $group->name)->count() > 0) {
            return $this->fail(msg: '配置分组仍被配置项引用，不能删除', code: 422);
        }
        $group->force()->delete();
        $this->clearConfigCache();
        return $this->ok('删除成功');
    }

    private function payload(?Config $config = null): array
    {
        return [
            'code' => trim((string) $this->request->post('code', $config?->code ?? '')),
            'group' => trim((string) $this->request->post('group', $config?->group ?? 'site')),
            'type' => trim((string) $this->request->post('type', $config?->type ?? 'text')),
            'verify' => trim((string) $this->request->post('verify', $config?->verify ?? '')),
            'value' => (string) $this->request->post('value', $config?->value ?? ''),
            'extra' => trim((string) $this->request->post('extra', $config?->extra ?? '')),
            'remark' => trim((string) $this->request->post('remark', $config?->remark ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $config?->status ?? 1)),
        ];
    }

    private function validatePayload(array &$data): ?string
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,29}$/', $data['code'])) {
            return '配置编码必须以字母开头，且只能包含字母、数字、点、横线和下划线，最长 30 位';
        }
        if ($data['group'] === '' || strlen($data['group']) > 80 || !ConfigGroup::where('name', $data['group'])->find()) {
            return '请选择有效的配置分组';
        }
        $builtInTypes = ['json'];
        $typeExists = in_array($data['type'], $builtInTypes, true)
            || FieldType::where('name', $data['type'])->where('status', 1)->find();
        if ($data['type'] === '' || strlen($data['type']) > 30 || !$typeExists) {
            return '请选择有效的字段类型';
        }
        if (strlen($data['verify']) > 30 || ($data['verify'] !== '' && $data['verify'] !== '0' && !FieldVerify::where('verify', $data['verify'])->find())) {
            return '请选择有效的验证规则';
        }
        if (strlen($data['extra']) > 255) {
            return '选项定义不能超过 255 个字符';
        }
        if ((function_exists('mb_strlen') ? mb_strlen($data['remark']) : strlen($data['remark'])) > 100) {
            return '配置备注不能超过 100 个字符';
        }
        [$data['value'], $error] = $this->normalizeValue($data['type'], $data['value'], $data['extra']);
        return $error;
    }

    private function normalizeValue(string $type, mixed $raw, string $extra): array
    {
        if (in_array($type, ['checkbox', 'images', 'files'], true)) {
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
        if (in_array($type, ['radio', 'select', 'checkbox'], true) && $extra !== '') {
            $allowed = array_keys($this->parseOptions($extra));
            $values = $type === 'checkbox' ? array_filter(explode("\n", $value)) : [$value];
            foreach ($values as $item) {
                if ($item !== '' && !in_array($item, $allowed, true)) {
                    return ['', '配置值不在允许的选项范围内'];
                }
            }
        }
        return [$value, null];
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
        return [
            'name' => trim((string) $this->request->post('name', $group?->name ?? '')),
            'title' => trim((string) $this->request->post('title', $group?->title ?? '')),
            'status' => $this->binaryStatus($this->request->post('status', $group?->status ?? 1)),
        ];
    }

    private function validateGroup(array $data): ?string
    {
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
            'value' => (string) ($config->value ?? ''),
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
            'name' => (string) $group->name,
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
