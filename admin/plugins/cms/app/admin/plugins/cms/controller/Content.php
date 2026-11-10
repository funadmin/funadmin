<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\{Response,Db};
use app\cms\service\Input;
use app\cms\service\ContentService;
use app\cms\service\SiteService;
use app\cms\service\Renderer;
use app\cms\model\Content as ContentEntity;
use app\cms\model\ContentLocale;
use app\cms\model\ContentRevision;
use app\cms\model\Domain;
use app\cms\model\SiteModel;
use app\common\model\Attach;
#[RouteGroup('/admin/plugins/cms/content')]
final class Content extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new ContentService())->listing($this->site('cms:content:list'),$this->locale(),$this->input(),false)); }
    #[Get('/lookups')]
    public function lookups(): Response { return $this->action(function(): array {
        $site=$this->site('cms:content:list');$locale=$this->locale();
        $enabled=array_map('strval',SiteModel::query()->where('site_id',$site)->where('status',1)->pluck('model_id')->all());
        $models=array_values(array_filter((new \app\cms\service\ModelService())->listing(),static fn($model)=>in_array($model['id'],$enabled,true)&&(int)$model['status']===1));
        return ['models'=>$models,'categories'=>(new \app\cms\service\CategoryService())->listing($site,$locale,false),'templates'=>(new \app\cms\service\TemplateCatalog())->details($site),'forms'=>(new \app\cms\service\FormService())->options($site)];
    }); }
    #[Get('/translation')]
    public function translation(): Response { return $this->action(function(): array {
        $site=$this->site('cms:content:list');$locale=$this->locale();(new SiteService())->language($site,$locale);
        $content=Input::row(ContentEntity::query()->where('site_id',$site)->where('id',Input::id($this->input()['content_id']??''))->first())??throw new \InvalidArgumentException('内容不存在',404);
        $id=ContentLocale::query()->where('site_id',$site)->where('content_id',$content['id'])->where('locale',$locale)->value('id');
        return $id?(new ContentService())->editor($site,(string)$id):['content_id'=>$content['id'],'model_id'=>$content['model_id'],'category_id'=>$content['category_id'],'revision'=>null];
    }); }
    #[Get('/attachments')]
    public function attachments(): Response { return $this->action(function(): array {
        $site=$this->siteAny(['cms:content:create','cms:content:edit']);if(!Db::schema()->hasTable('attach'))return [];
        $query=Attach::query()->withoutGlobalScopes()->where('status',1)->whereNull('deleted_at');
        $ids=$this->input()['ids']??[];if(!is_array($ids)||count($ids)>100)throw new \InvalidArgumentException('附件列表无效');
        $ids=array_map(Input::id(...),$ids);$columns=['id','original_name','path','url','ext'];
        $selected=$ids?Input::rows((clone $query)->whereIn('id',$ids)->select($columns)->get()):[];
        if(!empty($this->input()['q']))$query->where('original_name','like','%'.Input::text($this->input()['q'],100).'%');
        $files=Input::rows($query->select($columns)->orderByDesc('id')->limit(100)->get());
        $result=[];foreach([...$selected,...$files] as $file)$result[$file['id']]=$file;return array_values($result);
    }); }
    #[Post('/upload')]
    public function upload(): Response { return $this->action(function(): array {
        $this->siteAny(['cms:content:create','cms:content:edit']);
        $response=(new \app\admin\system\controller\AdminUpload())->upload();
        $result=json_decode($response->rawBody(),true,512,JSON_THROW_ON_ERROR);
        if(($result['code']??500)!==200)throw new \InvalidArgumentException($result['msg']??'上传失败',(int)($result['code']??500));
        return $result['data'];
    }); }
    #[Get('/detail')]
    public function detail(): Response { return $this->action(fn()=>(new ContentService())->editor($this->site('cms:content:list'),Input::id($this->input()['id']??''))); }
    #[Get('/revisions')]
    public function revisions(): Response { return $this->action(fn()=>(new ContentService())->revisions($this->site('cms:content:list'),Input::id($this->input()['id']??''))); }
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>(new ContentService())->save($this->site(empty($this->input()['content_id'])?'cms:content:create':'cms:content:edit'),$this->locale(),$this->input(),$this->actor())); }
    #[Post('/publish')]
    public function publish(): Response { return $this->action(fn()=>(new ContentService())->publish($this->site('cms:content:publish'),Input::id($this->input()['id']??''),Input::id($this->input()['revision_id']??''),(int)($this->input()['lock_version']??-1))); }
    #[Post('/offline')]
    public function offline(): Response { return $this->action(function(): bool {(new ContentService())->offline($this->site('cms:content:offline'),Input::id($this->input()['id']??''),(int)($this->input()['lock_version']??-1));return true;}); }
    #[Get('/preview')]
    public function preview(): Response { return $this->action(function(): array {
        $siteId=$this->site('cms:content:preview');$editor=(new ContentService())->editor($siteId,Input::id($this->input()['id']??''));$revisionId=Input::id($this->input()['revision_id']??$editor['draft_revision_id']);
        $revision=Input::row(ContentRevision::query()->where('site_id',$siteId)->where('content_locale_id',$editor['id'])->where('id',$revisionId)->first())??throw new \InvalidArgumentException('修订不存在');
        $sites=new SiteService();$site=$sites->get($siteId);$primary=Domain::query()->where('site_id',$siteId)->where('id',$site['primary_domain_id'])->first();if(!$primary)throw new \InvalidArgumentException('请配置主域名');$site['primary_host']=$primary->host;
        $language=$sites->language($siteId,$editor['locale']);$page=['target_type'=>'preview','path'=>$revision['desired_path'],'title'=>$revision['title'],'robots'=>'noindex,nofollow','page'=>1];
        return ['html'=>(new Renderer())->render($site,$language,$page,$revision['template'],$revision+['content_id'=>$editor['content_id'],'published_at'=>'预览'])];
    }); }
}
