<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\crud\CrudDefinition;
use app\common\crud\CrudGenerator;
use app\common\crud\ManifestMerger;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use app\console\development\service\FormCrudDefinitionFactory;

function managedExpect(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}
function managedReject(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException $error) { return; }
    throw new RuntimeException($message);
}
function managedDefinition(string $entity, string $title): CrudDefinition
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

$root = dirname(__DIR__) . '/runtime/business-plugin-managed-' . bin2hex(random_bytes(6));
(new PluginScaffolder($root . '/plugins'))->scaffold('closeout', '隔离测试', false, true, true);
$manifestPath = $root . '/plugins/closeout/plugin.json';
foreach (['alpha', 'beta'] as $entity) {
    $directory = $root . '/plugins/closeout/admin-web/' . $entity;
    mkdir($directory, 0755, true);
    file_put_contents($directory . '/index.vue', '<template />');
}
$merger = new ManifestMerger($root);
$a = managedDefinition('alpha', '模块 A');
$b = managedDefinition('beta', '模块 B');
$first = $merger->merge($a, true);
file_put_contents($manifestPath, $first);
file_put_contents($manifestPath, $merger->merge($b, true));
$local = json_decode((string) file_get_contents($manifestPath), true);
$local['description'] = '人工说明';
file_put_contents($manifestPath, json_encode($local));
// 基线仅持有 A 的声明；B 与人工键不是 A 的受管范围。
$base = ['adminWeb' => []];
foreach (['components', 'permissions', 'routes', 'menu'] as $section) {
    $items = json_decode($first, true)['adminWeb'][$section] ?? [];
    $base['adminWeb'][$section] = $section === 'components' ? ['Alpha' => $items['Alpha']] : array_values(array_filter($items,
        static fn (array $item): bool => str_contains((string) ($item['code'] ?? $item['path'] ?? ''), 'alpha')));
}
$changed = managedDefinition('alpha', '模块 A 新标题');
$merged = json_decode($merger->merge($changed, true, $base), true);
managedExpect($merged['description'] === '人工说明', '不得覆盖人工顶层键');
$routes = array_column($merged['adminWeb']['routes'], null, 'path');
managedExpect($routes['/plugin/closeout/alpha']['meta']['title'] === '模块 A 新标题', '可靠模块基线应允许更新 A');
managedExpect($routes['/plugin/closeout/beta'] === array_column($local['adminWeb']['routes'], null, 'path')['/plugin/closeout/beta'], '必须保留模块 B');
$local['adminWeb']['routes'][array_search('/plugin/closeout/alpha', array_column($local['adminWeb']['routes'], 'path'), true)]['meta']['title'] = '人工改标题';
file_put_contents($manifestPath, json_encode($local));
managedReject(fn () => $merger->merge($changed, true, $base), '同键人工变更必须冲突');
$conflictPlan = (new CrudGenerator($root))->planManaged($changed, [
    ['path' => 'plugins/closeout/plugin.json', 'artifactType' => 'manifest', 'baseContent' => json_encode($base)],
]);
managedExpect($conflictPlan['blocked'] === true, '人工键冲突必须返回 blocked 计划而不是 422');
$manifestConflict = array_values(array_filter($conflictPlan['files'], fn (array $file): bool => $file['path'] === 'plugins/closeout/plugin.json'))[0];
managedExpect($manifestConflict['status'] === 'conflict' && !empty($manifestConflict['conflictPaths']), '必须提供结构化冲突路径');
managedExpect(str_contains($manifestConflict['localContent'], '人工改标题') && !str_contains($manifestConflict['localContent'], '人工说明'), '三方内容仅包含受管键');
file_put_contents($manifestPath, json_encode($merged));
$generator = new CrudGenerator($root);
$projection = $merger->projection($changed);
managedExpect(!str_contains(json_encode($projection), 'beta') && !isset($projection['description']), '模块基线不得包含 B 或人工键');
$baseline = [['path' => 'plugins/closeout/plugin.json', 'artifactType' => 'manifest', 'baseContent' => json_encode($projection)]];
$baseline[] = ['path' => 'plugins/closeout/admin-web/alpha/index.vue', 'artifactType' => 'source', 'baseContent' => '<template />'];
$plan = $generator->planManaged($changed, $baseline);
managedExpect(!$plan['blocked'], '模块 Manifest 基线必须接通 managed 规划');
$files = $generator->renderManagedBundle($changed, $baseline);
managedExpect(isset($files['plugins/closeout/plugin.json']), 'managed 渲染必须消费同一基线');
$state = new class {
    public array $records = [];
    public function loadBaselines(int $moduleId): array { return $this->records; }
    public function transaction(callable $operation): mixed { return $operation(); }
    public function commitGeneration(int $moduleId, int $generationId, array $records, string $transactionId, string $digest): void { $this->records = $records; }
    public function isGenerationCommitted(int $moduleId, int $generationId, string $transactionId, string $digest): bool { return $this->records !== []; }
};
$blobs = new \app\console\development\repository\GeneratedFileBaselineRepository($root, $state);
$tokens = new \app\common\crud\ConfirmationToken($root, 'managed-test-secret');
$bundle = [
    'target' => $changed->get('target'), 'plan' => $plan, 'remoteContents' => $files,
    'manifestBaselines' => ['plugins/closeout/plugin.json' => CrudDefinition::canonicalJson($projection)],
    'mergedTextContents' => array_column($plan['files'], 'content', 'path'),
    'definitionHash' => $changed->hash(), 'schemaHash' => str_repeat('1', 64),
    'registryHash' => str_repeat('2', 64), 'templateVersion' => CrudGenerator::TEMPLATE_VERSION,
    'migrationHash' => str_repeat('3', 64), 'resources' => [],
    'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson([])),
];
$transaction = new \app\console\development\service\GenerationTransactionService($root, $tokens, $blobs, $state, new stdClass(), fn (): array => $bundle);
$lifecycle = new \app\common\plugin\sdk\LifecycleLock($root . '/runtime/plugins/locks');
$held = $lifecycle->acquire('closeout');
$blockedByLock = false;
try {
    $transaction->execute(1, 1, $bundle, $tokens->issue($transaction::bundleDigest($bundle)));
} catch (Throwable $error) {
    $blockedByLock = str_contains($error->getMessage(), '锁') || str_contains($error->getMessage(), '生命周期');
} finally { $held->release(); }
managedExpect($blockedByLock && $state->records === [], '生成必须先获取同插件生命周期锁，锁冲突不得写文件或提交');
$transaction->execute(1, 1, $bundle, $tokens->issue($transaction::bundleDigest($bundle)));
$manifestRecords = array_values(array_filter($state->records, fn (array $row): bool => $row['artifact_type'] === 'manifest'));
managedExpect(count($manifestRecords) === 1, 'WAL 必须提交当前模块 Manifest 基线');
$saved = $manifestRecords[0];
managedExpect($blobs->load($saved['base_storage_path'], $saved['base_hash']) === CrudDefinition::canonicalJson($projection), 'WAL 只能持久化模块 projection，不能持有整个 Manifest');
managedExpect($saved['target_hash'] === hash_file('sha256', $manifestPath), 'Manifest target hash 必须仍然绑定完整文件');
$managed = new \app\console\development\service\ManagedGenerationService($root, baselines: $blobs, stateRepository: $state);
$build = new ReflectionMethod($managed, 'buildDefinitionBundle');
$build->setAccessible(true);
$rebuilt = $build->invoke($managed, 1, managedDefinition('alpha', '模块 A 再次更新'), str_repeat('1', 64), str_repeat('2', 64));
managedExpect(isset($rebuilt['manifestBaselines']['plugins/closeout/plugin.json']), '生产 bundle 必须派生模块 projection');
managedExpect(!$rebuilt['plan']['blocked'], '生产 bundle 必须在渲染与规划中消费相同模块基线');
$rows = [];
$previewService = new \app\console\development\service\ManagedGenerationService($root, baselines: $blobs, stateRepository: $state,
    moduleReader: fn (int $id): array => ['id' => $id, 'form_id' => 1],
    generationWriter: function (array $row) use (&$rows): int { $rows[] = $row; return count($rows); });
$previewMethod = new ReflectionMethod($previewService, 'previewBundle');
$preview = $previewMethod->invoke($previewService, 1, $rebuilt, true, 'review_preview_01');
$visible = array_column($preview['plan']['files'], null, 'path');
managedExpect(isset($visible['plugins/closeout/plugin.json']['remoteContent']), '授权正常预览必须提供受管 Manifest 增量');
managedExpect(!str_contains(json_encode($preview), '人工说明') && !str_contains(json_encode($preview), '模块 B'), '预览不得泄露无关 Manifest 键');
$sqlFiles = array_filter($preview['plan']['files'], fn (array $file): bool => str_ends_with($file['path'], '.sql'));
managedExpect($sqlFiles !== [] && str_contains((string) reset($sqlFiles)['remoteContent'], 'CREATE TABLE'), '授权预览必须可见 SQL');
managedExpect(($preview['bundleDigest'] ?? '') === $rows[0]['manifest']['bundleDigest'], '预览必须绑定 generation bundle digest');
$public = $previewMethod->invoke($previewService, 1, $rebuilt, false, 'review_preview_02');
foreach ($public['plan']['files'] as $file) managedExpect(!isset($file['remoteContent'], $file['localContent'], $file['baseContent']), '无敏感权限不得返回文本');
$manual = json_decode((string) file_get_contents($manifestPath), true);
foreach ($manual['adminWeb']['routes'] as &$route) {
    if ($route['path'] === '/plugin/closeout/alpha') $route['meta']['title'] = '人工冲突保存';
}
unset($route);
file_put_contents($manifestPath, json_encode($manual));
$blockedBundle = $build->invoke($managed, 1, managedDefinition('alpha', '新 Remote'), str_repeat('1', 64), str_repeat('2', 64));
$blockedPreview = $previewMethod->invoke($previewService, 1, $blockedBundle, true, 'review_conflict_01');
managedExpect($blockedPreview['plan']['blocked'] && !isset($blockedPreview['sensitive']), '冲突计划不得签发执行令牌');
$savedConflict = $rows[array_key_last($rows)];
managedExpect($savedConflict['status'] === 'conflict', '冲突必须持久化 generation 记录');
$persisted = array_column($savedConflict['manifest']['plan']['files'], null, 'path')['plugins/closeout/plugin.json'];
managedExpect(isset($persisted['baseContent'], $persisted['localContent'], $persisted['remoteContent']), '受管冲突三方必须保存');
$binaryBundle = $rebuilt;
$binaryBundle['plan']['files'][0]['contentKind'] = 'binary';
$binaryBundle['plan']['files'][0]['remoteContent'] = 'BINARY_SECRET';
$binaryPreview = $previewMethod->invoke($previewService, 1, $binaryBundle, true, 'review_binary_01');
managedExpect(!str_contains(json_encode($binaryPreview), 'BINARY_SECRET'), '即使授权也不返回二进制');
echo in_array('--preview-json', $argv, true)
    ? json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    : "business plugin managed tests: PASS\n";
