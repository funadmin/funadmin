<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\service;
use Illuminate\Database\Schema\{Blueprint,Builder};
use Illuminate\Database\MySqlConnection;
use support\Db;
use app\admin\plugins\oss\model\OssSetting;
final class Schema
{
    public static function ready():bool{return Db::schema()->hasTable('oss_profile')&&Db::schema()->hasTable('oss_setting');}
    /**
     * 按迁移顺序建表：基线（001）+ 软删除（002），已有的表跳过。002 是按 information_schema 守卫的手写 PREPARE 迁移，
     * 无法由蓝图导出；softDelete() 是它的等价实现，MySQL 上的一致性见 tests/Console/MySqlOssSoftDeleteUpgradeTest.php。
     */
    public static function install():void{self::create(Db::schema(),true);self::softDelete(Db::schema());OssSetting::query()->insertOrIgnore(['id'=>1,'current_profile_id'=>null]);}
    /** 002：oss_profile 加 deleted_at 与索引；主键仍是字符串 id，没有非主键唯一索引，不加 deleted_token。oss_setting 豁免。 */
    private static function softDelete(Builder $schema):void{
        if(!$schema->hasColumn('oss_profile','deleted_at'))$schema->table('oss_profile',static function(Blueprint $t):void{$t->dateTime('deleted_at')->nullable();$t->index('deleted_at','idx_oss_profile_deleted_at');});
    }
    private static function create(Builder $schema,bool $skip):void{
        if(!$skip||!$schema->hasTable('oss_profile'))$schema->create('oss_profile',static function(Blueprint $t):void{
            $t->string('id',16)->primary();$t->string('name',100);$t->string('provider',20);$t->string('bucket',100);$t->string('region',60)->default('');$t->string('endpoint',255)->default('');$t->string('domain',500);$t->string('prefix',200)->default('');$t->text('credentials');$t->timestamps();
        });
        if(!$skip||!$schema->hasTable('oss_setting'))$schema->create('oss_setting',static function(Blueprint $t):void{$t->unsignedTinyInteger('id')->primary();$t->string('current_profile_id',16)->nullable();});
    }
    public static function mysql():string{
        $connection=new MySqlConnection(static fn()=>throw new \LogicException('Export cannot connect'),'', 'fun_', ['driver'=>'mysql','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','engine'=>'InnoDB']);
        $queries=$connection->pretend(static fn()=>self::create($connection->getSchemaBuilder(),false));
        return "-- OSS plugin storage profiles. Credentials encrypted by application.\n".implode(";\n",array_column($queries,'query')).";\nINSERT IGNORE INTO `fun_oss_setting` (`id`, `current_profile_id`) VALUES (1, NULL);\n";
    }
}
