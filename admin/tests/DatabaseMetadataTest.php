<?php

declare(strict_types=1);

namespace Tests;

use app\common\crud\SchemaInspector;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class DatabaseMetadataTest extends TestCase
{
    public function test_native_metadata_preserves_physical_names_comments_indexes_and_foreign_keys(): void
    {
        $connection = Db::connection();
        $schema = $connection->getSchemaBuilder();
        $parent = 'meta_parent_' . bin2hex(random_bytes(4));
        $child = 'meta_child_' . bin2hex(random_bytes(4));
        try {
            $schema->create($parent, static function (Blueprint $table): void { $table->id(); });
            $schema->create($child, static function (Blueprint $table) use ($connection, $parent): void {
                $table->id();
                $table->string('title', 80)->nullable()->comment('中文标题');
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->json('metadata')->nullable();
                $table->unique('title');
                $table->foreign('parent_id')->references('id')->on($parent);
            });
            $physical = $connection->getTablePrefix() . $child;
            $metadata = SchemaInspector::forConnection($connection->getName())->inspect($physical);
            self::assertSame($physical, $metadata['table']);
            self::assertSame(['id'], $metadata['primaryKey']);
            self::assertFalse($metadata['pivot']);
            $columns = array_column($metadata['columns'], null, 'name');
            self::assertTrue($columns['title']['nullable']);
            if ($connection->getDriverName() !== 'sqlite') self::assertSame('中文标题', $columns['title']['comment']);
            self::assertTrue($columns['id']['primary']);
            self::assertSame('auto_increment', $columns['id']['extra']);
            self::assertSame(['title'], $metadata['uniqueIndexes'][0]['columns']);
            self::assertSame($connection->getTablePrefix() . $parent, $metadata['foreignKeys'][0]['referencedTable']);
            self::assertContains($physical, array_column(SchemaInspector::forConnection($connection->getName())->tables(), 'name'));
        } finally {
            $schema->dropIfExists($child);
            $schema->dropIfExists($parent);
        }
    }
}
