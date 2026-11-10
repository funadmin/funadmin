<?php
declare(strict_types=1);
namespace app\cms\service;
use app\admin\authentication\model\Admin;
use app\admin\authorization\service\AuthorizationService;
use app\cms\model\{Site,SiteAdmin,SiteRolePermission};
use support\Db;

final class SiteAuthorization
{
    public function hostCodes(string $adminId): array
    {
        if((new SiteAccess())->isSuper($adminId))return PermissionCatalog::hostCodes();
        if(!Db::schema()->hasTable('admin')||!Admin::query()->where('id',Input::id($adminId))->where('status',1)->whereNull('deleted_at')->exists())return [];
        return (new AuthorizationService())->permissionCodes((int)$adminId);
    }
    public function effective(string $siteId,string $adminId,?array $globalCodes=null): array
    {
        $siteId=Input::id($siteId);$adminId=Input::id($adminId);
        if(!SiteRoleSchema::ready())throw new \InvalidArgumentException('请完成 CMS 升级后再使用站点权限',409);
        if(!Site::query()->where('id',$siteId)->exists())return [];
        if((new SiteAccess())->isSuper($adminId))return PermissionCatalog::operations();
        if(!SiteAdmin::query()->forSite($siteId)->where('admin_id',$adminId)->exists())return [];
        $registered=PermissionCatalog::registered();
        $globalCodes=array_values(array_intersect($globalCodes??$this->hostCodes($adminId),$registered));
        $roles=Db::table('cms_site_role_member as m')->join('cms_site_role as r',function($j){$j->on('r.id','=','m.role_id')->on('r.site_id','=','m.site_id');})->where('m.site_id',$siteId)->where('m.admin_id',$adminId)->where('r.status',1)->whereNull('r.deleted_at')->pluck('r.id');
        $assigned=SiteRolePermission::query()->forSite($siteId)->whereIn('role_id',$roles)->pluck('permission_code')->all();
        return array_values(array_filter(PermissionCatalog::operations(),fn($p)=>in_array($p,$registered,true)&&in_array($p,$assigned,true)&&array_diff(PermissionCatalog::ceilings($p),$globalCodes)===[]));
    }
    public function assert(string $siteId,string $adminId,string $operation): void
    {
        if(!in_array($operation,$this->effective($siteId,$adminId),true))throw new \InvalidArgumentException('没有本站操作权限：'.$operation,403);
    }
    public function acrossSites(string $adminId,?array $globalCodes=null): array
    {
        $result=[];foreach(Site::query()->pluck('id') as $site)$result=array_merge($result,$this->effective((string)$site,$adminId,$globalCodes));return array_values(array_unique($result));
    }
}
