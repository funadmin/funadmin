<?php

declare(strict_types=1);

namespace Tests;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;
use app\common\model\concern\RestoreConflictException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

final class SoftDeleteTokenItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'soft_delete_token_items';
    protected $guarded = [];
}

final class SoftDeleteVetoItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'soft_delete_token_items';
    protected $guarded = [];

    public static bool $veto = false;

    protected static function booted(): void
    {
        static::updating(static fn () => self::$veto ? false : null);
    }
}

final class SoftDeletePlainItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $table = 'soft_delete_plain_items';
    protected $guarded = [];
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SoftDeleteTraitTest extends TestCase
{
    protected function setUp(): void
    {
        $schema = Db::schema();
        $schema->dropIfExists('soft_delete_token_items');
        $schema->create('soft_delete_token_items', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 64);
            $table->string('name', 64)->default('');
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_token')->default(0);
            $table->unique(['code', 'deleted_token'], 'uniq_code');
        });
        $schema->dropIfExists('soft_delete_plain_items');
        $schema->create('soft_delete_plain_items', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code', 64);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
        });
    }

    private function row(string $table, int $id): ?object
    {
        return Db::table($table)->where('id', $id)->first();
    }

    public function test_delete_sets_deleted_at_and_token_to_id(): void
    {
        SoftDeleteTokenItem::create(['code' => 'a']);
        $item = SoftDeleteTokenItem::create(['code' => 'zhangsan']);

        self::assertTrue($item->hasDeletedToken());
        self::assertSame(0, (int) $this->row('soft_delete_token_items', $item->id)->deleted_token);

        $item->delete();

        $row = $this->row('soft_delete_token_items', $item->id);
        self::assertNotNull($row->deleted_at);
        self::assertSame($item->id, (int) $row->deleted_token);
        self::assertSame($item->id, (int) $item->deleted_token);
        self::assertTrue($item->trashed());
        self::assertNull(SoftDeleteTokenItem::find($item->id));
        self::assertNotNull(SoftDeleteTokenItem::withTrashed()->find($item->id));
        self::assertSame(1, SoftDeleteTokenItem::onlyTrashed()->count());
    }

    public function test_recreate_same_unique_value_after_delete_succeeds(): void
    {
        $first = SoftDeleteTokenItem::create(['code' => 'zhangsan']);
        $first->delete();

        $second = SoftDeleteTokenItem::create(['code' => 'zhangsan']);
        self::assertNotSame($first->id, $second->id);
        self::assertSame(2, SoftDeleteTokenItem::withTrashed()->where('code', 'zhangsan')->count());

        $this->expectException(QueryException::class);
        SoftDeleteTokenItem::create(['code' => 'zhangsan']);
    }

    public function test_batch_delete_sets_each_row_token_to_its_id(): void
    {
        $ids = [];
        foreach (['a', 'b', 'c'] as $code) {
            $ids[$code] = SoftDeleteTokenItem::create(['code' => $code])->id;
        }

        $affected = SoftDeleteTokenItem::query()->whereIn('code', ['a', 'b'])->delete();

        self::assertSame(2, $affected);
        foreach (['a', 'b'] as $code) {
            $row = $this->row('soft_delete_token_items', $ids[$code]);
            self::assertNotNull($row, 'batch delete must not physically delete');
            self::assertNotNull($row->deleted_at);
            self::assertSame($ids[$code], (int) $row->deleted_token);
        }
        $live = $this->row('soft_delete_token_items', $ids['c']);
        self::assertNull($live->deleted_at);
        self::assertSame(0, (int) $live->deleted_token);

        // Same codes can be recreated after batch delete.
        SoftDeleteTokenItem::create(['code' => 'a']);
        SoftDeleteTokenItem::create(['code' => 'b']);
        self::assertSame(3, SoftDeleteTokenItem::count());
    }

    public function test_restore_clears_deleted_at_and_token(): void
    {
        $single = SoftDeleteTokenItem::create(['code' => 'single']);
        $single->delete();
        $trashed = SoftDeleteTokenItem::withTrashed()->find($single->id);
        self::assertTrue($trashed->restore());
        self::assertFalse($trashed->trashed());
        self::assertSame(0, (int) $trashed->deleted_token);
        $row = $this->row('soft_delete_token_items', $single->id);
        self::assertNull($row->deleted_at);
        self::assertSame(0, (int) $row->deleted_token);

        $ids = [];
        foreach (['x', 'y'] as $code) {
            $ids[] = SoftDeleteTokenItem::create(['code' => $code])->id;
        }
        SoftDeleteTokenItem::query()->whereIn('id', $ids)->delete();
        self::assertSame(2, SoftDeleteTokenItem::onlyTrashed()->whereIn('id', $ids)->restore());
        foreach ($ids as $id) {
            $row = $this->row('soft_delete_token_items', $id);
            self::assertNull($row->deleted_at);
            self::assertSame(0, (int) $row->deleted_token);
        }
        self::assertSame(3, SoftDeleteTokenItem::count());
    }

    public function test_force_delete_removes_row(): void
    {
        $item = SoftDeleteTokenItem::create(['code' => 'gone']);
        $item->forceDelete();
        self::assertNull($this->row('soft_delete_token_items', $item->id));

        $trashed = SoftDeleteTokenItem::create(['code' => 'gone-too']);
        $trashed->delete();
        SoftDeleteTokenItem::withTrashed()->find($trashed->id)->forceDelete();
        self::assertNull($this->row('soft_delete_token_items', $trashed->id));
    }

    public function test_restore_conflicting_with_live_row_throws_clear_error_and_changes_nothing(): void
    {
        $old = SoftDeleteTokenItem::create(['code' => 'zhangsan', 'name' => 'old']);
        $old->delete();
        $new = SoftDeleteTokenItem::create(['code' => 'zhangsan', 'name' => 'new']);
        $oldBefore = $this->row('soft_delete_token_items', $old->id);
        $newBefore = $this->row('soft_delete_token_items', $new->id);

        $trashed = SoftDeleteTokenItem::withTrashed()->find($old->id);
        try {
            $trashed->restore();
            self::fail('restore should conflict');
        } catch (RestoreConflictException $error) {
            self::assertInstanceOf(\RuntimeException::class, $error);
            self::assertSame(409, $error->getCode());
            self::assertSame('已存在相同的记录，无法恢复，请先修改或删除现有记录', $error->getMessage());
        }
        self::assertTrue($trashed->trashed(), 'in-memory model must still be trashed');
        self::assertSame($old->id, (int) $trashed->deleted_token);
        self::assertFalse($trashed->isDirty());
        self::assertEquals($oldBefore, $this->row('soft_delete_token_items', $old->id));
        self::assertEquals($newBefore, $this->row('soft_delete_token_items', $new->id));

        // Batch restore hits the same conflict.
        try {
            SoftDeleteTokenItem::onlyTrashed()->where('id', $old->id)->restore();
            self::fail('batch restore should conflict');
        } catch (RestoreConflictException $error) {
            self::assertSame(409, $error->getCode());
        }
        self::assertEquals($oldBefore, $this->row('soft_delete_token_items', $old->id));
        self::assertEquals($newBefore, $this->row('soft_delete_token_items', $new->id));
    }

    public function test_table_without_token_column_only_sets_deleted_at(): void
    {
        $item = SoftDeletePlainItem::create(['code' => 'p1']);
        self::assertFalse($item->hasDeletedToken());

        $item->delete();
        self::assertNotNull($this->row('soft_delete_plain_items', $item->id)->deleted_at);
        self::assertNull(SoftDeletePlainItem::find($item->id));

        $item->restore();
        self::assertNull($this->row('soft_delete_plain_items', $item->id)->deleted_at);

        $other = SoftDeletePlainItem::create(['code' => 'p2']);
        self::assertSame(2, SoftDeletePlainItem::query()->delete());
        self::assertSame(2, SoftDeletePlainItem::onlyTrashed()->count());
        self::assertSame(2, SoftDeletePlainItem::onlyTrashed()->restore());
        self::assertNull($this->row('soft_delete_plain_items', $other->id)->deleted_at);
        self::assertSame(2, SoftDeletePlainItem::count());
    }

    public function test_column_added_after_check_is_used_without_flush(): void
    {
        $item = SoftDeletePlainItem::create(['code' => 'late']);
        self::assertFalse($item->hasDeletedToken());

        Db::schema()->table('soft_delete_plain_items', static function (Blueprint $table): void {
            $table->unsignedBigInteger('deleted_token')->default(0);
        });

        self::assertTrue($item->hasDeletedToken());
        $item->delete();
        self::assertSame($item->id, (int) $this->row('soft_delete_plain_items', $item->id)->deleted_token);

        $other = SoftDeletePlainItem::create(['code' => 'late-batch']);
        SoftDeletePlainItem::query()->where('id', $other->id)->delete();
        self::assertSame($other->id, (int) $this->row('soft_delete_plain_items', $other->id)->deleted_token);
    }

    public function test_restore_vetoed_by_listener_keeps_model_trashed(): void
    {
        $item = SoftDeleteVetoItem::create(['code' => 'veto']);
        $item->delete();
        $trashed = SoftDeleteVetoItem::withTrashed()->find($item->id);

        SoftDeleteVetoItem::$veto = true;
        self::assertFalse($trashed->restore());

        self::assertTrue($trashed->trashed());
        self::assertSame($item->id, (int) $trashed->deleted_token);
        self::assertFalse($trashed->isDirty());
        $row = $this->row('soft_delete_token_items', $item->id);
        self::assertNotNull($row->deleted_at);
        self::assertSame($item->id, (int) $row->deleted_token);
    }

    public function test_not_null_failure_with_unique_text_in_bindings_is_not_unique_violation(): void
    {
        Db::schema()->dropIfExists('soft_delete_not_null_items');
        Db::schema()->create('soft_delete_not_null_items', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('label', 64);
            $table->string('required', 64);
        });

        try {
            Db::table('soft_delete_not_null_items')->insert(['label' => 'UNIQUE constraint failed', 'required' => null]);
            self::fail('NOT NULL insert should fail');
        } catch (QueryException $error) {
            self::assertStringContainsString('UNIQUE constraint failed', $error->getMessage());
            self::assertFalse(RestoreConflictException::isUniqueViolation($error));
        }
    }
}
