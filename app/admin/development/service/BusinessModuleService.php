<?php

declare(strict_types=1);

namespace app\admin\development\service;

use app\admin\development\model\BusinessModule;
use app\admin\development\model\CrudGeneration;
use app\admin\development\http\BusinessResponseSanitizer;
use app\admin\form\model\Form;
use app\admin\form\service\FormDesignerService;
use InvalidArgumentException;
use think\facade\Db;

/** 业务开发模块与生成记录的数据访问边界。 */
final class BusinessModuleService
{
    public function __construct(private readonly ?BusinessTargetService $targets = null)
    {
    }

    /** 仅规范化目标选择；授权及插件状态必须由调用入口另行校验。 */
    public static function normalizeTarget(array $input, string $source): array
    {
        if (array_diff(array_keys($input), ['type', 'pluginCode']) !== []) {
            throw new InvalidArgumentException('目标配置包含非受控字段');
        }
        if (!in_array($source, ['created', 'adopted'], true)) {
            throw new InvalidArgumentException('表来源不合法');
        }
        $type = $input['type'] ?? 'core';
        if (!in_array($type, ['core', 'plugin'], true)) {
            throw new InvalidArgumentException('目标类型必须为 core 或 plugin');
        }
        $code = $input['pluginCode'] ?? null;
        if ($type === 'plugin') {
            if (!is_string($code) || preg_match('/^[a-z][a-z0-9]*$/D', $code) !== 1) {
                throw new InvalidArgumentException('插件标识不合法');
            }
        } elseif ($code !== null) {
            throw new InvalidArgumentException('核心目标不能指定插件');
        }
        return [
            'type' => $type,
            'pluginCode' => $code,
            'scope' => 'admin',
            'tableStrategy' => $source === 'adopted' ? 'external' : 'owned',
            'locked' => false,
        ];
    }

    public function listing(int $page, int $pageSize, string $keyword, string $status, string $origin): array
    {
        $query = BusinessModule::order('updated_at', 'desc')->order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($builder) use ($keyword): void {
                $builder->whereLike('name', '%' . $keyword . '%')->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '') $query->where('lifecycle_status', $status);
        if ($origin !== '') $query->where('origin', $origin);
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $list = array_map(static fn ($module): array => $module->toArray(), $result->items());
        if ($list !== []) {
            // 成功指针不代表最新尝试；按本页模块批量投影生成状态，不暴露制品内容。
            $latestIds = CrudGeneration::whereIn('business_module_id', array_column($list, 'id'))
                ->group('business_module_id')->column('MAX(id)');
            $states = CrudGeneration::whereIn('id', $latestIds)
                ->field('business_module_id,status,recovery_status')->select()->toArray();
            $states = array_column($states, null, 'business_module_id');
            foreach ($list as &$module) {
                $state = $states[$module['id']] ?? null;
                $module['recovery_status'] = (string) ($state['recovery_status'] ?? 'none');
                if ($state !== null) $module['generation_status'] = (string) $state['status'];
            }
            unset($module);
        }
        return ['list' => $list, 'total' => $result->total(), 'page' => $page, 'pageSize' => $pageSize];
    }

    /** 设计目录只读取模块身份，不加载字段、默认值或发布版本。 */
    public function designTarget(int $id): string
    {
        $module = BusinessModule::where('id', $id)->field('id,metadata')->find();
        if (!$module) throw new BusinessResourceGoneException('业务模块不存在或已删除');
        return (string) ($module->metadata['target']['type'] ?? 'core');
    }

    public function detail(int $id): array
    {
        $module = BusinessModule::find($id);
        if (!$module) throw new BusinessResourceGoneException('业务模块不存在或已删除');
        $form = $module->form_id ? Form::find((int) $module->form_id) : null;
        return ['module' => $module->toArray(), 'form' => $form?->toArray(), 'fields' => $form ? $form->fields()->order('sort_order')->select()->toArray() : []];
    }

    public function createWithForm(array $formPayload, string $origin, string $actor, FormDesignerService $forms): array
    {
        $target = $formPayload['business_target'] ?? self::normalizeTarget([], (string) $formPayload['source_type']);
        ($this->targets ?? new BusinessTargetService(root_path(), (string) config('database.default', 'mysql')))
            ->assertSelection($target, (string) $formPayload['connection'], (string) $formPayload['table_name']);
        unset($formPayload['business_target']);
        return Db::transaction(function () use ($formPayload, $origin, $actor, $forms, $target): array {
            $saved = $forms->save($formPayload);
            $form = $saved['form'];
            $formId = (int) (is_object($form) ? $form->id : $form['id']);
            $code = (string) $formPayload['form_key'];
            if (BusinessModule::where('code', $code)->find()) throw new InvalidArgumentException('业务模块标识已存在');
            $module = BusinessModule::create([
                'code' => $code,
                'name' => (string) $formPayload['name'],
                'form_id' => $formId,
                'origin' => $origin,
                'connection_name' => (string) ($formPayload['connection'] ?? 'mysql'),
                'table_name' => (string) $formPayload['table_name'],
                'runtime_route' => '/development/business/runtime/' . $code,
                'module_route' => '/development/business/' . $code,
                'lifecycle_status' => 'draft',
                'generation_status' => 'idle',
                'metadata' => ['createdBy' => $actor, 'target' => $target],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Form::where('id', $formId)->update(['business_module_id' => (int) $module->id]);
            return $this->detail((int) $module->id);
        });
    }

    public function generations(int $page, int $pageSize, int $moduleId = 0, string $status = ''): array
    {
        $query = CrudGeneration::order('id', 'desc');
        if ($moduleId > 0) $query->where('business_module_id', $moduleId);
        if ($status !== '') $query->where('status', $status);
        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        return ['list' => array_map([$this, 'sanitizeGeneration'], $result->items()), 'total' => $result->total(), 'page' => $page, 'pageSize' => $pageSize];
    }

    public function generation(int $id): array
    {
        $record = CrudGeneration::find($id);
        if (!$record) throw new BusinessResourceGoneException('生成记录不存在或已删除');
        return $this->sanitizeGeneration($record->toArray());
    }

    private function sanitizeGeneration(mixed $record): array
    {
        $row = is_object($record) && method_exists($record, 'toArray') ? $record->toArray() : (array) $record;
        $recoveryStatus = (string) ($row['recovery_status'] ?? 'none');
        $row['businessModuleId'] = isset($row['business_module_id']) ? (int) $row['business_module_id'] : null;
        $row['generationMode'] = (string) ($row['generation_mode'] ?? '');
        $row['recoveryStatus'] = $recoveryStatus;
        $row['planDigest'] = $row['plan_digest'] ?? null;
        $row['definitionHash'] = (string) ($row['definition_hash'] ?? '');
        $row['createdAt'] = $row['created_at'] ?? null;
        $row['updatedAt'] = $row['updated_at'] ?? null;
        $row['availableActions'] = (string) ($row['status'] ?? '') === 'failed' && $recoveryStatus === 'recovery_required'
            ? ['recover']
            : [];
        // 历史记录不继承预览的专用授权，持久化三方内容仅用于受控冲突处理。
        foreach ($row['manifest']['plan']['files'] ?? [] as $index => $file) {
            unset($row['manifest']['plan']['files'][$index]['baseContent'],
                $row['manifest']['plan']['files'][$index]['localContent'],
                $row['manifest']['plan']['files'][$index]['remoteContent']);
        }
        return BusinessResponseSanitizer::sanitize($row);
    }
}

final class BusinessResourceGoneException extends InvalidArgumentException
{
}
