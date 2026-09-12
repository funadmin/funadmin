<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$implementationFiles = [
    'Form.php',
    'NodeCollection.php',
    'FieldBuilder.php',
    'ValidationBuilder.php',
    'DataSourceBuilder.php',
    'ConditionBuilder.php',
    'ActionBuilder.php',
];

foreach ($implementationFiles as $file) {
    $commonPath = $root . '/app/common/form/builder/' . $file;
    if (!is_file($commonPath)) {
        throw new RuntimeException('common Builder 实现缺失：' . $commonPath);
    }
}

if (is_dir($root . '/extend/fun/form')) {
    throw new RuntimeException('legacy Form Builder 目录必须删除');
}

$publicClasses = [
    'Form',
    'NodeCollection',
    'FieldBuilder',
    'ValidationBuilder',
    'DataSourceBuilder',
    'ConditionBuilder',
    'ActionBuilder',
];
foreach ($publicClasses as $class) {
    $common = 'app\\common\\form\\builder\\' . $class;
    if (!class_exists($common)) {
        throw new RuntimeException('公开 Builder 类缺失：' . $common);
    }
}

$hashForm = \app\common\form\builder\Form::make('migration_contract')
    ->title('迁移契约')
    ->table('fun_migration_contract');
$hashForm->input('name', '名称')->required()->placeholder('请输入名称');
$hashForm->grid('main', '主要信息', function (\app\common\form\builder\NodeCollection $nodes): void {
    $nodes->date('birthday', '生日')->format('YYYY-MM-DD');
});
$hashForm->onSubmit()->request('migration.submit')->notify('已保存');

$expectedHash = '84545ce8718f45d9509a22e99925f84b1b70cbf0eee1cfd1f77b0f0ff86d47cd';
if ($hashForm->compile()->hash() !== $expectedHash) {
    throw new RuntimeException('迁移后的复杂表单 canonical hash 发生变化');
}

$methods = get_class_methods(\app\common\form\builder\Form::class);
foreach (['make', 'title', 'model', 'layout', 'form', 'submit', 'list', 'extension', 'table', 'dataSource', 'dataSourceBuilder', 'action', 'onSubmit', 'toArray', 'toJson', 'compile', 'validate'] as $method) {
    if (!in_array($method, $methods, true)) {
        throw new RuntimeException('公开 Form 方法缺失：' . $method);
    }
}

echo "form builder migration contract tests: PASS\n";
