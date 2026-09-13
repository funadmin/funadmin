<?php
// 只读现场诊断：不启动应用、不调用预览、不写审计或业务数据。
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$root = dirname(__DIR__);
$env = new think\Env();
$env->load($root . '/.env');
try {
    $pdo = new PDO('mysql:host=' . $env->get('DB_HOST', '127.0.0.1') . ';port=' . $env->get('DB_PORT', '3306') . ';dbname=' . $env->get('DB_NAME', 'funadmin') . ';charset=utf8mb4', $env->get('DB_USER', 'root'), $env->get('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    exit("数据库连接失败（隐藏连接详情）\n");
}
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    $module = $pdo->query('SELECT id,form_id,code,lifecycle_status,generation_status FROM fun_business_module WHERE id=4')->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['module' => $module], JSON_UNESCAPED_UNICODE) . "\n";
    $form = $pdo->query('SELECT id,form_key,name,table_name,connection,source_type,publish_config,schema_hash,published_schema_hash FROM fun_form WHERE id=3')->fetch(PDO::FETCH_ASSOC);
    $form['publish_config'] = json_decode($form['publish_config'] ?: '[]', true);
    echo json_encode(['form' => $form], JSON_UNESCAPED_UNICODE) . "\n";
    $stmt = $pdo->prepare('SELECT version,schema_hash,schema_document FROM fun_form_schema_version WHERE form_id=3 AND schema_hash=?');
    $stmt->execute([$form['published_schema_hash']]);
    $version = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['publishedVersion' => $version ? $version['version'] : null]) . "\n";
    echo json_encode(['generations' => $pdo->query('SELECT id,status,created_at FROM fun_crud_generation WHERE business_module_id=4 ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC)]) . "\n";
    $document = json_decode($version['schema_document'], true);
    $schema = (new app\common\form\schema\FormSchemaCompiler(new app\common\form\schema\FormSchemaValidator()))->compile($document);
    echo json_encode(['compiledHash' => $schema->hash(), 'list' => $schema->document()['list'] ?? [], 'fields' => array_map(static fn ($f) => array_intersect_key($f, array_flip(['field_name','type','column_type'])), $schema->fieldProjection())], JSON_UNESCAPED_UNICODE) . "\n";
    $definition = (new app\console\development\service\FormCrudDefinitionFactory())->createFromSchema($schema, $form, $form['publish_config']);
    (new app\common\crud\DefinitionValidator())->validate($definition, $root);
    echo "Definition 校验通过\n";
    $files = (new app\common\crud\CrudGenerator($root))->renderManagedBundle($definition);
    echo '纯内存模板渲染通过：' . count($files) . "\n";
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    $pdo->rollBack();
}
