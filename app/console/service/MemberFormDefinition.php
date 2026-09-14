<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\form\builder\Form;

/** 会员专用展示定义；不绑定数据表、不注册发布、不替代会员 CRUD。 */
final class MemberFormDefinition
{
    public static function build(array $options): array
    {
        $choices = static fn (array $items): array => array_map(
            static fn (array $item): array => ['label' => (string) $item['name'], 'value' => (int) $item['id']], $items
        );
        $form = Form::make('system_member')->title('会员资料');
        $form->input('username', '用户名')->default('')->required()->rule('minLength', 2)->rule('maxLength', 80)
            ->prop('maxlength', 80)->span(12);
        $form->input('mobile', '手机号')->default('')->required()->rule('pattern', '^[0-9+\\- ]{6,20}$')
            ->prop('maxlength', 20)->span(12);
        $form->input('email', '邮箱')->default('')->rule('email')->rule('maxLength', 60)->prop('maxlength', 60)->placeholder('选填');
        $form->select('group_ids', '会员组')->default(isset($options['groups'][0]) ? [(int) $options['groups'][0]['id']] : [])
            ->required()->rule('type', 'array')->rule('min', 1)->rule('max', 32)
            ->prop('multiple', true)->options($choices($options['groups']))->span(12);
        $form->select('level_id', '会员等级')->default((int) ($options['levels'][0]['id'] ?? 0))
            ->required()->rule('type', 'number')->rule('min', 1)->options($choices($options['levels']))->span(12);
        $form->select('tag_ids', '会员标签')->default([])->rule('type', 'array')->rule('max', 32)
            ->prop('multiple', true)->prop('clearable', true)->options($choices($options['tags']));
        $form->radio('sex', '性别')->default('0')->options([
            ['label' => '保密', 'value' => '0'], ['label' => '男', 'value' => '1'], ['label' => '女', 'value' => '2'],
        ])->span(12);
        $form->radio('status', '状态')->default(1)->options([
            ['label' => '启用', 'value' => 1], ['label' => '停用', 'value' => 0],
        ])->span(12);
        $form->image('avatar', '头像')->default('')->rule('maxLength', 255)->prop('maxSize', 2)->prop('bizType', 'avatar');
        $compiled = $form->compile();
        return ['schema' => $compiled->document(), 'fields' => $compiled->fieldProjection()];
    }
}
