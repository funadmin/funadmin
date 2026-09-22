<?php

declare(strict_types=1);
namespace app\admin\service;

use app\common\form\builder\Page;

/** 会员列表展示层；表单与身份同步仍由原专用实现负责。 */
final class MemberPageDefinition
{
    public static function build(array $options, string $locale = 'zh-cn'): array
    {
        $t = static fn (string $zh, string $en): string => str_starts_with(strtolower($locale), 'en') ? $en : $zh;
        $choices = static fn (array $items): array => array_map(static fn (array $item): array => ['label' => (string) $item['name'], 'value' => (int) $item['id']], $items);
        $active = ['field' => 'recycled', 'op' => 'eq', 'value' => false];
        $recycled = ['field' => 'recycled', 'op' => 'eq', 'value' => true];
        $empty = ['field' => 'selectionCount', 'op' => 'eq', 'value' => 0];
        $action = static fn (string $id, string $label, string $color, array $extra = []): array => [
            'id' => $id, 'label' => $label, 'color' => $color,
            'action' => ['type' => 'registered', 'key' => $id, 'capabilityVersion' => '1'],
        ] + $extra;
        return Page::make('system_member')->list(['category' => ['enabled' => true, 'field' => 'groupId']])->search([
            ['field' => 'keyword', 'label' => $t('关键词', 'Keyword'), 'type' => 'input', 'placeholder' => $t('用户名/手机号/邮箱', 'Username/Mobile/Email')],
            ['field' => 'groupId', 'label' => $t('会员组', 'Member Group'), 'type' => 'select', 'placeholder' => $t('全部', 'All'), 'options' => $choices($options['groups'])],
            ['field' => 'levelId', 'label' => $t('会员等级', 'Member Level'), 'type' => 'select', 'placeholder' => $t('全部', 'All'), 'options' => $choices($options['levels'])],
            ['field' => 'status', 'label' => $t('状态', 'Status'), 'type' => 'select', 'placeholder' => $t('全部', 'All'), 'options' => [['label' => $t('启用', 'Enabled'), 'value' => 1], ['label' => $t('停用', 'Disabled'), 'value' => 0]]],
        ])->columns([
            ['key' => 'selection', 'label' => '', 'type' => 'selection', 'width' => 48, 'align' => 'center'],
            ['key' => 'id', 'label' => 'ID', 'prop' => 'id', 'width' => 72, 'align' => 'center'],
            ['key' => 'member', 'label' => $t('会员', 'Member'), 'slot' => 'member', 'minWidth' => 180],
            ['key' => 'email', 'label' => $t('邮箱', 'Email'), 'prop' => 'email', 'formatter' => 'emptyText', 'minWidth' => 180],
            ['key' => 'sex', 'label' => $t('性别', 'Gender'), 'prop' => 'sex', 'formatter' => 'sexText', 'width' => 80, 'align' => 'center'],
            ['key' => 'groups', 'label' => $t('会员组', 'Member Groups'), 'slot' => 'groups', 'minWidth' => 170],
            ['key' => 'levelName', 'label' => $t('会员等级', 'Member Level'), 'prop' => 'levelName', 'minWidth' => 120],
            ['key' => 'status', 'label' => $t('状态', 'Status'), 'slot' => 'status', 'width' => 90, 'align' => 'center'],
            ['key' => 'loginCount', 'label' => $t('登录次数', 'Logins'), 'prop' => 'loginCount', 'width' => 95, 'align' => 'center'],
            ['key' => 'createdAt', 'label' => $t('注册时间', 'Registered At'), 'prop' => 'createdAt', 'width' => 170],
            ['key' => 'deletedAt', 'label' => $t('删除时间', 'Deleted At'), 'prop' => 'deletedAt', 'width' => 170, 'visibleWhen' => $recycled],
            ['key' => 'actions', 'label' => $t('操作', 'Actions'), 'slot' => 'actions', 'width' => 120, 'align' => 'center', 'fixed' => 'right', 'visibleWhen' => $active],
        ])->toolbar([
            $action('normal', $t('正常列表', 'Normal List'), 'primary', ['icon' => 'i-ep-list', 'activeWhen' => $active, 'inactiveColor' => 'info']),
            $action('recycled', $t('回收站', 'Recycle Bin'), 'warning', ['icon' => 'i-ep-folder-remove', 'activeWhen' => $recycled, 'inactiveColor' => 'info']),
            $action('add', $t('新增', 'Add'), 'primary', ['icon' => 'i-ep-plus', 'permission' => 'system:member:add', 'visibleWhen' => $active]),
            $action('recycle', $t('移入回收站', 'Move to Recycle Bin'), 'danger', ['icon' => 'i-ep-delete', 'permission' => 'system:member:delete', 'visibleWhen' => $active, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('import', $t('CSV 导入', 'CSV Import'), 'info', ['icon' => 'i-ep-upload', 'permission' => 'system:member:import', 'visibleWhen' => $active]),
            $action('restore', $t('恢复', 'Restore'), 'success', ['icon' => 'i-ep-refresh-left', 'permission' => 'system:member:restore', 'visibleWhen' => $recycled, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('destroy', $t('永久删除', 'Delete Permanently'), 'danger', ['icon' => 'i-ep-delete-filled', 'permission' => 'system:member:destroy', 'visibleWhen' => $recycled, 'disabledWhen' => $empty, 'selectionCount' => true]),
            $action('export', $t('CSV 导出', 'CSV Export'), 'info', ['icon' => 'i-ep-download', 'permission' => 'system:member:export']),
        ])->rowActions([
            $action('edit', $t('编辑', 'Edit'), 'primary', ['permission' => 'system:member:edit', 'visibleWhen' => $active]),
        ])->compile();
    }
}
