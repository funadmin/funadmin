<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use app\cms\service\Input;
use app\cms\service\SiteRoleService;
use app\cms\service\PermissionCatalog;
use app\cms\service\SiteAuthorization;
#[RouteGroup('/admin/plugins/cms/access')]
final class Access extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(function():array {$id=$this->site('cms:access:list');$s=new SiteRoleService();$ceilings=[];foreach(PermissionCatalog::operations() as $p)$ceilings[$p]=PermissionCatalog::ceilings($p);return ['catalog'=>PermissionCatalog::groups(),'ceilings'=>$ceilings,'host_permissions'=>(new SiteAuthorization())->hostCodes($this->actor()),'roles'=>$s->roles($id,$this->actor()),'members'=>$s->members($id,$this->actor())];}); }
    #[Get('/candidates')]
    public function candidates(): Response { return $this->action(fn()=>(new SiteRoleService())->candidates($this->site('cms:access:members'),$this->actor())); }
    #[Post('/roles/save')]
    public function saveRole(): Response { return $this->action(fn()=>(new SiteRoleService())->saveRole($this->site('cms:access:roles'),$this->actor(),$this->input())); }
    #[Post('/members/save')]
    public function assign(): Response { return $this->action(function():bool {(new SiteRoleService())->assign($this->site('cms:access:members'),$this->actor(),Input::id($this->input()['admin_id']??''),$this->input()['role_ids']??[]);return true;}); }
    /** 回收站：被移除的成员身份及与它一起移除的角色。恢复与彻底删除各需独立的本站权限；彻底删除不可恢复。 */
    #[Get('/members/recycle')]
    public function memberRecycle(): Response { return $this->action(fn()=>(new SiteRoleService())->trashedMembers($this->site('cms:access:recycle'),$this->actor())); }
    #[Post('/members/restore')]
    public function memberRestore(): Response { return $this->action(fn()=>(new SiteRoleService())->restoreMembers($this->site('cms:access:restore'),$this->actor(),$this->input()['ids']??null)); }
    #[Post('/members/destroy')]
    public function memberDestroy(): Response { return $this->action(fn()=>(new SiteRoleService())->destroyMembers($this->site('cms:access:destroy'),$this->actor(),$this->input()['ids']??null)); }
    #[Post('/members/remove')]
    public function remove(): Response { return $this->action(function():bool {(new SiteRoleService())->remove($this->site('cms:access:members'),$this->actor(),Input::id($this->input()['admin_id']??''));return true;}); }
}
