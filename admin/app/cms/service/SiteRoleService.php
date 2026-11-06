<?php
declare(strict_types=1);
namespace app\cms\service;
use app\admin\authentication\model\Admin;
use app\admin\authorization\service\RoleScopeService;
use support\Db;

final class SiteRoleService
{
    public function roles(string $siteId,string $actorId): array
    {
        (new SiteAuthorization())->assert($siteId,$actorId,'cms:access:list');
        $rows=Input::rows(Db::table('cms_site_role')->where('site_id',$siteId)->whereNull('deleted_at')->orderBy('id')->get());
        foreach($rows as &$r)$r['permissions']=$this->permissions($siteId,$r['id']);return $rows;
    }
    public function members(string $siteId,string $actorId): array
    {
        (new SiteAuthorization())->assert($siteId,$actorId,'cms:access:list');
        $rows=Input::rows(Db::table('cms_site_admin as m')->join('admin as a','a.id','=','m.admin_id')->where('m.site_id',$siteId)->whereNull('a.deleted_at')->select(['a.id','a.username','a.real_name','a.status'])->orderBy('a.id')->get());
        foreach($rows as &$row){$row['role_ids']=array_map('strval',Db::table('cms_site_role_member')->where('site_id',$siteId)->where('admin_id',$row['id'])->pluck('role_id')->all());$row['manageable']=$this->manageable($actorId,$row['id']);$row['effective']=(new SiteAuthorization())->effective($siteId,$row['id']);$row['host_permissions']=(new SiteAuthorization())->hostCodes($row['id']);}return $rows;
    }
    public function candidates(string $siteId,string $actorId): array
    {
        (new SiteAuthorization())->assert($siteId,$actorId,'cms:access:members');$rows=[];
        foreach(Admin::where('status',1)->whereNull('deleted_at')->orderBy('id')->get() as $row)if($this->manageable($actorId,(string)$row->id))$rows[]=['id'=>(string)$row->id,'username'=>$row->username,'real_name'=>$row->real_name,'host_permissions'=>(new SiteAuthorization())->hostCodes((string)$row->id)];return $rows;
    }
    public function saveRole(string $siteId,string $actorId,array $input): array
    {
        return Db::transaction(function()use($siteId,$actorId,$input): array {
            (new SiteService())->lock($siteId);$auth=new SiteAuthorization();$auth->assert($siteId,$actorId,'cms:access:roles');
            $id=empty($input['id'])?null:Input::id($input['id']);$old=$id?$this->role($siteId,$id):null;
            $permissions=$input['permissions']??[];
            if(!is_array($permissions)||count($permissions)>100||array_filter($permissions,fn($p)=>!is_string($p)||!in_array($p,PermissionCatalog::operations(),true)))throw new \InvalidArgumentException('权限目录包含未知 code',422);
            $permissions=array_values(array_unique($permissions));
            if(array_diff($permissions,PermissionCatalog::registered()))throw new \InvalidArgumentException('权限未注册、停用或归属无效',422);
            if(!(new SiteAccess())->isSuper($actorId)){
                $own=$auth->effective($siteId,$actorId);$previous=$id?$this->permissions($siteId,$id):[];
                if(array_diff(array_merge($previous,$permissions),$own))throw new \InvalidArgumentException('不能授予超出自己本站权限的能力',403);
                if($id)foreach(Db::table('cms_site_role_member')->where('site_id',$siteId)->where('role_id',$id)->pluck('admin_id') as $member)$this->assertManageable($actorId,(string)$member);
            }
            $code=Input::code($input['code']??$old['code']??'');if($old&&$old['code']!==$code)throw new \InvalidArgumentException('角色标识不可修改',422);
            $name=Input::text($input['name']??'',100);if($name==='')throw new \InvalidArgumentException('角色名称不能为空',422);
            $data=['code'=>$code,'name'=>$name,'status'=>$this->status($input['status']??$old['status']??1),'updated_at'=>Input::now()];
            if($old)Db::table('cms_site_role')->where('id',$id)->where('site_id',$siteId)->update($data);else $id=(string)Db::table('cms_site_role')->insertGetId($data+['site_id'=>$siteId,'created_at'=>Input::now()]);
            $before=$old?($old+['permissions'=>$this->permissions($siteId,$id)]):[];
            Db::table('cms_site_role_permission')->where('site_id',$siteId)->where('role_id',$id)->delete();
            foreach($permissions as $p)Db::table('cms_site_role_permission')->insert(['site_id'=>$siteId,'role_id'=>$id,'permission_code'=>$p]);
            $after=$this->role($siteId,$id)+['permissions'=>$permissions];$this->audit($siteId,$actorId,'role',$id,$old?'role.update':'role.create',$before,$after);return $after;
        });
    }
    public function assign(string $siteId,string $actorId,string $adminId,array $roleIds): void
    {
        Db::transaction(function()use($siteId,$actorId,$adminId,$roleIds): void {
            (new SiteService())->lock($siteId);$auth=new SiteAuthorization();$auth->assert($siteId,$actorId,'cms:access:members');$this->assertManageable($actorId,$adminId);
            $ids=array_values(array_unique(array_map(Input::id(...),$roleIds)));$own=$auth->effective($siteId,$actorId);
            foreach($ids as $id){$role=$this->role($siteId,$id);if((int)$role['status']!==1)throw new \InvalidArgumentException('不能分配停用的角色',422);if(!(new SiteAccess())->isSuper($actorId)&&array_diff($this->permissions($siteId,$id),$own))throw new \InvalidArgumentException('角色权限超出您的本站权限',403);}
            $before=array_map('strval',Db::table('cms_site_role_member')->where('site_id',$siteId)->where('admin_id',$adminId)->pluck('role_id')->all());
            // Replacing a stronger pre-existing grant is also a privileged operation.
            if(!(new SiteAccess())->isSuper($actorId))foreach($before as $id)if(array_diff($this->permissions($siteId,$id),$own))throw new \InvalidArgumentException('不能修改超出自己权限的成员角色',403);
            Db::table('cms_site_admin')->insertOrIgnore(['site_id'=>$siteId,'admin_id'=>$adminId]);Db::table('cms_site_role_member')->where('site_id',$siteId)->where('admin_id',$adminId)->delete();
            foreach($ids as $id)Db::table('cms_site_role_member')->insert(['site_id'=>$siteId,'role_id'=>$id,'admin_id'=>$adminId]);
            $this->audit($siteId,$actorId,'member',$adminId,'member.assign',$before,$ids);
        });
    }
    public function remove(string $siteId,string $actorId,string $adminId): void
    {
        Db::transaction(function()use($siteId,$actorId,$adminId): void {
            (new SiteService())->lock($siteId);(new SiteAuthorization())->assert($siteId,$actorId,'cms:access:members');$this->assertManageable($actorId,$adminId);
            $before=array_map('strval',Db::table('cms_site_role_member')->where('site_id',$siteId)->where('admin_id',$adminId)->pluck('role_id')->all());
            if(!(new SiteAccess())->isSuper($actorId))foreach($before as $id)if(array_diff($this->permissions($siteId,$id),(new SiteAuthorization())->effective($siteId,$actorId)))throw new \InvalidArgumentException('不能撤销超出自己权限的成员角色',403);
            Db::table('cms_site_role_member')->where('site_id',$siteId)->where('admin_id',$adminId)->delete();Db::table('cms_site_admin')->where('site_id',$siteId)->where('admin_id',$adminId)->delete();$this->audit($siteId,$actorId,'member',$adminId,'member.remove',$before,[]);
        });
    }
    private function permissions(string $siteId,string $roleId): array { return Db::table('cms_site_role_permission')->where('site_id',$siteId)->where('role_id',$roleId)->pluck('permission_code')->all(); }
    private function role(string $siteId,string $id): array { return Input::row(Db::table('cms_site_role')->where('site_id',$siteId)->where('id',$id)->whereNull('deleted_at')->first())??throw new \InvalidArgumentException('角色不属于本站',422); }
    private function manageable(string $actorId,string $targetId): bool
    {
        $target=Admin::where('id',Input::id($targetId))->whereNull('deleted_at')->first();if(!$target)return false;
        if((new SiteAccess())->isSuper($actorId))return true;
        if($actorId===$targetId||(int)$targetId===(int)config('auth.super_admin_id',1))return false;
        // Host scope is request-bound. A service caller must never spoof that identity.
        if((string)(request()?->context['admin']['id']??'')!==$actorId)return false;
        return (new RoleScopeService())->canManageAdmin($target->toArray());
    }
    private function assertManageable(string $actorId,string $targetId): void { if(!$this->manageable($actorId,$targetId))throw new \InvalidArgumentException('不能管理本人或宿主范围之外的账号',403); }
    private function status(mixed $status): int { if(!in_array($status,[0,1,'0','1',true,false],true))throw new \InvalidArgumentException('状态无效',422);return (int)$status; }
    private function audit(string $site,string $actor,string $type,string $target,string $op,array $before,array $after): void { Db::table('cms_site_auth_audit')->insert(['site_id'=>$site,'actor_admin_id'=>$actor,'target_type'=>$type,'target_id'=>$target,'operation'=>$op,'before_state'=>Input::json($before),'after_state'=>Input::json($after),'created_at'=>Input::now()]); }
}
