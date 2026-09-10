<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\BusinessModule;
use app\console\model\CrudGeneration;
use app\console\model\Form;
use InvalidArgumentException;
use think\facade\Db;

/** 业务开发模块与生成记录的数据访问边界。 */
final class BusinessModuleService
{
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
        return ['list' => $result->items(), 'total' => $result->total(), 'page' => $page, 'pageSize' => $pageSize];
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
        return Db::transaction(function () use ($formPayload, $origin, $actor, $forms): array {
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
                'metadata' => ['createdBy' => $actor],
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
        unset($row['confirm_token'], $row['trusted_bundle']);
        return $row;
    }
}

final class BusinessResourceGoneException extends InvalidArgumentException
{
}
