<?php

declare(strict_types=1);
namespace app\admin\service;

use app\common\form\builder\Page;

/** 会员列表展示层；表单与身份同步仍由原专用实现负责。 */
final class MemberPageDefinition
{
    public static function build(array $options): array
    {
        $choices = static fn (array $items): array => array_map(static fn (array $item): array => ['label' => (string) $item['name'], 'value' => (int) $item['id']], $items);
        $active = ['field' => 'recycled', 'op' => 'eq', 'value' => false];
        $recycled = ['field' => 'recycled', 'op' => 'eq', 'value' => true];
        $empty = ['field' => 'selectionCount', 'op' => 'eq', 'value' => 0];
        $action = static fn (string $id, string $label, string $color, array $extra = []): array => [
            'id' => $id, 'label' => $label, 'color' => $color,
            'action' => ['type' => 'registered', 'key' => $id, 'capabilityVersion' => '1'],
        ] + $extra;
        return Page::make('system_member')->list(['category' => ['enabled' => true, 'field' => 'groupId']])->search([
            ['field' => 'keyword', 'label' => '关键词', 'type' => 'input', 'placeholder' => '用户名/手机号/邮箱'],
            ['field' => 'groupId', 'label' => '会员组', 'type' => 'select', 'placeholder' => '全部', 'options' => $choices($options['groups'])],
            ['field' => 'levelId', 'label' => '会员等级', 'type' => 'select', 'placeholder' => '全部', 'options' => $choices($options['levels'])],
            ['field' => 'status', 'label' => '状态', 'type' => 'select', 'placeholder' => '全部', 'options' => [['label' => '启用', 'value' => 1], ['label' => '停用', 'value' => 0]]],
        ])->columns([
            ['key' => 'selection', 'label' => '', 'type' => 'selection', 'width' => 48, 'align' => 'center'],
            ['key' => 'id', 'label' => 'ID', 'prop' => 'id', 'width' => 72, 'align' => 'center'],
            ['key' => 'member', 'label' => '会员', 'slot' => 'member', 'minWidth' => 180],
            ['key' => 'email', 'label' => '邮箱', 'prop' => 'email', 'formatter' => 'emptyText', 'minWidth' => 180],
            ['key' => 'sex', 'label' => '性别', 'prop' => 'sex', 'formatter' => 'sexText', 'width' => 80, 'align' => 'center'],
            ['key' => 'groups', 'label' => '会员组', 'slot' => 'groups', 'minWidth' => 170],
            ['key' => 'levelName', 'label' => '会员等级', 'prop' => 'levelName', 'minWidth' => 120],
            ['key' => 'status', 'label' => '状态', 'slot' => 'status', 'width' => 90, 'align' => 'center'],
            ['key' => 'loginCount', 'label' => '登录次数', 'prop' => 'loginCount', 'width' => 95, 'align' => 'center'],
            ['key' => 'createdAt', 'label' => '注册时间', 'prop' => 'createdAt', 'width' => 170],
            ['key' => 'deletedAt', 'label' => '删除时间', 'prop' => 'deletedAt', 'width' => 170, 'visibleWhen' => $recycled],
            ['key' => 'actions', 'label' => '操作', 'slot' => 'actions', 'width' => 120, 'align' => 'center', 'fixed' => 'right', 'visibleWhen' => $active],
        ])->toolbar([
            $action('normal', '正常列表', 'primary', ['activeWhen' => $active, 'inactiveColor' => 'info']),
            $action('recycled', '回收站', 'warning', ['activeWhen' => $recycled, 'inactiveColor' => 'info']),
            $action('add', '新增', 'primary', ['icon' => 'i-ep-plus', 'permission' => 'system:member:add', 'visibleWhen' => $active]),
            $action('recycle', '移入回收站', 'danger', ['icon' => 'i-ep-delete', 'permission' => 'system:member:delete', 'visibleWhen' => $active, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('import', 'CSV 导入', 'info', ['icon' => 'i-ep-upload', 'permission' => 'system:member:import', 'visibleWhen' => $active]),
            $action('restore', '恢复', 'success', ['icon' => 'i-ep-refresh-left', 'permission' => 'system:member:restore', 'visibleWhen' => $recycled, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('destroy', '永久删除', 'danger', ['icon' => 'i-ep-delete-filled', 'permission' => 'system:member:destroy', 'visibleWhen' => $recycled, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('export', 'CSV 导出', 'info', ['icon' => 'i-ep-download', 'permission' => 'system:member:export']),
        ])->rowActions([
            $action('edit', '编辑', 'primary', ['permission' => 'system:member:edit', 'visibleWhen' => $active]),
        ])->compile();
    }
}
