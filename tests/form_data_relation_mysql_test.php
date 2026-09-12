<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\form\repository\FormSchemaRepository;
use app\console\form\service\FormDataService;
use think\App;
use think\facade\Db;

function relationMysqlExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function relationMysqlIdentifier(string $identifier): string
{
    relationMysqlExpect((bool) preg_match('/^[a-z0-9_]+$/', $identifier), '数据库标识符不安全');
    return '`' . $identifier . '`';
}

function relationMysqlRows(PDO $database, string $sql): array
{
    return $database->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function relationMysqlFailure(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$projectRoot = dirname(__DIR__);
$app = new App($projectRoot . '/');
$app->http->name('console');
$app->setAppPath($projectRoot . '/app/console/');
$app->setNamespace('app\\console');
$app->initialize();
$originalDatabaseConfig = (array) config('database');
$mysql = (array) ($originalDatabaseConfig['connections']['mysql'] ?? []);
$sourceDatabase = (string) ($mysql['database'] ?? '');
$temporaryDatabase = 'funadmin_form_relation_' . bin2hex(random_bytes(6));

relationMysqlExpect($sourceDatabase !== '', '项目数据库名不能为空');
relationMysqlExpect($sourceDatabase !== $temporaryDatabase, '隔离测试库不得等于项目数据库');
relationMysqlExpect(str_starts_with($temporaryDatabase, 'funadmin_form_relation_'), '隔离测试库名必须使用安全前缀');

$host = (string) ($mysql['hostname'] ?? '127.0.0.1');
$port = (string) ($mysql['hostport'] ?? '3306');
$charset = (string) ($mysql['charset'] ?? 'utf8mb4');
$username = (string) ($mysql['username'] ?? '');
$password = (string) ($mysql['password'] ?? '');
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
];
$server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, $options);
$database = null;

try {
    $server->exec('CREATE DATABASE ' . relationMysqlIdentifier($temporaryDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database = new PDO("mysql:host={$host};port={$port};dbname={$temporaryDatabase};charset={$charset}", $username, $password, $options);

    $database->exec(<<<'SQL'
CREATE TABLE `fun_form` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_key` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `connection` varchar(50) NOT NULL DEFAULT 'mysql',
  `source_type` varchar(20) NOT NULL DEFAULT 'adopted',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `list_config` json DEFAULT NULL,
  `form_config` json DEFAULT NULL,
  `published_schema_hash` char(64) NULL,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_key` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_form_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL,
  `field_name` varchar(64) NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'input',
  `column_type` varchar(100) NOT NULL DEFAULT '',
  `nullable` tinyint(1) NOT NULL DEFAULT 1,
  `default_value` varchar(255) NOT NULL DEFAULT '',
  `comment` varchar(255) NOT NULL DEFAULT '',
  `unsigned` tinyint(1) NOT NULL DEFAULT 0,
  `index_type` varchar(10) NOT NULL DEFAULT 'none',
  `placeholder` varchar(100) NOT NULL DEFAULT '',
  `options_source` json DEFAULT NULL,
  `control_props` json DEFAULT NULL,
  `validate_rules` json DEFAULT NULL,
  `link_rules` json DEFAULT NULL,
  `relation_type` varchar(20) NOT NULL DEFAULT 'none',
  `relation_table` varchar(100) NOT NULL DEFAULT '',
  `relation_label_field` varchar(64) NOT NULL DEFAULT '',
  `relation_value_field` varchar(64) NOT NULL DEFAULT '',
  `relation_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `relation_on_delete` varchar(20) NOT NULL DEFAULT 'restrict',
  `list_show` tinyint(1) NOT NULL DEFAULT 1,
  `list_sort` tinyint(1) NOT NULL DEFAULT 0,
  `list_filter` varchar(20) NOT NULL DEFAULT '',
  `list_formatter` varchar(30) NOT NULL DEFAULT '',
  `list_width` int NOT NULL DEFAULT 0,
  `form_show` tinyint(1) NOT NULL DEFAULT 1,
  `form_required` tinyint(1) NOT NULL DEFAULT 0,
  `form_group` varchar(50) NOT NULL DEFAULT '',
  `form_span` int NOT NULL DEFAULT 24,
  `form_readonly` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_field_form_name` (`form_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_form_schema_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `schema_version` int unsigned NOT NULL DEFAULT 2,
  `schema_hash` char(64) NOT NULL,
  `schema_document` json NOT NULL,
  `origin` varchar(20) NOT NULL DEFAULT 'designer',
  `parent_version_id` bigint unsigned NULL,
  `change_summary` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_schema_version` (`form_id`,`version`),
  UNIQUE KEY `uk_form_schema_hash` (`form_id`,`schema_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_business_module` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `form_id` bigint unsigned NOT NULL,
  `lifecycle_status` varchar(32) NOT NULL DEFAULT 'draft',
  `published_schema_hash` char(64) NULL,
  `published_schema_version` int unsigned NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_business_module_code` (`code`),
  UNIQUE KEY `uk_business_module_form` (`form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_fd_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_fd_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `sku` varchar(50) NOT NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fd_item_order_sku` (`order_id`,`sku`),
  KEY `idx_fd_item_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_fd_note` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `body` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fd_note_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_fd_string_order` (
  `code` varchar(36) NOT NULL,
  `title` varchar(100) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_fd_string_item` (
  `line_code` varchar(36) NOT NULL,
  `order_code` varchar(36) NOT NULL,
  `label` varchar(100) NOT NULL,
  PRIMARY KEY (`line_code`),
  KEY `idx_fd_string_item_order` (`order_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

    $database->exec(<<<'SQL'
INSERT INTO `fun_form` (`id`,`form_key`,`name`,`table_name`,`connection`,`status`) VALUES
(1,'fd_orders','订单','fun_fd_order','mysql',1),
(2,'fd_items','订单明细','fun_fd_item','mysql',1),
(3,'fd_notes','订单备注','fun_fd_note','mysql',1),
(4,'fd_string_orders','字符串订单','fun_fd_string_order','mysql',1),
(5,'fd_string_items','字符串明细','fun_fd_string_item','mysql',1);
INSERT INTO `fun_form_field` (`form_id`,`field_name`,`label`,`type`,`column_type`,`control_props`,`relation_type`,`relation_table`,`relation_value_field`,`sort_order`) VALUES
(1,'title','标题','input','varchar(100)',NULL,'none','','',10),
(1,'items','明细','repeatable','',NULL,'has_many','fun_fd_item','order_id',20),
(1,'notes','备注','subform','',NULL,'has_many','fun_fd_note','order_id',30),
(2,'id','ID','number','bigint unsigned','{"primary":true}','none','','',10),
(2,'sku','SKU','input','varchar(50)',NULL,'none','','',20),
(3,'id','ID','number','bigint unsigned','{"primary":true}','none','','',10),
(3,'body','内容','input','varchar(100)',NULL,'none','','',20),
(4,'code','编码','input','varchar(36)','{"primary":true}','none','','',10),
(4,'title','标题','input','varchar(100)',NULL,'none','','',20),
(4,'items','明细','subform','',NULL,'has_many','fun_fd_string_item','order_code',30),
(5,'line_code','行编码','input','varchar(36)','{"primary":true}','none','','',10),
(5,'label','标签','input','varchar(100)',NULL,'none','','',20);
SQL);

    $definitions = [
        1 => ['form_key' => 'fd_orders', 'name' => '订单', 'table_name' => 'fun_fd_order', 'connection' => 'mysql', 'source_type' => 'adopted', 'fields' => [
            ['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(100)'],
            ['field_name' => 'items', 'label' => '明细', 'type' => 'repeatable', 'relation_type' => 'has_many', 'relation_table' => 'fun_fd_item', 'relation_value_field' => 'order_id'],
            ['field_name' => 'notes', 'label' => '备注', 'type' => 'subform', 'relation_type' => 'has_many', 'relation_table' => 'fun_fd_note', 'relation_value_field' => 'order_id'],
        ]],
        2 => ['form_key' => 'fd_items', 'name' => '订单明细', 'table_name' => 'fun_fd_item', 'connection' => 'mysql', 'source_type' => 'adopted', 'fields' => [
            ['field_name' => 'id', 'label' => 'ID', 'type' => 'number', 'column_type' => 'bigint unsigned', 'control_props' => ['primary' => true]],
            ['field_name' => 'sku', 'label' => 'SKU', 'type' => 'input', 'column_type' => 'varchar(50)'],
        ]],
        3 => ['form_key' => 'fd_notes', 'name' => '订单备注', 'table_name' => 'fun_fd_note', 'connection' => 'mysql', 'source_type' => 'adopted', 'fields' => [
            ['field_name' => 'id', 'label' => 'ID', 'type' => 'number', 'column_type' => 'bigint unsigned', 'control_props' => ['primary' => true]],
            ['field_name' => 'body', 'label' => '内容', 'type' => 'input', 'column_type' => 'varchar(100)'],
        ]],
        4 => ['form_key' => 'fd_string_orders', 'name' => '字符串订单', 'table_name' => 'fun_fd_string_order', 'connection' => 'mysql', 'source_type' => 'adopted', 'fields' => [
            ['field_name' => 'code', 'label' => '编码', 'type' => 'input', 'column_type' => 'varchar(36)', 'control_props' => ['primary' => true]],
            ['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(100)'],
            ['field_name' => 'items', 'label' => '明细', 'type' => 'subform', 'relation_type' => 'has_many', 'relation_table' => 'fun_fd_string_item', 'relation_value_field' => 'order_code'],
        ]],
        5 => ['form_key' => 'fd_string_items', 'name' => '字符串明细', 'table_name' => 'fun_fd_string_item', 'connection' => 'mysql', 'source_type' => 'adopted', 'fields' => [
            ['field_name' => 'line_code', 'label' => '行编码', 'type' => 'input', 'column_type' => 'varchar(36)', 'control_props' => ['primary' => true]],
            ['field_name' => 'label', 'label' => '标签', 'type' => 'input', 'column_type' => 'varchar(100)'],
        ]],
    ];
    $repository = new FormSchemaRepository();
    $schemaHashes = [];
    $insertVersion = $database->prepare('INSERT INTO fun_form_schema_version (form_id,version,schema_version,schema_hash,schema_document,created_at) VALUES (?,1,2,?,?,NOW())');
    $publishForm = $database->prepare('UPDATE fun_form SET published_schema_hash=? WHERE id=?');
    $publishModule = $database->prepare("INSERT INTO fun_business_module (code,form_id,lifecycle_status,published_schema_hash,published_schema_version) VALUES (?,?,'dynamic_published',?,1)");
    foreach ($definitions as $formId => $definition) {
        $compiled = $repository->compile($definition);
        $schemaHashes[$definition['form_key']] = $compiled->hash();
        $insertVersion->execute([$formId, $compiled->hash(), json_encode($compiled->document(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
        $publishForm->execute([$compiled->hash(), $formId]);
        $publishModule->execute([$definition['form_key'], $formId, $compiled->hash()]);
    }

    $isolatedConfig = $originalDatabaseConfig;
    $isolatedConfig['connections']['mysql']['database'] = $temporaryDatabase;
    $app->config->set($isolatedConfig, 'database');
    Db::connect('mysql', true);
    $service = new FormDataService();
    $create = static fn (string $key, array $data): array => $service->create($key, $data, [], $schemaHashes[$key]);
    $update = static fn (string $key, int|string $id, array $data): array => $service->update($key, $id, $data, [], $schemaHashes[$key]);

    // 整数父主键创建：父 ID 必须可靠传给 repeatable 与 subform。
    $created = $create('fd_orders', [
        'title' => '整数父单',
        'items' => [['sku' => 'I-1'], ['sku' => 'I-2']],
        'notes' => [['body' => 'N-1']],
    ]);
    $integerParentId = (int) $created['id'];
    relationMysqlExpect($integerParentId > 0, '整数父主键创建必须返回自增 ID');
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_item WHERE order_id={$integerParentId}")->fetchColumn() === 2, 'repeatable 子行必须绑定整数父主键');
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_note WHERE order_id={$integerParentId}")->fetchColumn() === 1, 'subform 子行必须绑定整数父主键');

    // 字符串父主键与字符串子主键必须原样创建并关联。
    $stringCreated = $create('fd_string_orders', [
        'code' => 'ORDER-A',
        'title' => '字符串父单',
        'items' => [['line_code' => 'LINE-A', 'label' => '第一行']],
    ]);
    relationMysqlExpect($stringCreated['id'] === 'ORDER-A', '字符串父主键必须原样返回');
    relationMysqlExpect(relationMysqlRows($database, "SELECT line_code,order_code,label FROM fun_fd_string_item WHERE line_code='LINE-A'") === [[
        'line_code' => 'LINE-A', 'order_code' => 'ORDER-A', 'label' => '第一行',
    ]], '字符串子主键必须允许创建并绑定字符串父主键');

    // 任一子行失败时，父行和已写入子行必须一起回滚。
    $parentCountBefore = (int) $database->query('SELECT COUNT(*) FROM fun_fd_order')->fetchColumn();
    $itemCountBefore = (int) $database->query('SELECT COUNT(*) FROM fun_fd_item')->fetchColumn();
    relationMysqlFailure(fn () => $create('fd_orders', [
        'title' => '应回滚父单',
        'items' => [['sku' => 'DUP'], ['sku' => 'DUP']],
    ]), '重复子行创建必须失败');
    relationMysqlExpect((int) $database->query('SELECT COUNT(*) FROM fun_fd_order')->fetchColumn() === $parentCountBefore, '子行失败后父创建必须回滚');
    relationMysqlExpect((int) $database->query('SELECT COUNT(*) FROM fun_fd_item')->fetchColumn() === $itemCountBefore, '子行失败后已创建子行必须回滚');

    // 更新未携带关系字段时保持子行不变。
    $itemsBeforeMissingUpdate = relationMysqlRows($database, "SELECT id,sku,deleted_at FROM fun_fd_item WHERE order_id={$integerParentId} ORDER BY id");
    $update('fd_orders', $integerParentId, ['title' => '仅更新父表']);
    relationMysqlExpect(relationMysqlRows($database, "SELECT id,sku,deleted_at FROM fun_fd_item WHERE order_id={$integerParentId} ORDER BY id") === $itemsBeforeMissingUpdate, '缺少关系字段时必须保持子行不变');

    // 混合同步：更新已有、创建新增、遗漏项软删。
    $keptId = (int) $itemsBeforeMissingUpdate[0]['id'];
    $removedId = (int) $itemsBeforeMissingUpdate[1]['id'];
    $update('fd_orders', $integerParentId, [
        'items' => [['id' => $keptId, 'sku' => 'I-1-UPDATED'], ['sku' => 'I-3']],
    ]);
    relationMysqlExpect((string) $database->query("SELECT sku FROM fun_fd_item WHERE id={$keptId}")->fetchColumn() === 'I-1-UPDATED', '混合同步必须更新已有子行');
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_item WHERE order_id={$integerParentId} AND sku='I-3' AND deleted_at IS NULL")->fetchColumn() === 1, '混合同步必须创建新增子行');
    relationMysqlExpect($database->query("SELECT deleted_at FROM fun_fd_item WHERE id={$removedId}")->fetchColumn() !== null, '混合同步遗漏的软删表子行必须软删除');

    // 越权提交其他父记录的子行必须拒绝，且父字段和本父子行均回滚。
    $other = $create('fd_orders', ['title' => '其他父单', 'items' => [['sku' => 'OTHER']]]);
    $otherItemId = (int) $database->query('SELECT id FROM fun_fd_item WHERE order_id=' . (int) $other['id'])->fetchColumn();
    $titleBeforeUnauthorized = (string) $database->query("SELECT title FROM fun_fd_order WHERE id={$integerParentId}")->fetchColumn();
    $activeItemsBeforeUnauthorized = relationMysqlRows($database, "SELECT id,sku FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NULL ORDER BY id");
    relationMysqlFailure(fn () => $update('fd_orders', $integerParentId, [
        'title' => '越权更新不应保留',
        'items' => [['id' => $otherItemId, 'sku' => 'STOLEN']],
    ]), '越权子行必须被拒绝');
    relationMysqlExpect((string) $database->query("SELECT title FROM fun_fd_order WHERE id={$integerParentId}")->fetchColumn() === $titleBeforeUnauthorized, '越权子行失败后父更新必须回滚');
    relationMysqlExpect(relationMysqlRows($database, "SELECT id,sku FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NULL ORDER BY id") === $activeItemsBeforeUnauthorized, '越权子行失败后本父子行必须保持不变');

    // 空数组明确清空关系：含 deleted_at 的表软删，不含 deleted_at 的表硬删。
    $update('fd_orders', $integerParentId, ['items' => [], 'notes' => []]);
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NULL")->fetchColumn() === 0, '空 repeatable 数组必须清空活动子行');
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NOT NULL")->fetchColumn() >= 2, '含 deleted_at 的子表必须软删除');
    relationMysqlExpect((int) $database->query("SELECT COUNT(*) FROM fun_fd_note WHERE order_id={$integerParentId}")->fetchColumn() === 0, '不含 deleted_at 的子表必须硬删除');

    // 两个关系依次同步时，后一个失败必须回滚父表和前一个关系的全部变更。
    $update('fd_orders', $integerParentId, [
        'items' => [['sku' => 'TX-OLD']],
        'notes' => [['body' => 'NOTE-OLD']],
    ]);
    $transactionItem = relationMysqlRows($database, "SELECT id,sku FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NULL");
    $transactionNote = relationMysqlRows($database, "SELECT id,body FROM fun_fd_note WHERE order_id={$integerParentId}");
    $foreignNoteParent = $create('fd_orders', ['title' => '备注归属父单', 'notes' => [['body' => 'FOREIGN-NOTE']]]);
    $foreignNoteId = (int) $database->query('SELECT id FROM fun_fd_note WHERE order_id=' . (int) $foreignNoteParent['id'])->fetchColumn();
    relationMysqlFailure(fn () => $update('fd_orders', $integerParentId, [
        'title' => '多关系失败不应保留',
        'items' => [['id' => (int) $transactionItem[0]['id'], 'sku' => 'TX-CHANGED'], ['sku' => 'TX-NEW']],
        'notes' => [['id' => $foreignNoteId, 'body' => 'FOREIGN-CHANGED']],
    ]), '第二个关系越权必须导致整体失败');
    relationMysqlExpect(relationMysqlRows($database, "SELECT id,sku FROM fun_fd_item WHERE order_id={$integerParentId} AND deleted_at IS NULL") === $transactionItem, '多关系失败必须回滚前一个关系的更新和创建');
    relationMysqlExpect(relationMysqlRows($database, "SELECT id,body FROM fun_fd_note WHERE order_id={$integerParentId}") === $transactionNote, '多关系失败必须保持当前父记录的后一个关系');
    relationMysqlExpect((string) $database->query("SELECT title FROM fun_fd_order WHERE id={$integerParentId}")->fetchColumn() === $titleBeforeUnauthorized, '多关系失败必须回滚父表更新');

    echo "form data relation mysql isolation tests: PASS; temporary database cleaned\n";
} finally {
    $database = null;
    $app->config->set($originalDatabaseConfig, 'database');
    Db::connect('mysql', true);
    if (str_starts_with($temporaryDatabase, 'funadmin_form_relation_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . relationMysqlIdentifier($temporaryDatabase));
    }
}
