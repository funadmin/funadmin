<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function legacyFormOriginExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$migrationName = '093_retire_legacy_form_origin.sql';
$migrationPath = $root . '/database/migrations/archive/' . $migrationName;
$migrations = array_map('basename', glob($root . '/database/migrations/archive/*.sql') ?: []);
sort($migrations, SORT_STRING);

legacyFormOriginExpect(is_file($migrationPath), '缺少 legacy_form 来源退役 migration');
legacyFormOriginExpect(
    array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '093_'))) === [$migrationName],
    '093 migration 编号必须唯一且用于 legacy_form 来源退役'
);

$businessService = (string) file_get_contents($root . '/app/console/development/service/BusinessDevelopmentService.php');
$publishService = (string) file_get_contents($root . '/app/console/form/service/FormPublishService.php');
legacyFormOriginExpect(!str_contains($businessService, 'legacy_form'), 'BusinessDevelopmentService 不得再接受 legacy_form 来源');
legacyFormOriginExpect(!str_contains($publishService, 'legacy_form'), 'FormPublishService 不得再产生 legacy_form 来源');
legacyFormOriginExpect(
    str_contains($publishService, "=== 'adopted' ? 'database' : 'visual'"),
    'FormPublishService 必须将 adopted 归类 database，其余归类 visual'
);

foreach ([
    'app/console/form/model/Form.php',
    'app/console/form/model/FormField.php',
    'app/console/form/model/FormSchemaVersion.php',
    'app/console/form/service/FormDesignerService.php',
    'app/console/form/repository/FormSchemaRepository.php',
    'app/console/form/service/FormPublishService.php',
] as $preservedFile) {
    legacyFormOriginExpect(is_file($root . '/' . $preservedFile), '统一 Form 引擎文件不得删除：' . $preservedFile);
}

$migration = is_file($migrationPath) ? (string) file_get_contents($migrationPath) : '';
legacyFormOriginExpect(str_contains($migration, "WHEN f.`source_type`='adopted' THEN 'database' ELSE 'visual'"), '迁移必须按 form.source_type 无损重分类');
legacyFormOriginExpect(str_contains($migration, "WHERE m.`origin`='legacy_form'"), '迁移必须仅重分类 legacy_form 模块');
legacyFormOriginExpect(str_contains($migration, "enum('visual','database')"), '迁移后的 origin enum 必须仅保留 visual/database');
legacyFormOriginExpect(strpos($migration, "WHERE m.`origin`='legacy_form'") < strpos($migration, "enum('visual','database')"), '必须先重分类 legacy 行，再收紧 enum');
legacyFormOriginExpect(str_contains($migration, "JSON_TYPE(m.`metadata`)='OBJECT'"), 'metadata 非对象必须安全处理');
legacyFormOriginExpect(str_contains($migration, "JSON_REMOVE(m.`metadata`,'$.legacyFormId')"), 'metadata 必须移除 legacyFormId');
legacyFormOriginExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME)\b/i', preg_replace('/^\s*--.*$/m', '', $migration)), '迁移必须 forward-only 且不得删除 Form Schema 数据');
legacyFormOriginExpect(!preg_match('/(?:UPDATE|ALTER|DELETE|TRUNCATE|DROP)\s+`?fun_form(?:_field|_schema_version)?`?/i', $migration), '迁移不得修改或删除 Form Schema 数据');

$productionRoots = [$root . '/app', $root . '/admin-web/src'];
foreach ($productionRoots as $productionRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($productionRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || str_ends_with($file->getFilename(), '.spec.ts')) {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        legacyFormOriginExpect(!str_contains($contents, 'legacy_form'), '当前生产代码残留 legacy_form：' . $file->getPathname());
    }
}

$databaseDsn = (string) getenv('LEGACY_FORM_RETIREMENT_TEST_DSN');
if ($databaseDsn !== '' && extension_loaded('pdo_mysql')) {
    $database = new PDO($databaseDsn, (string) getenv('LEGACY_FORM_RETIREMENT_TEST_USER'), (string) getenv('LEGACY_FORM_RETIREMENT_TEST_PASS'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $database->exec('DROP TABLE IF EXISTS `fun_business_module`, `fun_form_schema_version`, `fun_form_field`, `fun_form`');
    $database->exec(<<<'SQL'
CREATE TABLE `fun_form` (
  `id` bigint unsigned NOT NULL, `source_type` varchar(20) NOT NULL, `schema_origin` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
CREATE TABLE `fun_form_field` (
  `id` bigint unsigned NOT NULL, `form_id` bigint unsigned NOT NULL, `field_name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
CREATE TABLE `fun_form_schema_version` (
  `id` bigint unsigned NOT NULL, `form_id` bigint unsigned NOT NULL, `schema_hash` char(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
CREATE TABLE `fun_business_module` (
  `id` bigint unsigned NOT NULL, `form_id` bigint unsigned NULL,
  `origin` enum('visual','database','legacy_form') NOT NULL DEFAULT 'visual', `metadata` json DEFAULT NULL,
  `updated_at` datetime NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB;
SQL);
    $database->exec("INSERT INTO `fun_form` VALUES (1,'adopted','database'),(2,'created','designer'),(3,'created','designer')");
    $database->exec("INSERT INTO `fun_form_field` VALUES (1,1,'adopted_field'),(2,2,'visual_field')");
    $database->exec("INSERT INTO `fun_form_schema_version` VALUES (1,1,REPEAT('a',64)),(2,2,REPEAT('b',64))");
    $database->exec("INSERT INTO `fun_business_module` VALUES (1,1,'legacy_form',JSON_OBJECT('legacyFormId',1,'kept','yes'),NULL),(2,2,'legacy_form',NULL,NULL),(3,3,'legacy_form',JSON_ARRAY('legal'),NULL)");

    $statements = array_values(array_filter(array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $migration)))));
    foreach ($statements as $statement) {
        $database->exec($statement);
    }

    $origins = $database->query('SELECT `id`,`origin`,`metadata` FROM `fun_business_module` ORDER BY `id`')->fetchAll();
    legacyFormOriginExpect($origins[0]['origin'] === 'database', 'adopted 表单必须迁移为 database');
    legacyFormOriginExpect($origins[1]['origin'] === 'visual' && $origins[2]['origin'] === 'visual', '非 adopted 表单必须迁移为 visual');
    legacyFormOriginExpect(json_decode((string) $origins[0]['metadata'], true) === ['kept' => 'yes'], '对象 metadata 必须仅移除 legacyFormId');
    legacyFormOriginExpect($origins[1]['metadata'] === null, 'NULL metadata 必须保持 NULL');
    legacyFormOriginExpect(json_decode((string) $origins[2]['metadata'], true) === ['legal'], '非对象合法 JSON metadata 必须保持原值');
    $originColumn = $database->query("SHOW COLUMNS FROM `fun_business_module` LIKE 'origin'")->fetch();
    legacyFormOriginExpect($originColumn['Type'] === "enum('visual','database')", '数据库 origin enum 必须仅剩 visual/database');
    legacyFormOriginExpect((int) $database->query('SELECT COUNT(*) FROM `fun_form`')->fetchColumn() === 3, '迁移不得删除 fun_form 数据');
    legacyFormOriginExpect((int) $database->query('SELECT COUNT(*) FROM `fun_form_field`')->fetchColumn() === 2, '迁移不得删除 fun_form_field 数据');
    legacyFormOriginExpect((int) $database->query('SELECT COUNT(*) FROM `fun_form_schema_version`')->fetchColumn() === 2, '迁移不得删除 fun_form_schema_version 数据');
    echo "legacy form origin retirement integration tests: PASS\n";
}

echo "legacy form origin retirement contract tests: PASS\n";
