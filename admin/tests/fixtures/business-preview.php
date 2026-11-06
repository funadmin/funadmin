<?php

declare(strict_types=1);

// Frontend contracts must never inherit an external MySQL test switch.
putenv('TEST_MYSQL=0');
require dirname(__DIR__) . '/bootstrap.php';

use app\admin\development\repository\GeneratedFileBaselineRepository;
use app\admin\development\service\FormCrudDefinitionFactory;
use app\admin\development\service\ManagedGenerationService;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\ManifestMerger;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\common\plugin\sdk\PluginScaffolder;

function previewDefinition(string $entity, string $title): CrudDefinition
{
    $schema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
        'schemaVersion' => 2, 'key' => $entity, 'title' => $title,
        'database' => ['connection' => 'mysql', 'table' => 'fun_closeout_' . $entity, 'source' => 'created'],
        'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
            'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]],
    ]);
    $factory = new FormCrudDefinitionFactory();
    return $factory->forBusinessTarget($factory->createFromSchema($schema, []), ['type' => 'plugin', 'pluginCode' => 'closeout']);
}

$root = sys_get_temp_dir() . '/frontend-preview-' . bin2hex(random_bytes(8));
try {
    (new PluginScaffolder($root . '/plugins'))->scaffold('closeout', '隔离测试', false, true, true);
    foreach (['alpha', 'beta'] as $entity) {
        $directory = $root . '/plugins/closeout/web/src/admin/modules/closeout/' . $entity;
        mkdir($directory, 0700, true);
        file_put_contents($directory . '/index.vue', '<template />');
    }
    $manifestPath = $root . '/plugins/closeout/plugin.json';
    $merger = new ManifestMerger($root);
    $alpha = previewDefinition('alpha', '模块 A');
    file_put_contents($manifestPath, $merger->merge($alpha, true));
    file_put_contents($manifestPath, $merger->merge(previewDefinition('beta', '模块 B'), true));
    $local = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    $local['description'] = '人工说明';
    file_put_contents($manifestPath, json_encode($local, JSON_THROW_ON_ERROR));

    $state = new class {
        public array $records = [];
        public function loadBaselines(int $id): array { return $this->records; }
    };
    $blobs = new GeneratedFileBaselineRepository($root, $state);
    $base = $blobs->prepare(CrudDefinition::canonicalJson($merger->projection($alpha)));
    $state->records[] = ['artifact_type' => 'manifest', 'relative_path' => 'plugins/closeout/plugin.json',
        'base_storage_path' => $base['path'], 'base_hash' => $base['hash']];
    $sourceBase = $blobs->prepare('<template />');
    $state->records[] = ['artifact_type' => 'source', 'relative_path' => 'plugins/closeout/web/src/admin/modules/closeout/alpha/index.vue',
        'base_storage_path' => $sourceBase['path'], 'base_hash' => $sourceBase['hash']];
    $rows = [];
    $service = new ManagedGenerationService($root, baselines: $blobs, stateRepository: $state,
        tokens: new ConfirmationToken($root, 'frontend-preview-test-secret'),
        moduleReader: static fn (int $id): array => ['id' => $id, 'form_id' => 1],
        generationReader: static fn (int $id): ?array => null,
        generationWriter: static function (array $row) use (&$rows): int { $rows[] = $row; return count($rows); });
    $bundle = (new ReflectionMethod($service, 'buildDefinitionBundle'))->invoke($service, 1,
        previewDefinition('alpha', '模块 A 再次更新'), str_repeat('1', 64), str_repeat('2', 64));
    $preview = (new ReflectionMethod($service, 'previewBundle'))->invoke($service, 1, $bundle, true, 'frontend_preview_01');
    if ($preview['plan']['blocked']) throw new RuntimeException('授权预览被冲突阻止');
    echo json_encode($preview, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
