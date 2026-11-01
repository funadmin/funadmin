<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\model\Admin;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authentication\controller\AdminAuth;
use app\admin\authorization\model\AdminDepartment;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AdminLayoutContractTest extends TestCase
{
    protected function setUp(): void
    {
        Db::schema()->dropIfExists('admin');
        Db::schema()->create('admin', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 20)->unique();
            $table->string('password', 200);
            $table->boolean('status')->default(true);
            $table->string('token', 100)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Db::schema()->dropIfExists('admin_department');
        Db::schema()->create('admin_department', static function (Blueprint $table): void {
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('dept_id');
            $table->timestamps();
            $table->primary(['admin_id', 'dept_id']);
        });
    }

    protected function tearDown(): void
    {
        AdminDepartment::flushEventListeners();
        Db::schema()->dropIfExists('admin_department');
        Db::schema()->dropIfExists('admin');
    }

    public function test_department_associations_use_the_existing_model_and_legacy_table(): void
    {
        self::assertSame('admin_department', (new AdminDepartment())->getTable());
        $created = 0;
        AdminDepartment::created(static function () use (&$created): void {
            $created++;
        });
        Admin::syncDepartments(7, [2, '2', 3, 0, -1]);
        Admin::syncDepartments(8, [4]);
        self::assertSame(3, $created);
        self::assertSame([1, 2, 3], ((new Admin())->setRawAttributes(['id' => 7, 'dept_id' => 1]))->departmentIds());
        self::assertSame([2, 3], ((new Admin())->setRawAttributes(['id' => 7, 'dept_id' => 2]))->departmentIds());
        Admin::syncDepartments(7, [5]);
        self::assertSame([5], ((new Admin())->setRawAttributes(['id' => 7]))->departmentIds());
        self::assertSame([4], ((new Admin())->setRawAttributes(['id' => 8]))->departmentIds());
        Admin::syncDepartments(7, []);
        self::assertSame([], ((new Admin())->setRawAttributes(['id' => 7]))->departmentIds());
    }

    public function test_authentication_uses_business_module_namespace_and_legacy_table(): void
    {
        self::assertSame('admin', (new Admin())->getTable());
        self::assertTrue(class_exists(AdminSessionService::class));
        self::assertTrue(class_exists(BrowserAuthService::class));
        self::assertTrue(class_exists(AdminAuth::class));
    }
}
