<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use support\Db;
use app\cms\service\Input;
use app\cms\service\SiteService;
use app\cms\service\SiteAccess;
use app\cms\service\SiteAuthorization;
use app\cms\service\PermissionCatalog;
use app\cms\model\ContentModel;
use app\cms\model\SiteLocale;
use app\cms\model\SiteModel;
#[RouteGroup('/admin/plugins/cms')]
final class Sites extends Base
{
    /** 站点保存以 id 指定站点，其余接口仍用 site_id。 */
    protected function invalidatePublicPages(): void
    {
        parent::invalidatePublicPages();$id=(string)($this->input()['id']??'');
        if(($this->input()['site_id']??'')===''&&$id!=='')try{(new \app\cms\service\PublicPageCache())->invalidateSite($id);}catch(\Throwable){}
    }
    #[Get('/context')]
    public function context(): Response { return $this->action(function():array {
        $menu=(string)($this->input()['menu']??'');if($menu!==''&&!in_array($menu,PermissionCatalog::menus(),true))throw new \InvalidArgumentException('未知菜单',422);
        $auth=new SiteAuthorization();$items=[];
        foreach((new SiteService())->listing($this->actor()) as $site){$effective=$auth->effective($site['id'],$this->actor());if(!$effective||$menu!==''&&!in_array($menu,$effective,true))continue;
            $locales=Input::rows(SiteLocale::query()->where('site_id',$site['id'])->where('status',1)->select(['id','site_id','locale','name','url_prefix','status'])->orderBy('id')->get());
            $items[]=['id'=>$site['id'],'name'=>$site['name'],'default_locale'=>$site['default_locale'],'locales'=>$locales,'permissions'=>$effective];
        }
        return ['items'=>$items,'is_super'=>(new SiteAccess())->isSuper($this->actor())];
    }); }
    #[Get('/sites')]
    public function index(): Response { return $this->context(); }
    /** 新建站点需平台管理员；编辑站点沿用本站编辑权限读取可用主题。 */
    #[Get('/sites/options')]
    public function options(): Response { return $this->action(function(): array {
        if(empty($this->input()['site_id']))(new SiteAccess())->super($this->actor());else $this->site('cms:site:edit');
        return ['themes'=>(new \app\cms\service\TemplateCatalog())->themes()];
    }); }
    #[Get('/settings')]
    public function settings(): Response { return $this->action(function():array {$id=$this->site('cms:site:detail');$s=new SiteService();return ['site'=>$s->get($id),'locales'=>$s->locales($id),'domains'=>$s->domains($id),'models'=>Input::rows(SiteModel::query()->where('site_id',$id)->get())];}); }
    #[Get('/configuration')]
    public function configuration(): Response { return $this->action(fn()=>(new \app\cms\service\SiteConfiguration())->read($this->site('cms:site:detail'),empty($this->input()['locale'])?'':Input::locale($this->input()['locale']))); }
    #[Post('/configuration/save')]
    public function configurationSave(): Response { return $this->action(fn()=>(new \app\cms\service\SiteConfiguration())->save(Input::id($this->input()['site_id']??''),$this->actor(),$this->input())); }
    #[Post('/sites/save')]
    public function save(): Response { return $this->action(fn()=>Db::transaction(function():array {
        $input=$this->input();if(!empty($input['id'])){
            $id=Input::id($input['id']);(new SiteService())->lock($id);$auth=new SiteAuthorization();$auth->assert($id,$this->actor(),'cms:site:edit');$old=(new SiteService())->get($id);
            if(!empty($input['host'])||isset($input['primary_domain_id'])&&(string)$input['primary_domain_id']!==(string)$old['primary_domain_id'])$auth->assert($id,$this->actor(),'cms:site:domain');
            if(isset($input['default_locale'])&&$input['default_locale']!==$old['default_locale'])$auth->assert($id,$this->actor(),'cms:site:locale');
        }return (new SiteService())->save($input,$this->actor());
    })); }
    #[Post('/locales/save')]
    public function localeSave(): Response { return $this->action(fn()=>(new SiteService())->saveLocale($this->site('cms:site:locale'),$this->input())); }
    #[Post('/domains/save')]
    public function domainSave(): Response { return $this->action(fn()=>(new SiteService())->saveDomain($this->site('cms:site:domain'),$this->input())); }
    #[Post('/site-models/save')]
    public function modelSave(): Response { return $this->action(fn()=>Db::transaction(function():bool {
        $site=Input::id($this->input()['site_id']??'');(new SiteService())->lock($site);$this->siteAny(['cms:model:status','cms:model:template']);$input=$this->input();$id=Input::id($input['model_id']??'');
        if(!ContentModel::query()->where('id',$id)->exists())throw new \InvalidArgumentException('模型不存在',404);
        $old=SiteModel::query()->where('site_id',$site)->where('model_id',$id)->first();$auth=new SiteAuthorization();
        $status=(int)($input['status']??$old->status??0);$template=Input::template($input['default_template']??$old->default_template??'detail');
        if($status!==(int)($old->status??0))$auth->assert($site,$this->actor(),'cms:model:status');
        if($template!==($old->default_template??'detail'))$auth->assert($site,$this->actor(),'cms:model:template');
        // 只更新未删除的绑定；没有时新增一行（已删除的绑定保持删除，不被复活）。
        if($old)SiteModel::query()->whereKey($old->getKey())->update(['status'=>$status,'default_template'=>$template]);else SiteModel::query()->insert(['site_id'=>$site,'model_id'=>$id,'status'=>$status,'default_template'=>$template]);return true;
    })); }
}
