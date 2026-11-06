<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/Document/bootstrap.php';

use app\document\service\Schema;
use PHPUnit\Framework\TestCase;

final class DocumentMigrationTest extends TestCase
{
    public function test_shipped_migration_is_generated_from_the_schema_definition(): void
    {
        $file = dirname(__DIR__) . '/plugins/document/database/migrations/001_document_baseline.sql';
        self::assertFileExists($file);
        self::assertSame(Schema::mysql(), file_get_contents($file));
    }

    public function test_schema_defines_the_document_contract(): void
    {
        $sql = Schema::mysql();
        foreach (['document_categories', 'documents', 'document_category_links', 'document_revisions', 'document_events'] as $table) {
            self::assertStringContainsString('`fun_' . $table . '`', $sql);
        }
        foreach (['scope', 'plugin_code', 'slug', 'content_json', 'plain_text', 'lock_version', 'revision_no', 'current_revision_no'] as $column) {
            self::assertStringContainsString('`' . $column . '`', $sql);
        }
    }
}
