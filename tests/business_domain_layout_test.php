<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$expect = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expected = [
    'app/console/development/service/BusinessDevelopmentService.php',
    'app/console/development/service/BusinessModuleService.php',
    'app/console/development/service/DevCrudService.php',
    'app/console/development/service/FormCrudDefinitionFactory.php',
    'app/console/development/service/ManagedGenerationService.php',
    'app/console/development/service/GenerationTransactionService.php',
    'app/console/development/service/GenerationResourceTransaction.php',
    'app/console/development/repository/DatabaseGenerationStateRepository.php',
    'app/console/development/repository/GeneratedFileBaselineRepository.php',
    'app/console/development/exception/BusinessOperationException.php',
    'app/console/development/exception/GenerationInterruptionException.php',
    'app/console/development/http/BusinessApiErrorMapper.php',
    'app/console/development/http/BusinessResponseSanitizer.php',
    'app/console/development/model/BusinessModule.php',
    'app/console/development/model/CrudGeneration.php',
    'app/console/development/model/GeneratedFileBaseline.php',
];
foreach ($expected as $relative) {
    $expect(is_file($root . '/' . $relative), '业务开发领域文件路径错误：' . $relative);
    $namespace = match (true) {
        str_contains($relative, '/service/') => 'app\\console\\development\\service',
        str_contains($relative, '/repository/') => 'app\\console\\development\\repository',
        str_contains($relative, '/exception/') => 'app\\console\\development\\exception',
        str_contains($relative, '/http/') => 'app\\console\\development\\http',
        default => 'app\\console\\development\\model',
    };
    $source = (string) file_get_contents($root . '/' . $relative);
    $expect(str_contains($source, 'namespace ' . $namespace . ';'), '业务开发领域 namespace 错误：' . $relative);
}
foreach (['BusinessDevelopmentService.php', 'BusinessModuleService.php', 'DevCrudService.php', 'FormCrudDefinitionFactory.php', 'ManagedGenerationService.php', 'GenerationTransactionService.php', 'GenerationResourceTransaction.php', 'DatabaseGenerationStateRepository.php', 'GeneratedFileBaselineRepository.php', 'BusinessOperationException.php', 'GenerationInterruptionException.php', 'BusinessApiErrorMapper.php', 'BusinessResponseSanitizer.php'] as $file) {
    $expect(!is_file($root . '/app/console/service/' . $file), '业务开发类不得继续平铺：' . $file);
}
foreach (['BusinessModule.php', 'CrudGeneration.php', 'GeneratedFileBaseline.php'] as $file) {
    $expect(!is_file($root . '/app/console/model/' . $file), '业务开发模型不得继续平铺：' . $file);
}
$expect(is_file($root . '/app/console/controller/development/Business.php'), 'Business HTTP Adapter 必须保留在 Controller 扫描树');
$expect(is_file($root . '/app/console/service/ResourceRegistryService.php'), '跨领域资源注册服务不得迁入业务开发');
$expect(is_file($root . '/app/common/crud/CrudGenerator.php'), '共享 CRUD 生成内核不得迁入 console development');

echo "Business domain layout tests passed\n";
