<?php
declare(strict_types=1);
namespace app\cms\service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\MySqlConnection;
use support\Db;

final class SiteRoleSchema
{
    public static function tables(): array { return ['cms_site_role','cms_site_role_permission','cms_site_role_member','cms_site_auth_audit']; }
    public static function ready(): bool { foreach(self::tables() as $t)if(!Db::schema()->hasTable($t))return false;return true; }
    public static function install(): void { if(!self::ready())self::create(Db::schema(),true); }
    public static function mysql(): string
    {
        $connection=new MySqlConnection(static fn()=>throw new \RuntimeException('Export must not connect'),'', 'fun_', ['driver'=>'mysql','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','engine'=>'InnoDB']);
        $queries=$connection->pretend(static fn()=>self::create($connection->getSchemaBuilder(),false));
        return "-- CMS site roles; generated from SiteRoleSchema.php.\n".implode(";\n",array_column($queries,'query')).";\n";
    }
    private static function create(Builder $s,bool $skipExisting): void
    {
        $definitions=[
            'cms_site_role'=>static function(Blueprint $t): void {$t->id();$t->unsignedBigInteger('site_id');$t->string('code',64);$t->string('name',100);$t->boolean('status')->default(true);$t->timestamps();$t->softDeletes();$t->unique(['site_id','code']);},
            'cms_site_role_permission'=>static function(Blueprint $t): void {$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('role_id');$t->string('permission_code',100);$t->unique(['site_id','role_id','permission_code'],'cms_role_permission_unique');},
            'cms_site_role_member'=>static function(Blueprint $t): void {$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('role_id');$t->unsignedBigInteger('admin_id');$t->unique(['site_id','role_id','admin_id'],'cms_role_member_unique');$t->index(['site_id','admin_id']);},
            'cms_site_auth_audit'=>static function(Blueprint $t): void {$t->id();$t->unsignedBigInteger('site_id');$t->unsignedBigInteger('actor_admin_id');$t->string('target_type',30);$t->unsignedBigInteger('target_id');$t->string('operation',40);$t->json('before_state');$t->json('after_state');$t->dateTime('created_at');$t->index(['site_id','created_at']);},
        ];
        foreach($definitions as $table=>$definition)if(!$skipExisting||!$s->hasTable($table))$s->create($table,$definition);
    }
    /** 启用时为每个站点补齐标准预设角色；成员只通过角色分配获得权限。 */
    public static function bootstrap(): void
    {
        if(!self::ready())throw new \InvalidArgumentException('请完成 CMS 升级后再使用站点权限',409);
        foreach(Db::table('cms_site')->whereNull('deleted_at')->pluck('id') as $id)self::seedSite((string)$id);
    }
    public static function seedSite(string $siteId): void
    {
        if(!self::ready())return;
        Db::transaction(function()use($siteId): void {
            (new SiteService())->lock($siteId);
            foreach(PermissionCatalog::presets() as $code=>[$name,$permissions]){
                if(Db::table('cms_site_role')->where('site_id',$siteId)->where('code',$code)->whereNull('deleted_at')->exists())continue;
                $role=Db::table('cms_site_role')->insertGetId(['site_id'=>$siteId,'code'=>$code,'name'=>$name,'status'=>1,'created_at'=>Input::now(),'updated_at'=>Input::now()]);
                foreach($permissions as $p)Db::table('cms_site_role_permission')->insert(['site_id'=>$siteId,'role_id'=>$role,'permission_code'=>$p]);
            }
        });
    }
}
