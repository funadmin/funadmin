<?php
declare(strict_types=1);
namespace app\cms\service;
use app\admin\authentication\model\Admin;
use app\admin\authorization\service\RoleScopeService;
use app\cms\model\SiteAdmin;
use app\cms\model\SiteAuthAudit;
use app\cms\model\SiteRole;
use app\cms\model\SiteRoleMember;
use app\cms\model\SiteRolePermission;
use support\Db;

final class SiteRoleService
{
    public function roles(string $siteId,string $actorId): array
    {
        (new SiteAuthorization())->assert($siteId,$actorId,'cms:access:list');
        $rows=Input::rows(SiteRole::query()->forSite($siteId)->orderBy('id')->get());
        foreach($rows as &$r)$r['permissions']=$this->permissions($siteId,$r['id']);return $rows;
    }
    public function members(string $siteId,string $actorId): array
    {
        (new SiteAuthorization())->assert($siteId,$actorId,'cms:access:list');
        $rows=Input::rows(Db::table('cms_site_admin as m')->join('admin as a','a.id','=','m.admin_id')->where('m.site_id',$siteId)->whereNull('m.deleted_at')->whereNull('a.deleted_at')->select(['a.id','a.username','a.real_name','a.status'])->orderBy('a.id')->get());
        foreach($rows as &$row){$row['role_ids']=array_map('strval',SiteRoleMember::query()->forSite($siteId)->where('admin_id',$row['id'])->pluck('role_id')->all());$row['manageable']=$this->manageable($actorId,$row['id']);$row['effective']=(new SiteAuthorization())->effective($siteId,$row['id']);$row['host_permissions']=(new SiteAuthorization())->hostCodes($row['id']);}return $rows;
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
                if($id)foreach(SiteRoleMember::query()->forSite($siteId)->where('role_id',$id)->pluck('admin_id') as $member)$this->assertManageable($actorId,(string)$member);
            }
            $code=Input::code($input['code']??$old['code']??'');if($old&&$old['code']!==$code)throw new \InvalidArgumentException('角色标识不可修改',422);
            $name=Input::text($input['name']??'',100);if($name==='')throw new \InvalidArgumentException('角色名称不能为空',422);
            $data=['code'=>$code,'name'=>$name,'status'=>$this->status($input['status']??$old['status']??1),'updated_at'=>Input::now()];
            if($old)SiteRole::query()->where('id',$id)->where('site_id',$siteId)->update($data);else{$row=new SiteRole();$row->fill($data+['site_id'=>$siteId,'created_at'=>Input::now()])->save();$id=(string)$row->getKey();}
            $before=$old?($old+['permissions'=>$this->permissions($siteId,$id)]):[];
            // 站点行已加锁（所属锁）：权限按差异同步，原样保存不产生软删除行。
            RelationSync::sync(SiteRolePermission::class,['site_id'=>$siteId,'role_id'=>$id],'permission_code',$permissions);
            $after=$this->role($siteId,$id)+['permissions'=>$permissions];$this->audit($siteId,$actorId,'role',$id,$old?'role.update':'role.create',$before,$after);return $after;
        });
    }
    public function assign(string $siteId,string $actorId,string $adminId,array $roleIds): void
    {
        Db::transaction(function()use($siteId,$actorId,$adminId,$roleIds): void {
            (new SiteService())->lock($siteId);$auth=new SiteAuthorization();$auth->assert($siteId,$actorId,'cms:access:members');$this->assertManageable($actorId,$adminId);
            $ids=array_values(array_unique(array_map(Input::id(...),$roleIds)));$own=$auth->effective($siteId,$actorId);
            foreach($ids as $id){$role=$this->role($siteId,$id);if((int)$role['status']!==1)throw new \InvalidArgumentException('不能分配停用的角色',422);if(!(new SiteAccess())->isSuper($actorId)&&array_diff($this->permissions($siteId,$id),$own))throw new \InvalidArgumentException('角色权限超出您的本站权限',403);}
            $before=array_map('strval',SiteRoleMember::query()->forSite($siteId)->where('admin_id',$adminId)->pluck('role_id')->all());
            // Replacing a stronger pre-existing grant is also a privileged operation.
            if(!(new SiteAccess())->isSuper($actorId))foreach($before as $id)if(array_diff($this->permissions($siteId,$id),$own))throw new \InvalidArgumentException('不能修改超出自己权限的成员角色',403);
            SiteAdmin::query()->insertOrIgnore(['site_id'=>$siteId,'admin_id'=>$adminId]);
            RelationSync::sync(SiteRoleMember::class,['site_id'=>$siteId,'admin_id'=>$adminId],'role_id',$ids);
            $this->audit($siteId,$actorId,'member',$adminId,'member.assign',$before,$ids);
        });
    }
    public function remove(string $siteId,string $actorId,string $adminId): void
    {
        Db::transaction(function()use($siteId,$actorId,$adminId): void {
            (new SiteService())->lock($siteId);(new SiteAuthorization())->assert($siteId,$actorId,'cms:access:members');$this->assertManageable($actorId,$adminId);
            $before=array_map('strval',SiteRoleMember::query()->forSite($siteId)->where('admin_id',$adminId)->pluck('role_id')->all());
            if(!(new SiteAccess())->isSuper($actorId))foreach($before as $id)if(array_diff($this->permissions($siteId,$id),(new SiteAuthorization())->effective($siteId,$actorId)))throw new \InvalidArgumentException('不能撤销超出自己权限的成员角色',403);
            // 成员身份与它的角色共用一个删除时间：回收站恢复时据此带回一起移除的角色。
            $at=RecycleBin::now();RecycleBin::trash(SiteRoleMember::query()->forSite($siteId)->where('admin_id',$adminId),$at);RecycleBin::trash(SiteAdmin::query()->forSite($siteId)->where('admin_id',$adminId),$at);
            $this->audit($siteId,$actorId,'member',$adminId,'member.remove',$before,[]);
        });
    }
    /** 回收站：本站被移除的成员身份（差异同步移除的单个角色不算成员删除，不在这里）。roles 为恢复时会带回的角色。 */
    public function trashedMembers(string $siteId,string $actorId): array
    {
        $siteId=Input::id($siteId);$rows=[];
        foreach(SiteAdmin::onlyTrashed()->forSite($siteId)->orderByDesc('deleted_at')->orderByDesc('id')->limit(200)->get() as $member){
            $at=RecycleBin::deletedAt($member);$adminId=(string)$member->admin_id;$admin=Admin::where('id',$adminId)->first();
            $roleIds=array_map('strval',SiteRoleMember::onlyTrashed()->forSite($siteId)->where('admin_id',$adminId)->where('deleted_at',$at)->pluck('role_id')->all());
            $roles=$roleIds?array_map(static fn(array $r): array=>['id'=>$r['id'],'name'=>$r['name'],'status'=>(int)$r['status']],Input::rows(SiteRole::query()->forSite($siteId)->whereIn('id',$roleIds)->orderBy('id')->get())):[];
            $rows[]=['id'=>(string)$member->getRawOriginal('id'),'admin_id'=>$adminId,'username'=>$admin?->username??'','real_name'=>$admin?->real_name??'','deleted_at'=>$at,'roles'=>$roles,'manageable'=>$this->manageable($actorId,$adminId)];
        }
        return $rows;
    }
    /**
     * 恢复成员身份，并带回与它一起移除的角色。校验与分配角色相同：账号必须可管理；角色已删除或停用时跳过（计入 skipped）；
     * 非平台管理员不能恢复超出自己本站权限的角色（403）。该账号已重新加入本站时整批 409。
     * @return array{count:int,skipped:int}
     */
    public function restoreMembers(string $siteId,string $actorId,mixed $ids): array
    {
        $siteId=Input::id($siteId);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($siteId,$actorId,$ids): array {
            (new SiteService())->lock($siteId);$auth=new SiteAuthorization();$auth->assert($siteId,$actorId,'cms:access:restore');
            $members=RecycleBin::locked(SiteAdmin::class,$siteId,$ids,'成员不在回收站或不属于本站');$skipped=0;$super=(new SiteAccess())->isSuper($actorId);$own=$super?[]:$auth->effective($siteId,$actorId);
            foreach($members as $member){
                $adminId=(string)$member->admin_id;$this->assertManageable($actorId,$adminId);$at=RecycleBin::deletedAt($member);
                $restore=[];
                foreach(SiteRoleMember::onlyTrashed()->forSite($siteId)->where('admin_id',$adminId)->where('deleted_at',$at)->get() as $row){
                    $role=Input::row(SiteRole::query()->forSite($siteId)->where('id',$row->role_id)->first());
                    if(!$role||(int)$role['status']!==1){$skipped++;continue;}
                    if(!$super&&array_diff($this->permissions($siteId,$role['id']),$own))throw new \InvalidArgumentException('角色权限超出您的本站权限',403);
                    $restore[]=$row->getKey();
                }
                $member->restore();if($restore)SiteRoleMember::onlyTrashed()->whereKey($restore)->restore();
                $this->audit($siteId,$actorId,'member',$adminId,'member.restore',[],array_map('strval',SiteRoleMember::query()->forSite($siteId)->where('admin_id',$adminId)->pluck('role_id')->all()));
            }
            return ['count'=>count($members),'skipped'=>$skipped];
        });
    }
    /**
     * 彻底删除回收站中的成员身份及与它一起移除的角色记录，不可恢复；账号必须可管理。
     * @return array{count:int}
     */
    public function destroyMembers(string $siteId,string $actorId,mixed $ids): array
    {
        $siteId=Input::id($siteId);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($siteId,$actorId,$ids): array {
            (new SiteService())->lock($siteId);(new SiteAuthorization())->assert($siteId,$actorId,'cms:access:destroy');
            $members=RecycleBin::locked(SiteAdmin::class,$siteId,$ids,'成员不在回收站或不属于本站');
            foreach($members as $member){
                $adminId=(string)$member->admin_id;$this->assertManageable($actorId,$adminId);
                SiteRoleMember::onlyTrashed()->forSite($siteId)->where('admin_id',$adminId)->where('deleted_at',RecycleBin::deletedAt($member))->forceDelete();$member->forceDelete();
                $this->audit($siteId,$actorId,'member',$adminId,'member.destroy',[],[]);
            }
            return ['count'=>count($members)];
        });
    }
    private function permissions(string $siteId,string $roleId): array { return SiteRolePermission::query()->forSite($siteId)->where('role_id',$roleId)->pluck('permission_code')->all(); }
    private function role(string $siteId,string $id): array { return Input::row(SiteRole::query()->forSite($siteId)->where('id',$id)->first())??throw new \InvalidArgumentException('角色不属于本站',422); }
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
    private function audit(string $site,string $actor,string $type,string $target,string $op,array $before,array $after): void { SiteAuthAudit::query()->insert(['site_id'=>$site,'actor_admin_id'=>$actor,'target_type'=>$type,'target_id'=>$target,'operation'=>$op,'before_state'=>Input::json($before),'after_state'=>Input::json($after),'created_at'=>Input::now()]); }
}
