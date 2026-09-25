<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\form\repository\FormSchemaRepository;
use app\admin\form\service\FormDataService;
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
$app->setAppPath($projectRoot . '/app/admin/');
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

    // 分类与树的完整运行态：真实字符串主键、等值零值、软删除、导出及写入。
    $database->exec('ALTER TABLE fun_fd_string_order ADD parent_code varchar(36) NULL, ADD category_id int NOT NULL DEFAULT 0, ADD deleted_at datetime NULL');
    $treeDefinition = $definitions[4];
    $treeDefinition['fields'] = array_values(array_filter($treeDefinition['fields'], static fn (array $field): bool => $field['field_name'] !== 'items'));
    $treeDefinition['fields'][1]['list_filter'] = 'eq';
    $treeDefinition['fields'][] = ['field_name' => 'parent_code', 'label' => '父级', 'type' => 'input', 'column_type' => 'varchar(36)'];
    $treeDefinition['fields'][] = ['field_name' => 'category_id', 'label' => '分类', 'type' => 'select', 'column_type' => 'int', 'options_source' => ['mode' => 'static', 'options' => [['label' => '零', 'value' => 0], ['label' => '一', 'value' => 1]]]];
    $treeDefinition['list_config'] = ['category' => ['enabled' => true, 'field' => 'category_id'], 'tree' => ['enabled' => true, 'parentField' => 'parent_code']];
    $treeSchema = $repository->compile($treeDefinition);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$treeSchema->hash(), $treeSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$treeSchema->hash()]);
    $database->prepare('UPDATE fun_form SET published_schema_hash=? WHERE id=4')->execute([$treeSchema->hash()]);
    Db::connect('mysql', true);
    $treeMeta = $service->meta('fd_string_orders');
    relationMysqlExpect($treeMeta['primaryKey']['name'] === 'code' && count($treeMeta['categoryOptions']) === 2, '实际主键与安全分类选项必须返回');
    $service->create('fd_string_orders', ['code' => 'ORDER-B', 'title' => '子记录', 'parent_code' => 'ORDER-A', 'category_id' => 0], [], $treeSchema->hash());
    $service->create('fd_string_orders', ['code' => 'ORDER-C', 'title' => '其他分类', 'parent_code' => 'ORDER-A', 'category_id' => 1], [], $treeSchema->hash());
    $zero = $service->listing('fd_string_orders', ['__category' => 0], '', 'asc', 9, 1);
    relationMysqlExpect(count($zero['list']) === 2 && $zero['page'] === 1, '分类零值必须等值筛选且树不分页');
    relationMysqlExpect(count($service->export('fd_string_orders', ['__category' => 0])) === 2, '树导出不能只导根或当前页');
    $service->update('fd_string_orders', 'ORDER-B', ['title' => '编辑子记录'], [], $treeSchema->hash());
    relationMysqlExpect($service->detail('fd_string_orders', 'ORDER-B')['row']['title'] === '编辑子记录', '树子记录编辑仍可用');
    $service->remove('fd_string_orders', 'ORDER-A', $treeSchema->hash());
    relationMysqlExpect(count($service->listing('fd_string_orders', ['__category' => 0], '', 'asc', 1, 1)['list']) === 1, '软删父记录后孤儿仍可读取');
    $insertTree = $database->prepare('INSERT INTO fun_fd_string_order (code,title,category_id) VALUES (?,?,0)');
    for ($index = 0; $index < 999; $index++) $insertTree->execute(['NODE-' . $index, '节点']);
    relationMysqlExpect(count($service->listing('fd_string_orders', ['__category' => 0], '', 'asc', 1, 20)['list']) === 1000, '1000 条授权集合完整返回');
    relationMysqlFailure(fn () => $service->listing('fd_string_orders', [], '', 'asc', 1, 20), '1001 条必须报错而非截断');
    relationMysqlExpect(count($service->export('fd_string_orders', [])) === 1001, '1001 条树数据必须允许导出，不受展示上限限制');

    // 使用真实部门授权，验证共用过滤不能遗漏权限、软删除或零值分类。
    $database->exec(<<<'SQL'
ALTER TABLE fun_business_module ADD metadata json NULL;
ALTER TABLE fun_fd_string_order ADD dept_id int NOT NULL DEFAULT 7;
CREATE TABLE fun_admin (id bigint PRIMARY KEY, dept_id int, deleted_at datetime NULL);
CREATE TABLE fun_auth_group (id bigint PRIMARY KEY, status int, data_scope varchar(30), deleted_at datetime NULL);
CREATE TABLE fun_admin_department (id bigint PRIMARY KEY, admin_id bigint, dept_id int);
CREATE TABLE fun_casbin_rule (id bigint PRIMARY KEY, ptype varchar(10), v0 varchar(100), v1 varchar(100), v2 varchar(100), v3 varchar(100) DEFAULT '', v4 varchar(100) DEFAULT '', v5 varchar(100) DEFAULT '');
INSERT INTO fun_admin VALUES (987654,7,NULL);
INSERT INTO fun_auth_group VALUES (987654,1,'dept',NULL);
INSERT INTO fun_admin_department VALUES (1,987654,7);
UPDATE fun_business_module SET metadata='{"publishConfig":{"dataScopeEnabled":true,"dataScopeField":"dept_id"}}' WHERE form_id=4;
INSERT INTO fun_fd_string_order (code,title,category_id,dept_id,deleted_at) VALUES
('DENIED','节点',0,8,NULL), ('DELETED','节点',0,7,NOW()), ('VISIBLE','节点',0,7,NULL);
SQL);
    $domain = \app\admin\authorization\service\PermissionResource::domain();
    $database->prepare("INSERT INTO fun_casbin_rule (id,ptype,v0,v1,v2) VALUES (1,'g','admin:987654','role:987654',?)")->execute([$domain]);
    session('admin.id', 987654);
    Db::connect('mysql', true);
    relationMysqlExpect((new \app\admin\authorization\service\DataScopeService())->resolve()['departmentIds'] === [7], '测试必须使用真实的非超级管理员部门授权');
    foreach ([0, '0'] as $category) {
        try {
            $service->listing('fd_string_orders', ['__category' => $category], '', 'asc', 1, 20);
            throw new RuntimeException('1001 条授权数据树列表必须拒绝');
        } catch (InvalidArgumentException $exception) {
            relationMysqlExpect(str_contains($exception->getMessage(), '1000'), '必须因展示上限拒绝');
        }
        $exported = $service->export('fd_string_orders', ['__category' => $category]);
        relationMysqlExpect(count($exported) === 1001, '零值分类的 1001 条授权数据必须完整导出');
        relationMysqlExpect(array_intersect(['DENIED', 'DELETED', 'ORDER-A', 'ORDER-C'], array_column($exported, 'code')) === [], '导出必须排除越权、软删除及其他分类数据');
        relationMysqlExpect(count($service->export('fd_string_orders', ['__category' => $category, 'title' => '节点'])) === 1000, '导出必须叠加普通过滤与分类过滤');
    }
    relationMysqlExpect($service->export('fd_string_orders', ['__category' => 0, 'title' => '不存在']) === [], '无匹配数据应导出空数组');
    relationMysqlExpect(count($service->export('fd_string_orders', ['__category' => 1])) === 1, '非零分类仍可导出');
    $expectedCodes = array_column($service->export('fd_string_orders', ['__category' => 0]), 'code');
    for ($index = 999; $index < 4999; $index++) {
        $code = 'NODE-' . $index;
        $insertTree->execute([$code, '节点']);
        $expectedCodes[] = $code;
    }
    sort($expectedCodes, SORT_STRING);
    $limitedExport = $service->export('fd_string_orders', ['__category' => 0]);
    relationMysqlExpect(count($limitedExport) === 5000, '动态导出必须保留已有独立的 5000 条上限');
    relationMysqlExpect(array_column($limitedExport, 'code') === array_slice($expectedCodes, 0, 5000), '导出应按真实主键升序应用独立上限');

    // 左树使用同一隔离库和真实发布快照，不读取项目业务数据。
    $leftDocument = $treeSchema->document();
    $leftDocument['list'] = ['leftTree' => ['enabled' => true, 'source' => ['type' => 'current'],
        'mapping' => ['valueField' => 'code', 'labelField' => 'title', 'parentField' => 'parent_code', 'targetField' => 'parent_code'],
        'selection' => ['mode' => 'multiple', 'includeDescendants' => false], 'actions' => []]];
    $leftSchema = $repository->compile($leftDocument);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$leftSchema->hash(), $leftSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$leftSchema->hash()]);
    $database->exec("UPDATE fun_fd_string_order SET deleted_at=NOW() WHERE code LIKE 'NODE-%'");
    Db::connect('mysql', true);
    $treeService = new FormDataService(permissionChecker: static fn (string $permission): bool => true);
    $nodes = $treeService->leftTree('fd_string_orders');
    relationMysqlExpect(!in_array('DENIED', array_column($nodes['nodes'], 'value'), true), '左树必须排除无权行');
    relationMysqlExpect(!in_array('DELETED', array_column($nodes['nodes'], 'value'), true), '左树必须排除已删除行');
    relationMysqlFailure(fn () => $treeService->listing('fd_string_orders', ['__leftTree' => ['DENIED']], '', 'asc', 1, 20), '无权选择不得回退全量');
    relationMysqlFailure(fn () => $treeService->listing('fd_string_orders', ['__leftTree' => ['missing']], '', 'asc', 1, 20), '失效选择不得回退全量');
    relationMysqlExpect($treeService->listing('fd_string_orders', ['__leftTree' => ['ORDER-B']], '', 'asc', 1, 20)['total'] === 0, '合法但无关联数据返回空集合');
    relationMysqlExpect($treeService->listing('fd_string_orders', ['__leftTree' => []], '', 'asc', 1, 20)['total'] === 3, '空选择返回全部授权行');
    relationMysqlFailure(fn () => $service->leftTree('fd_string_orders'), '来源读取必须校验业务权限');

    $checkedPermissions = [];
    $strictTreeService = new FormDataService(permissionChecker: static function (string $permission) use (&$checkedPermissions): bool {
        $checkedPermissions[] = $permission;
        return $permission === 'console/form.data/index';
    });
    $strictTreeService->leftTree('fd_string_orders');
    relationMysqlExpect(in_array('console/form.data/index', $checkedPermissions, true), '动态来源必须使用真实路由资源，而不是 generated UI code');
    relationMysqlExpect(method_exists($treeService, 'sourceMeta'), '来源候选必须提供已发布且授权的元数据');
    relationMysqlFailure(fn () => $service->sourceMeta('fd_string_orders'), '无来源读取权限必须拒绝候选');
    relationMysqlExpect($strictTreeService->sourceMeta('fd_string_orders')['primaryKey']['name'] === 'code', '候选保留真实主键');
    $database->exec("UPDATE fun_business_module SET metadata=' {\"target\":{\"type\":\"plugin\",\"pluginCode\":\"example\",\"scope\":\"console\"}}' WHERE form_id=4");
    relationMysqlFailure(fn () => $strictTreeService->leftTree('fd_string_orders'), '插件来源不能借核心动态权限授权');
    $database->exec("UPDATE fun_business_module SET metadata='{\"publishConfig\":{\"dataScopeEnabled\":true,\"dataScopeField\":\"dept_id\"}}' WHERE form_id=4");

    $flatDocument = $leftDocument;
    unset($flatDocument['list']['leftTree']['mapping']['parentField']);
    $flatDocument['list']['leftTree']['actions'] = ['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true];
    $flatSchema = $repository->compile($flatDocument);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$flatSchema->hash(), $flatSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$flatSchema->hash()]);
    relationMysqlExpect($treeService->leftTree('fd_string_orders')['actions']['addChild'] === false, '无父级字段必须在服务端禁用子级动作');
    relationMysqlFailure(fn () => $treeService->leftTreeForm('fd_string_orders', 'addChild', 'ORDER-B', $flatSchema->hash()), '无父级不允许打开子级表单');
    $leftDocument['list']['leftTree']['actions'] = ['create' => true, 'addChild' => true, 'edit' => true, 'delete' => true];
    $leftDocument['nodes'][] = ['id' => 'dept', 'kind' => 'field', 'type' => 'number', 'field' => 'dept_id', 'title' => '部门', 'database' => ['columnType' => 'int'], 'children' => []];
    $leftSchema = $repository->compile($leftDocument);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$leftSchema->hash(), $leftSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$leftSchema->hash()]);
    Db::connect('mysql', true);
    relationMysqlExpect(method_exists($treeService, 'mutateLeftTree'), '缺少左树来源 CRUD');
    $database->exec("ALTER TABLE fun_fd_string_order ALTER COLUMN parent_code SET DEFAULT 'ORDER-B'");
    Db::connect('mysql', true);
    $treeService->mutateLeftTree('fd_string_orders', 'create', '', ['code' => 'TREE-ROOT', 'title' => '根', 'dept_id' => 7], $leftSchema->hash(), $leftSchema->hash());
    relationMysqlExpect($service->detail('fd_string_orders', 'TREE-ROOT')['row']['parent_code'] === null, '根创建必须覆盖数据库默认父级');
    $database->exec('ALTER TABLE fun_fd_string_order ALTER COLUMN parent_code DROP DEFAULT');
    Db::connect('mysql', true);
    relationMysqlFailure(fn () => $treeService->mutateLeftTree('fd_string_orders', 'create', '', ['code' => 'FAKE-ROOT', 'title' => '伪根', 'dept_id' => 7, 'parent_code' => 'TREE-ROOT'], $leftSchema->hash(), $leftSchema->hash()), '新增根节点不得携带真实父级');
    relationMysqlExpect($database->query("SELECT COUNT(*) FROM fun_fd_string_order WHERE code='FAKE-ROOT'")->fetchColumn() == 0, '拒绝伪根不得写入');
    $treeService->mutateLeftTree('fd_string_orders', 'addChild', 'TREE-ROOT', ['code' => 'TREE-CHILD', 'title' => '子', 'dept_id' => 7], $leftSchema->hash(), $leftSchema->hash());
    relationMysqlExpect($service->detail('fd_string_orders', 'TREE-CHILD')['row']['parent_code'] === 'TREE-ROOT', '新增子节点必须绑定父级');
    relationMysqlFailure(fn () => $treeService->mutateLeftTree('fd_string_orders', 'edit', 'TREE-ROOT', ['parent_code' => 'TREE-CHILD'], $leftSchema->hash(), $leftSchema->hash()), '必须拒绝父级环');
    relationMysqlFailure(fn () => $treeService->mutateLeftTree('fd_string_orders', 'delete', 'TREE-ROOT', [], $leftSchema->hash(), $leftSchema->hash()), '必须保护子节点');
    $database->exec("UPDATE fun_fd_string_order SET dept_id=8 WHERE code='TREE-CHILD'");
    relationMysqlFailure(fn () => $treeService->mutateLeftTree('fd_string_orders', 'delete', 'TREE-ROOT', [], $leftSchema->hash(), $leftSchema->hash()), '不可见子节点也必须阻止删除');
    relationMysqlFailure(fn () => $strictTreeService->mutateLeftTree('fd_string_orders', 'delete', 'TREE-ROOT', [], $leftSchema->hash(), $leftSchema->hash()), '来源写操作必须独立授权');
    relationMysqlFailure(fn () => $treeService->mutateLeftTree('fd_string_orders', 'edit', 'TREE-CHILD', ['title' => '越权'], $leftSchema->hash(), $leftSchema->hash()), '来源写入必须限制行范围');
    $treeService->mutateLeftTree('fd_string_orders', 'edit', 'TREE-ROOT', ['title' => '更新'], $leftSchema->hash(), $leftSchema->hash());
    relationMysqlExpect($service->detail('fd_string_orders', 'TREE-ROOT')['row']['title'] === '更新', '更新可省略已授权部门');
        relationMysqlExpect(method_exists($treeService, 'leftTreeForm'), '缺少按来源动作授权的弹窗读取接口');
        $treeForm = $treeService->leftTreeForm('fd_string_orders', 'edit', 'TREE-ROOT', $leftSchema->hash());
        relationMysqlExpect($treeForm['row']['title'] === '更新' && $treeForm['meta']['schemaHash'] === $leftSchema->hash(), '来源弹窗必须使用同一发布版本');
        try {
            $strictTreeService->leftTreeForm('fd_string_orders', 'edit', 'TREE-ROOT', $leftSchema->hash());
            throw new RuntimeException('只读授权不得获取编辑弹窗');
        } catch (InvalidArgumentException $error) {
            relationMysqlExpect(str_contains($error->getMessage(), '操作权限'), '弹窗必须在来源动作权限处拒绝');
        }

    try {
        $treeService->update('fd_string_orders', 'TREE-ROOT', ['parent_code' => 'TREE-CHILD'], [], $leftSchema->hash());
        throw new RuntimeException('普通更新不得绕过来源父级环保护');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '循环') || $error->getMessage() === '数据不存在', '普通更新必须执行父级可见性或环检查');
    }
    $selfValueDocument = $leftDocument;
    $selfValueDocument['list']['leftTree']['mapping']['targetField'] = 'code';
    $originalSource = $repository->compile($definitions[4])->document();
    foreach ($originalSource['nodes'] as $node) {
        if (($node['field'] ?? '') === 'items') $selfValueDocument['nodes'][] = $node;
    }
    $selfValueSchema = $repository->compile($selfValueDocument);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$selfValueSchema->hash(), $selfValueSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$selfValueSchema->hash()]);
    Db::connect('mysql', true);
    $treeService->mutateLeftTree('fd_string_orders', 'create', '', ['code' => 'TREE-LEAF', 'title' => '叶', 'dept_id' => 7], $selfValueSchema->hash(), $selfValueSchema->hash());
    $treeService->mutateLeftTree('fd_string_orders', 'delete', 'TREE-LEAF', [], $selfValueSchema->hash(), $selfValueSchema->hash());
    relationMysqlExpect($database->query("SELECT deleted_at FROM fun_fd_string_order WHERE code='TREE-LEAF'")->fetchColumn() !== null, '同业务按主键筛选不是对自身的外部引用');
    $treeService->mutateLeftTree('fd_string_orders', 'create', '', ['code' => 'TREE-REFERENCED', 'title' => '被引用', 'dept_id' => 7], $selfValueSchema->hash(), $selfValueSchema->hash());
    $database->exec("INSERT INTO fun_fd_string_item (line_code,order_code,label) VALUES ('REF-LINE','TREE-REFERENCED','不可忽略的关联')");
    try {
        $treeService->mutateLeftTree('fd_string_orders', 'delete', 'TREE-REFERENCED', [], $selfValueSchema->hash(), $selfValueSchema->hash());
        throw new RuntimeException('has_many 子表引用必须阻止来源删除');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '引用'), '关联引用保护必须明确拒绝');
    }
    $referencing = $repository->compile($definitions[5])->document();
    $referencing['list'] = ['leftTree' => ['enabled' => true, 'source' => ['type' => 'module', 'module' => 'fd_string_orders'], 'mapping' => ['valueField' => 'code', 'labelField' => 'title', 'targetField' => 'label']]];
    $referencing['list']['leftTree']['mapping']['parentField'] = 'parent_code';
    $referenceSchema = $repository->compile($referencing);
    $selfValueDocument['nodes'] = array_values(array_filter($selfValueDocument['nodes'], static fn (array $node): bool => ($node['field'] ?? '') !== 'items'));
    $selfValueSchema = $repository->compile($selfValueDocument);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$selfValueSchema->hash(), $selfValueSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$selfValueSchema->hash()]);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=5')->execute([$referenceSchema->hash(), $referenceSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=?,deleted_at=NOW() WHERE form_id=5')->execute([$referenceSchema->hash()]);
    $treeService->mutateLeftTree('fd_string_orders', 'create', '', ['code' => 'HIDDEN-REF', 'title' => '隐藏引用', 'dept_id' => 7], $selfValueSchema->hash(), $selfValueSchema->hash());
    $database->exec("INSERT INTO fun_fd_string_item (line_code,order_code,label) VALUES ('HIDDEN-LINE','OTHER','HIDDEN-REF')");
    try {
        $treeService->mutateLeftTree('fd_string_orders', 'delete', 'HIDDEN-REF', [], $selfValueSchema->hash(), $selfValueSchema->hash());
        throw new RuntimeException('软删业务的存量引用不得遗漏');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '引用'), '软删业务引用必须阻止删除：' . $error->getMessage());
    }
    try {
        $treeService->remove('fd_string_orders', 'HIDDEN-REF', $selfValueSchema->hash());
        throw new RuntimeException('普通删除入口不得绕过来源关联保护');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '引用'), '普通删除必须执行关联保护');
    }
    $withoutLeft = $selfValueDocument;
    unset($withoutLeft['list']['leftTree']);
    $withoutLeftSchema = $repository->compile($withoutLeft);
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$withoutLeftSchema->hash(), $withoutLeftSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$withoutLeftSchema->hash()]);
    try {
        $treeService->remove('fd_string_orders', 'HIDDEN-REF', $withoutLeftSchema->hash());
        throw new RuntimeException('跨业务来源未配置自身左树也必须保护引用');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '引用'), '跨业务普通删除必须保护引用');
    }
    foreach ([
        static fn () => $treeService->update('fd_string_orders', 'TREE-ROOT', ['parent_code' => 'TREE-ROOT'], [], $withoutLeftSchema->hash()),
        static fn () => $treeService->create('fd_string_orders', ['code' => 'SELF-ROOT', 'title' => '自环', 'dept_id' => 7, 'parent_code' => 'SELF-ROOT'], [], $withoutLeftSchema->hash()),
        static fn () => $treeService->remove('fd_string_orders', 'TREE-ROOT', $withoutLeftSchema->hash()),
    ] as $write) {
        try {
            $write();
            throw new RuntimeException('跨业务来源普通写入口不得绕过父环或子节点保护');
        } catch (InvalidArgumentException $error) {
            relationMysqlExpect(str_contains($error->getMessage(), '循环') || str_contains($error->getMessage(), '子节点'), '跨业务来源写保护必须明确拒绝：' . $error->getMessage());
        }
    }
    $database->prepare('UPDATE fun_form_schema_version SET schema_hash=?,schema_document=? WHERE form_id=4')->execute([$selfValueSchema->hash(), $selfValueSchema->canonicalJson()]);
    $database->prepare('UPDATE fun_business_module SET published_schema_hash=? WHERE form_id=4')->execute([$selfValueSchema->hash()]);
    $database->exec("UPDATE fun_business_module SET lifecycle_status='published',code='different_module_code' WHERE form_id=4");
    Db::connect('mysql', true);
    $formalPermissions = [];
    $formalService = new FormDataService(permissionChecker: static function (string $permission) use (&$formalPermissions): bool {
        $formalPermissions[] = $permission;
        return $permission === 'console/generated.fdstringorderscontroller/index';
    });
    $formalService->leftTree('fd_string_orders');
    relationMysqlExpect(in_array('console/generated.fdstringorderscontroller/index', $formalPermissions, true), '正式来源权限必须依据生成用表单 key 而非业务 code');
    $database->exec('CREATE TABLE fun_permission (id bigint PRIMARY KEY, status int, is_public int, code varchar(200), deleted_at datetime NULL)');
    $app->config->set(['auth_on' => true, 'superAdminId' => 1], 'funadmin');
    $authorization = new \app\admin\authorization\service\AdminAuthorizationService();
    $authorizedTree = new FormDataService(permissionChecker: [$authorization, 'nodeAccess']);
    $casbin = \app\admin\authorization\service\CasbinService::instance();
    foreach ([[], ['index'], ['index', 'create'], ['index', 'update'], ['index', 'remove']] as $grants) {
        $database->exec("DELETE FROM fun_casbin_rule WHERE ptype='p'");
        foreach ($grants as $offset => $grant) $database->prepare("INSERT INTO fun_casbin_rule (id,ptype,v0,v1,v2,v3) VALUES (?,'p','role:987654',?,'console/generated.fdstringorderscontroller',?)")->execute([10 + $offset, $domain, $grant]);
        $casbin->reload();
        if ($grants === []) {
            try { $authorizedTree->leftTree('fd_string_orders'); throw new RuntimeException('无来源读取授权必须拒绝'); }
            catch (InvalidArgumentException $error) { relationMysqlExpect(str_contains($error->getMessage(), '读取权限'), '真实权限拒绝读取'); }
            continue;
        }
        $actual = $authorizedTree->leftTree('fd_string_orders')['actions'];
        foreach (['create' => 'create', 'addChild' => 'create', 'edit' => 'update', 'delete' => 'remove'] as $action => $grant) {
            relationMysqlExpect($actual[$action] === (($selfValueDocument['list']['leftTree']['actions'][$action] ?? false) && in_array($grant, $grants, true)), '真实 Casbin 权限必须与动作开关取交集：' . $action);
        }
    }
    $popup = $treeService->leftTreeForm('fd_string_orders', 'create', '', $selfValueSchema->hash(), 'title', []);
    relationMysqlExpect(array_key_exists('options', $popup), '来源选项必须通过弹窗动作权限通道返回');
    try {
        $formalService->leftTreeForm('fd_string_orders', 'create', '', $selfValueSchema->hash(), 'title', []);
        throw new RuntimeException('来源只读权限不得请求弹窗选项');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '操作权限'), '选项通道必须先校验来源动作');
    }
    $generatedSchema = $repository->compile($selfValueDocument);
    $generatedColumns = array_map(static fn (array $column): array => $column + ['nullable' => !($column['notnull'] ?? false)], array_values(Db::connect('mysql')->getFields('fun_fd_string_order')));
    $definition = (new \app\admin\development\service\FormCrudDefinitionFactory())->createFromSchema($generatedSchema, ['table_name' => 'fun_fd_string_order'], [], ['primaryKey' => ['code'], 'columns' => $generatedColumns]);
    $generated = \app\common\crud\ProductionTemplateContext::build($definition, ['modelBaseImport' => 'use app\\admin\\model\\BackendModel;']);
    eval(substr($generated['modelContent'], 5));
    preg_match('/namespace ([^;]+);/', $generated['modelContent'], $namespace);
    preg_match('/final class (\w+)/', $generated['modelContent'], $className);
    $modelClass = $namespace[1] . '\\' . $className[1];
    $model = $modelClass::where('code', 'TREE-ROOT')->find();
    relationMysqlExpect($model !== null, '正式模型应读取真实来源节点');
    relationMysqlExpect($model->getAttr('code') === 'TREE-ROOT', '正式字符串主键不得转换为整数');
    try {
        $model->delete();
        throw new RuntimeException('正式普通删除必须拒绝有子节点来源');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '子节点'), '正式删除复用子节点保护');
    }
    try {
        $model->save(['parent_code' => 'TREE-ROOT']);
        throw new RuntimeException('正式普通保存必须拒绝自环');
    } catch (InvalidArgumentException $error) {
        relationMysqlExpect(str_contains($error->getMessage(), '循环'), '正式保存复用父环保护');
    }
    relationMysqlExpect($database->query("SELECT parent_code FROM fun_fd_string_order WHERE code='TREE-ROOT'")->fetchColumn() !== 'TREE-ROOT', '正式失败写入必须回滚');
    echo "form data relation mysql isolation tests: PASS; temporary database cleaned\n";
} finally {
    $database = null;
    $app->config->set($originalDatabaseConfig, 'database');
    Db::connect('mysql', true);
    if (str_starts_with($temporaryDatabase, 'funadmin_form_relation_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . relationMysqlIdentifier($temporaryDatabase));
    }
}
