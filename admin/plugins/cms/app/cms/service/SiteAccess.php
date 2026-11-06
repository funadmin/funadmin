<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;
final class SiteAccess
{
    public function isSuper(string $adminId): bool { return Input::id($adminId)===(string)config('auth.super_admin_id',1); }
    public function assert(string $siteId,string $adminId): void
    {
        $siteId=Input::id($siteId);$adminId=Input::id($adminId);
        if(!Db::table('cms_site')->where('id',$siteId)->whereNull('deleted_at')->exists()) throw new \InvalidArgumentException('站点不存在',404);
        if(!$this->isSuper($adminId)&&!Db::table('cms_site_admin')->where('site_id',$siteId)->where('admin_id',$adminId)->exists()) throw new \InvalidArgumentException('无权访问该站点',403);
    }
    public function super(string $adminId): void { if(!$this->isSuper($adminId)) throw new \InvalidArgumentException('需要平台管理员权限',403); }
}
