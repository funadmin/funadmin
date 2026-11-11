<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/**
 * 站点权限的升级补齐：只给未删除站点中 code 为 administrator 的角色补上缺失的表单操作（backfill）
 * 或 1.6.0 回收站操作（backfillRecycle，标记 recycle_permissions_backfilled_at）。
 * 每个站点只补一次：补齐与标记 `cms_site.settings.form_permissions_backfilled_at` 在同一事务内写入；
 * 已有标记的站点直接跳过，因此站点有意收回的表单权限不会在之后的停用→启用（每次更新都要经过）中被补回。
 * 新建站点在创建时已按预设授权，SiteService::save 会在创建时直接写入标记。
 */
final class FormPermissionUpgrade
{
    public const MARKER='form_permissions_backfilled_at';
    /** 1.6.0 回收站操作的补齐标记（规则相同，各自只补一次）。 */
    public const RECYCLE_MARKER='recycle_permissions_backfilled_at';
    /** 1.4 起的表单操作（不含之后加入的回收站操作，它们由 backfillRecycle 单独补齐）。 */
    private const FORM=['cms:form:list','cms:form:manage','cms:form:submissions','cms:form:handle','cms:form:export','cms:form:delete'];
    /** @return list<string> 由系统维护的补齐标记 */
    public static function markers(): array { return [self::MARKER,self::RECYCLE_MARKER]; }

    /** @return int 本次新增的角色权限行数 */
    public static function backfill(): int { return self::grant(self::MARKER,self::FORM); }
    /** @return int 本次新增的角色权限行数 */
    public static function backfillRecycle(): int { return self::grant(self::RECYCLE_MARKER,PermissionCatalog::RECYCLE); }

    /** @param list<string> $operations */
    private static function grant(string $marker,array $operations): int
    {
        if(!SiteRoleSchema::ready())return 0;
        $inserted=0;
        foreach(Db::table('cms_site')->whereNull('deleted_at')->orderBy('id')->pluck('id') as $siteId){
            $inserted+=Db::transaction(static function()use($siteId,$operations,$marker): int {
                $site=Db::table('cms_site')->where('id',$siteId)->whereNull('deleted_at')->lockForUpdate()->first(['id','settings']);
                if(!$site)return 0;
                $settings=json_decode((string)$site->settings,true);if(!is_array($settings))$settings=[];
                if(!empty($settings[$marker]))return 0;
                $count=0;
                foreach(Db::table('cms_site_role')->where('site_id',$site->id)->whereNull('deleted_at')->where('code','administrator')->orderBy('id')->pluck('id') as $roleId){
                    $existing=Db::table('cms_site_role_permission')->where('site_id',$site->id)->where('role_id',$roleId)->whereNull('deleted_at')->whereIn('permission_code',$operations)->pluck('permission_code')->all();
                    foreach(array_diff($operations,$existing) as $code)$count+=Db::table('cms_site_role_permission')->insertOrIgnore(['site_id'=>$site->id,'role_id'=>$roleId,'permission_code'=>$code]);
                }
                $settings[$marker]=Input::now();
                Db::table('cms_site')->where('id',$site->id)->update(['settings'=>Input::json($settings)]);
                return $count;
            });
        }
        return $inserted;
    }
}
