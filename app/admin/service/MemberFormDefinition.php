<?php

declare(strict_types=1);

namespace app\admin\service;

use app\common\form\builder\Form;

/** 会员专用展示定义；不绑定数据表、不注册发布、不替代会员 CRUD。 */
final class MemberFormDefinition
{
    public static function build(array $options, string $locale = 'zh-cn'): array
    {
        $t = static fn (string $zh, string $en): string => str_starts_with(strtolower($locale), 'en') ? $en : $zh;
        $choices = static fn (array $items): array => array_map(
            static fn (array $item): array => ['label' => (string) $item['name'], 'value' => (int) $item['id']], $items
        );
        $form = Form::make('system_member')->title($t('会员资料', 'Member Profile'));
        $form->input('username', $t('用户名', 'Username'))->default('')->required()->rule('minLength', 2)->rule('maxLength', 80)
            ->prop('maxlength', 80)->span(12);
        $form->input('mobile', $t('手机号', 'Mobile'))->default('')->required()->rule('pattern', '^[0-9+\\- ]{6,20}$')
            ->prop('maxlength', 20)->span(12);
        $form->input('email', $t('邮箱', 'Email'))->default('')->rule('format', 'email')->rule('maxLength', 60)->prop('maxlength', 60)->placeholder($t('选填', 'Optional'));
        $form->select('group_ids', $t('会员组', 'Member Groups'))->default(isset($options['groups'][0]) ? [(int) $options['groups'][0]['id']] : [])
            ->required()->rule('type', 'array')->rule('minLength', 1)->rule('maxLength', 32)
            ->prop('multiple', true)->options($choices($options['groups']))->span(12);
        $form->select('level_id', $t('会员等级', 'Member Level'))->default((int) ($options['levels'][0]['id'] ?? 0))
            ->required()->rule('type', 'number')->rule('min', 1)->options($choices($options['levels']))->span(12);
        $form->select('tag_ids', $t('会员标签', 'Member Tags'))->default([])->rule('type', 'array')->rule('maxLength', 32)
            ->prop('multiple', true)->prop('clearable', true)->options($choices($options['tags']));
        $form->radio('sex', $t('性别', 'Gender'))->default('0')->options([
            ['label' => $t('保密', 'Secret'), 'value' => '0'], ['label' => $t('男', 'Male'), 'value' => '1'], ['label' => $t('女', 'Female'), 'value' => '2'],
        ])->span(12);
        $form->radio('status', $t('状态', 'Status'))->default(1)->options([
            ['label' => $t('启用', 'Enabled'), 'value' => 1], ['label' => $t('停用', 'Disabled'), 'value' => 0],
        ])->span(12);
        $form->image('avatar', $t('头像', 'Avatar'))->default('')->rule('maxLength', 255)->prop('maxSize', 2)->prop('bizType', 'avatar');
        $compiled = $form->compile();
        return ['schema' => $compiled->document(), 'fields' => $compiled->fieldProjection()];
    }
}
