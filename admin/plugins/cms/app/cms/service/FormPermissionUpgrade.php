<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/** 表单权限的升级补齐：只给未删除站点中 code 为 administrator 的角色补上缺失的表单操作，可重复执行。 */
final class FormPermissionUpgrade
{
    /** @return int 本次新增的角色权限行数 */
    public static function backfill(): int
    {
        if(!SiteRoleSchema::ready())return 0;
        $operations=array_values(array_filter(PermissionCatalog::operations(),static fn(string $c)=>str_starts_with($c,'cms:form:')));
        $roles=Db::table('cms_site_role as r')->join('cms_site as s','s.id','=','r.site_id')->whereNull('s.deleted_at')->whereNull('r.deleted_at')->where('r.code','administrator')->orderBy('r.id')->get(['r.id','r.site_id']);
        $inserted=0;
        foreach($roles as $role){
            $existing=Db::table('cms_site_role_permission')->where('site_id',$role->site_id)->where('role_id',$role->id)->whereIn('permission_code',$operations)->pluck('permission_code')->all();
            foreach(array_diff($operations,$existing) as $code)$inserted+=Db::table('cms_site_role_permission')->insertOrIgnore(['site_id'=>$role->site_id,'role_id'=>$role->id,'permission_code'=>$code]);
        }
        return $inserted;
    }
}
