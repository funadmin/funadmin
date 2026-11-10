<?php

declare(strict_types=1);

namespace Tests;

use app\admin\plugin\service\PluginInfrastructureService;
use app\common\service\database\MigrationEffectVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** MySQL 类型规范化（migration 声明 ↔ information_schema.COLUMN_TYPE，含 5.7 / MariaDB 的显示宽度）。 */
final class MigrationEffectVerifierTest extends TestCase
{
    public static function equivalent(): array
    {
        return [
            'unsigned bigint with display width (5.7 / MariaDB)' => ['bigint(20) unsigned', 'bigint unsigned'],
            'unsigned int with display width' => ['int(10) unsigned', 'int unsigned'],
            'zerofill keeps its attribute' => ['int(10) unsigned zerofill', 'int unsigned zerofill'],
            'int display width' => ['int(11)', 'int'],
            'integer alias' => ['integer', 'int(11)'],
            'tinyint other than (1) loses its width' => ['tinyint(4)', 'tinyint'],
            'boolean is tinyint(1)' => ['boolean', 'tinyint(1)'],
            'bool is tinyint(1)' => ['bool', 'tinyint(1)'],
            'varchar keeps its length' => ['VARCHAR( 64 )', 'varchar(64)'],
            'decimal precision and scale' => ['decimal(10, 2)', 'decimal(10,2)'],
            'numeric alias' => ['numeric(10,2)', 'decimal(10,2)'],
            'enum spacing' => ["enum('a', 'b')", "enum('a','b')"],
            'char without length' => ['char', 'char(1)'],
        ];
    }

    #[DataProvider('equivalent')]
    public function test_equivalent_types_normalize_identically(string $declared, string $reported): void
    {
        self::assertSame(MigrationEffectVerifier::normalizeType($reported), MigrationEffectVerifier::normalizeType($declared));
    }

    public function test_exact_normal_forms(): void
    {
        self::assertSame('bigint unsigned', MigrationEffectVerifier::normalizeType('bigint(20) unsigned'));
        self::assertSame('int', MigrationEffectVerifier::normalizeType('int(11)'));
        self::assertSame('tinyint(1)', MigrationEffectVerifier::normalizeType('tinyint(1)'));
        self::assertSame('tinyint(1) unsigned', MigrationEffectVerifier::normalizeType('TINYINT(1) UNSIGNED'));
        self::assertSame('varchar(64)', MigrationEffectVerifier::normalizeType('varchar(64)'));
        self::assertSame('decimal(10,2)', MigrationEffectVerifier::normalizeType('decimal(10, 2)'));
    }

    public static function different(): array
    {
        return [
            'signedness' => ['bigint unsigned', 'bigint(20)'],
            'integer size' => ['int unsigned', 'bigint(20) unsigned'],
            // tinyint(1) 是布尔约定，不与普通 tinyint 互相接管。
            'boolean vs tinyint' => ['tinyint(1)', 'tinyint(4)'],
            'boolean vs plain tinyint' => ['boolean', 'tinyint'],
            'varchar length' => ['varchar(64)', 'varchar(100)'],
            'decimal scale' => ['decimal(10,2)', 'decimal(10,3)'],
            'char vs varchar' => ['char(64)', 'varchar(64)'],
        ];
    }

    #[DataProvider('different')]
    public function test_different_types_stay_different(string $left, string $right): void
    {
        self::assertNotSame(MigrationEffectVerifier::normalizeType($left), MigrationEffectVerifier::normalizeType($right));
    }

    public function test_json_equals_longtext_only_on_mariadb(): void
    {
        self::assertNotSame(MigrationEffectVerifier::normalizeType('json'), MigrationEffectVerifier::normalizeType('longtext'));
        self::assertSame(MigrationEffectVerifier::normalizeType('longtext', true), MigrationEffectVerifier::normalizeType('json', true));
        self::assertNotSame(MigrationEffectVerifier::normalizeType('text', true), MigrationEffectVerifier::normalizeType('json', true));
    }

    public function test_parsed_migration_type_matches_reported_column_type(): void
    {
        $effects = MigrationEffectVerifier::effects("alter table `fun_x` add `owner_id` bigint unsigned not null default '0', add `flag` tinyint(1) not null;");
        self::assertSame(['bigint unsigned', 'tinyint(1)'], array_column($effects, 'type'));
        self::assertSame('bigint unsigned', MigrationEffectVerifier::normalizeType('bigint(20) unsigned'));
    }

    public function test_failed_migration_points_to_adopt_only_for_already_existing_structure(): void
    {
        $duplicate = new \RuntimeException('outer', 0, new \PDOException("SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name 'fingerprint'"));
        self::assertStringContainsString('plugin:migrate cms --adopt=', PluginInfrastructureService::adoptHint('cms', $duplicate));
        self::assertStringContainsString('--adopt', PluginInfrastructureService::adoptHint('cms', new \RuntimeException("Duplicate key name 'idx'")));
        self::assertSame('', PluginInfrastructureService::adoptHint('cms', new \RuntimeException('Table doesn\'t exist')));
    }
}
