<?php

namespace app\backend\controller;

use app\BaseController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\common\model\DictItem;
use app\common\model\DictType;
use think\App;
use think\Response;
use think\facade\Session;

/**
 * Admin Web 字典 REST API。
 *
 * 字典类型通过 code 对外暴露，字典项在数据库中只保存 type_id，避免重复数据。
 */
class SystemDict extends BaseController
{
    protected $middleware = [
        CheckAdminApiRole::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function types(): Response
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = $this->pageSize();
        $query = DictType::order('sort', 'asc')->order('id', 'asc');

        $name = trim((string) $this->request->get('name', ''));
        $code = trim((string) $this->request->get('code', ''));
        $status = $this->request->get('status', null);
        if ($name !== '') {
            $query->whereLike('name', '%' . $name . '%');
        }
        if ($code !== '') {
            $query->whereLike('code', '%' . $code . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $list = array_map(fn (DictType $type) => $this->typeData($type), $result->items());

        return $this->ok([
            'list' => $list,
            'total' => $result->total(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function createType(): Response
    {
        $data = $this->typePayload();
        if ($error = $this->validateTypePayload($data)) {
            return $this->fail($error, 422);
        }
        if (DictType::where('code', $data['code'])->find()) {
            return $this->fail('字典编码已存在', 422);
        }

        $type = DictType::create($data);
        return $this->ok($this->typeData($type), '创建成功');
    }

    public function updateType(int $id): Response
    {
        $type = DictType::find($id);
        if (!$type) {
            return $this->fail('字典类型不存在', 404);
        }

        $data = $this->typePayload(false);
        unset($data['code']);
        if ($error = $this->validateTypePayload($data, false)) {
            return $this->fail($error, 422);
        }

        $type->save($data);
        return $this->ok($this->typeData($type), '保存成功');
    }

    public function deleteType(int $id): Response
    {
        $type = DictType::find($id);
        if (!$type) {
            return $this->fail('字典类型不存在', 404);
        }
        if (DictItem::where('type_id', $id)->count() > 0) {
            return $this->fail('请先删除该类型下的字典项', 422);
        }

        $type->delete();
        return $this->ok(null, '删除成功');
    }

    public function deleteTypes(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要删除的字典类型', 422);
        }
        if (DictItem::whereIn('type_id', $ids)->count() > 0) {
            return $this->fail('所选类型中仍存在字典项，请先删除字典项', 422);
        }

        $types = DictType::whereIn('id', $ids)->select();
        foreach ($types as $type) {
            $type->delete();
        }
        return $this->ok(['removed' => count($types)], '删除成功');
    }

    public function items(): Response
    {
        $page = max(1, (int) $this->request->get('page', 1));
        $pageSize = $this->pageSize();
        $typeCode = trim((string) $this->request->get('typeCode', ''));
        $query = DictItem::order('sort', 'asc')->order('id', 'asc');

        if ($typeCode !== '') {
            $type = DictType::where('code', $typeCode)->find();
            if (!$type) {
                return $this->ok(['list' => [], 'total' => 0, 'page' => $page, 'pageSize' => $pageSize]);
            }
            $query->where('type_id', (int) $type->id);
        }

        $label = trim((string) $this->request->get('label', ''));
        $value = trim((string) $this->request->get('value', ''));
        $status = $this->request->get('status', null);
        if ($label !== '') {
            $query->whereLike('label', '%' . $label . '%');
        }
        if ($value !== '') {
            $query->whereLike('value', '%' . $value . '%');
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $result = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $items = $result->items();
        $typeIds = array_values(array_unique(array_map(static fn ($item) => (int) $item->type_id, $items)));
        $typeCodes = $typeIds ? DictType::whereIn('id', $typeIds)->column('code', 'id') : [];
        $list = array_map(function (DictItem $item) use ($typeCodes) {
            return $this->itemData($item, (string) ($typeCodes[(int) $item->type_id] ?? ''));
        }, $items);

        return $this->ok([
            'list' => $list,
            'total' => $result->total(),
            'page' => $page,
            'pageSize' => $pageSize,
        ]);
    }

    public function createItem(): Response
    {
        $data = $this->itemPayload();
        if ($error = $this->validateItemPayload($data)) {
            return $this->fail($error, 422);
        }

        $type = DictType::where('code', $data['typeCode'])->find();
        if (!$type) {
            return $this->fail('字典类型不存在', 422);
        }
        if (DictItem::where('type_id', $type->id)->where('value', $data['value'])->find()) {
            return $this->fail('同一字典类型下的字典值不能重复', 422);
        }

        unset($data['typeCode']);
        $data['type_id'] = (int) $type->id;
        $item = DictItem::create($data);
        return $this->ok($this->itemData($item, (string) $type->code), '创建成功');
    }

    public function updateItem(int $id): Response
    {
        $item = DictItem::find($id);
        if (!$item) {
            return $this->fail('字典项不存在', 404);
        }

        $data = $this->itemPayload(false);
        unset($data['typeCode']);
        if ($error = $this->validateItemPayload($data, false)) {
            return $this->fail($error, 422);
        }
        if (isset($data['value']) && DictItem::where('type_id', $item->type_id)
            ->where('value', $data['value'])
            ->where('id', '<>', $id)
            ->find()) {
            return $this->fail('同一字典类型下的字典值不能重复', 422);
        }

        $item->save($data);
        $type = DictType::find((int) $item->type_id);
        return $this->ok($this->itemData($item, $type ? (string) $type->code : ''), '保存成功');
    }

    public function deleteItem(int $id): Response
    {
        $item = DictItem::find($id);
        if (!$item) {
            return $this->fail('字典项不存在', 404);
        }

        $item->delete();
        return $this->ok(null, '删除成功');
    }

    public function deleteItems(): Response
    {
        $ids = $this->ids();
        if (!$ids) {
            return $this->fail('请选择要删除的字典项', 422);
        }

        $items = DictItem::whereIn('id', $ids)->select();
        foreach ($items as $item) {
            $item->delete();
        }
        return $this->ok(['removed' => count($items)], '删除成功');
    }

    public function options(string $code): Response
    {
        $type = DictType::where('code', $code)->where('status', 1)->find();
        if (!$type) {
            return $this->ok([]);
        }

        $items = DictItem::where('type_id', $type->id)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();

        return $this->ok(array_map(fn (DictItem $item) => $this->optionData($item), $items->all()));
    }

    public function batch(): Response
    {
        $codes = $this->request->param('codes', []);
        if (!is_array($codes)) {
            return $this->fail('codes 必须是数组', 422);
        }
        $codes = array_values(array_unique(array_filter(array_map(
            static fn ($code) => trim((string) $code),
            $codes
        ))));
        if (count($codes) > 50) {
            return $this->fail('单次最多查询 50 个字典', 422);
        }
        if (!$codes) {
            return $this->ok([]);
        }

        $types = DictType::whereIn('code', $codes)->where('status', 1)->select();
        $typeCodes = [];
        foreach ($types as $type) {
            $typeCodes[(int) $type->id] = (string) $type->code;
        }
        $result = array_fill_keys($codes, []);
        if (!$typeCodes) {
            return $this->ok($result);
        }

        $items = DictItem::whereIn('type_id', array_keys($typeCodes))
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();
        foreach ($items as $item) {
            $result[$typeCodes[(int) $item->type_id]][] = $this->optionData($item);
        }

        return $this->ok($result);
    }

    private function typePayload(bool $creating = true): array
    {
        $fields = $creating ? ['code', 'name', 'status', 'sort', 'remark'] : ['name', 'status', 'sort', 'remark'];
        return $this->onlyInput($fields, $creating ? [
            'code' => '',
            'name' => '',
            'status' => 1,
            'sort' => 0,
            'remark' => '',
        ] : []);
    }

    private function itemPayload(bool $creating = true): array
    {
        $fields = $creating
            ? ['typeCode', 'label', 'value', 'cssClass', 'status', 'sort', 'remark']
            : ['label', 'value', 'cssClass', 'status', 'sort', 'remark'];
        $data = $this->onlyInput($fields, $creating ? [
            'typeCode' => '',
            'label' => '',
            'value' => '',
            'cssClass' => '',
            'status' => 1,
            'sort' => 0,
            'remark' => '',
        ] : []);
        if (array_key_exists('cssClass', $data)) {
            $data['css_class'] = $data['cssClass'];
            unset($data['cssClass']);
        }
        return $data;
    }

    private function onlyInput(array $fields, array $defaults): array
    {
        $input = $this->request->param();
        $result = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input)) {
                $value = $input[$field];
                $result[$field] = is_string($value) ? trim($value) : $value;
            } elseif (array_key_exists($field, $defaults)) {
                $result[$field] = $defaults[$field];
            }
        }
        foreach (['status', 'sort'] as $field) {
            if (array_key_exists($field, $result)) {
                $result[$field] = max(0, (int) $result[$field]);
            }
        }
        if (isset($result['status'])) {
            $result['status'] = $result['status'] === 1 ? 1 : 0;
        }
        return $result;
    }

    private function validateTypePayload(array $data, bool $requireCode = true): ?string
    {
        if ($requireCode && (!isset($data['code']) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,59}$/', $data['code']))) {
            return '字典编码只能包含字母、数字、下划线，且必须以字母开头';
        }
        if (($requireCode && !isset($data['name'])) || (isset($data['name']) && ($data['name'] === '' || mb_strlen($data['name']) > 100))) {
            return '字典名称不能为空且不能超过 100 个字符';
        }
        if (isset($data['remark']) && mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    private function validateItemPayload(array $data, bool $requireType = true): ?string
    {
        if ($requireType && empty($data['typeCode'])) {
            return '请选择字典类型';
        }
        if (($requireType && !isset($data['label'])) || (isset($data['label']) && ($data['label'] === '' || mb_strlen($data['label']) > 100))) {
            return '字典标签不能为空且不能超过 100 个字符';
        }
        if (($requireType && !isset($data['value'])) || (isset($data['value']) && ($data['value'] === '' || mb_strlen($data['value']) > 100))) {
            return '字典值不能为空且不能超过 100 个字符';
        }
        if (isset($data['css_class']) && !in_array($data['css_class'], ['', 'primary', 'success', 'warning', 'danger', 'info'], true)) {
            return '样式属性不合法';
        }
        if (isset($data['remark']) && mb_strlen($data['remark']) > 255) {
            return '备注不能超过 255 个字符';
        }
        return null;
    }

    private function ids(): array
    {
        $ids = $this->request->param('ids', []);
        if (!is_array($ids)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn ($id) => $id > 0)));
    }

    private function pageSize(): int
    {
        return min(100, max(1, (int) $this->request->get('pageSize', 10)));
    }

    private function typeData(DictType $type): array
    {
        return [
            'id' => (int) $type->id,
            'code' => (string) $type->code,
            'name' => (string) $type->name,
            'status' => (int) $type->status,
            'sort' => (int) $type->sort,
            'remark' => (string) $type->remark,
            'createdAt' => $this->formatTime($type->create_time),
        ];
    }

    private function itemData(DictItem $item, string $typeCode): array
    {
        return [
            'id' => (int) $item->id,
            'typeCode' => $typeCode,
            'label' => (string) $item->label,
            'value' => (string) $item->value,
            'sort' => (int) $item->sort,
            'status' => (int) $item->status,
            'cssClass' => (string) $item->css_class,
            'remark' => (string) $item->remark,
        ];
    }

    private function optionData(DictItem $item): array
    {
        return [
            'label' => (string) $item->label,
            'value' => (string) $item->value,
            'status' => (int) $item->status,
            'cssClass' => (string) $item->css_class,
        ];
    }

    private function formatTime($value): string
    {
        if (!$value) {
            return '';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }
        return (string) $value;
    }

    private function ok($data = null, string $msg = '操作成功'): Response
    {
        return $this->apiResponse(200, $msg, $data);
    }

    private function fail(string $msg, int $code = 400): Response
    {
        return $this->apiResponse($code, $msg, null, $code);
    }

    private function apiResponse(int $code, string $msg, $data, int $httpCode = 200): Response
    {
        return json([
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ], $httpCode)->header(['X-CSRF-TOKEN' => (string) Session::get('__token__', '')]);
    }
}
