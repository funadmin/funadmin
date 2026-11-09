<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/**
 * 表单权限的升级补齐：只给未删除站点中 code 为 administrator 的角色补上缺失的表单操作。
 * 每个站点只补一次：补齐与标记 `cms_site.settings.form_permissions_backfilled_at` 在同一事务内写入；
 * 已有标记的站点直接跳过，因此站点有意收回的表单权限不会在之后的停用→启用（每次更新都要经过）中被补回。
 * 新建站点在创建时已按预设授权，SiteService::save 会在创建时直接写入标记。
 */
final class FormPermissionUpgrade
{
    public const MARKER='form_permissions_backfilled_at';

    /** @return int 本次新增的角色权限行数 */
    public static function backfill(): int
    {
        if(!SiteRoleSchema::ready())return 0;
        $operations=array_values(array_filter(PermissionCatalog::operations(),static fn(string $c)=>str_starts_with($c,'cms:form:')));
        $inserted=0;
        foreach(Db::table('cms_site')->whereNull('deleted_at')->orderBy('id')->pluck('id') as $siteId){
            $inserted+=Db::transaction(static function()use($siteId,$operations): int {
                $site=Db::table('cms_site')->where('id',$siteId)->whereNull('deleted_at')->lockForUpdate()->first(['id','settings']);
                if(!$site)return 0;
                $settings=json_decode((string)$site->settings,true);if(!is_array($settings))$settings=[];
                if(!empty($settings[self::MARKER]))return 0;
                $count=0;
                foreach(Db::table('cms_site_role')->where('site_id',$site->id)->whereNull('deleted_at')->where('code','administrator')->orderBy('id')->pluck('id') as $roleId){
                    $existing=Db::table('cms_site_role_permission')->where('site_id',$site->id)->where('role_id',$roleId)->whereIn('permission_code',$operations)->pluck('permission_code')->all();
                    foreach(array_diff($operations,$existing) as $code)$count+=Db::table('cms_site_role_permission')->insertOrIgnore(['site_id'=>$site->id,'role_id'=>$roleId,'permission_code'=>$code]);
                }
                $settings[self::MARKER]=Input::now();
                Db::table('cms_site')->where('id',$site->id)->update(['settings'=>Input::json($settings)]);
                return $count;
            });
        }
        return $inserted;
    }
}
