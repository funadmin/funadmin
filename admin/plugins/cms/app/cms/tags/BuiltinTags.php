<?php
declare(strict_types=1);
namespace app\cms\tags;
use app\cms\template\TagRegistry;
use app\cms\template\RenderContext;
use app\cms\template\TrustedHtml;
use app\cms\service\ContentService;
use app\cms\service\CategoryService;
use app\cms\service\MenuService;
use app\cms\service\SiteService;
use app\cms\service\RouteService;
use app\cms\service\Input;
use app\cms\model\CategoryLocale;
use app\cms\model\ContentLocale;
final class BuiltinTags
{
    private ?array $pagination=null;
    private bool $paginationClaimed=false;
    private bool $formScript=false;
    public function registry(RenderContext $context): TagRegistry
    {
        $registry=new TagRegistry();
        $registry->onRender(function():void{$this->pagination=null;$this->paginationClaimed=false;$this->formScript=false;});
        $registry->register('config',new CallbackTag(['name'=>['type'=>'string','required'=>true],'limit'=>['type'=>'integer','default'=>100,'min'=>1,'max'=>100],'offset'=>['type'=>'integer','default'=>0,'min'=>0,'max'=>10000]],static function($p,RenderContext $c){
            $value=$c->page['config'][$p['name']]??(new \app\cms\service\SiteConfiguration())->resolve($c->siteId,$c->locale)[$p['name']]??'';
            if(is_array($value)&&array_is_list($value))return array_slice(array_values(array_filter($value,static fn($row)=>($row['enabled']??true)===true)),$p['offset'],$p['limit']);
            return $value;
        },false));
        $registry->register('theme',new CallbackTag(['name'=>['type'=>'string','required'=>true,'enum'=>['products','cases','articles','items','related','categories','pagination','slides','partners','contacts','ads']]],static fn($p,RenderContext $c):array=>$c->page['theme_lists'][$p['name']]??[],false));
        $registry->register('text',new CallbackTag([
            'value'=>['type'=>'text','default'=>''],'default'=>['type'=>'string','default'=>''],
            'strip_html'=>['type'=>'boolean','default'=>false],'length'=>['type'=>'integer','default'=>0,'min'=>0,'max'=>10000],
            'suffix'=>['type'=>'string','default'=>'…'],
        ],\app\cms\template\TemplateFormatting::text(...)));
        $registry->register('date',new CallbackTag(['value'=>['type'=>'string','default'=>''],'format'=>['type'=>'string','default'=>'Y-m-d'],'default'=>['type'=>'string','default'=>'']],\app\cms\template\TemplateFormatting::date(...)));
        $registry->register('content',new CallbackTag(['model'=>['type'=>'string','default'=>''],'category'=>['type'=>'string','default'=>''],'limit'=>['type'=>'integer','default'=>12,'min'=>1,'max'=>100],'paginate'=>['type'=>'boolean','default'=>false],'q'=>['type'=>'string','default'=>''],'order'=>['type'=>'string','default'=>'published_desc','enum'=>ContentService::ORDERS],'offset'=>['type'=>'integer','default'=>0,'min'=>0,'max'=>10000]],function($p,RenderContext $c): array {
            if($p['paginate']){
                if($p['offset']>0)throw new \InvalidArgumentException('分页列表不能设置 offset');
                if($this->paginationClaimed)throw new \InvalidArgumentException('同一页面只能有一个分页主列表');
                $this->paginationClaimed=true;
            }
            $filters=['model'=>$p['model'],'limit'=>$p['limit'],'page'=>$p['paginate']?($c->page['page']??1):1,'q'=>$p['q'],'order'=>$p['order'],'offset'=>$p['offset']];
            if($p['category']!==''){
                if(ctype_digit($p['category']))$filters['category_id']=$p['category'];
                else{$route=(new RouteService())->resolve($c->siteId,'/'.trim($p['category'],'/').'/');if(!$route||$route['target_type']!=='category'||$route['locale']!==$c->locale)return [];$category=(new CategoryService())->detail($c->siteId,$route['target_id']);if(!$category)return [];$filters['category_id']=$category['id'];}
            }elseif($p['paginate']&&!empty($c->page['category_id']))$filters['category_id']=$c->page['category_id'];
            $result=(new ContentService())->listing($c->siteId,$c->locale,$filters);
            if($p['paginate']){if($result['page']>1&&!$result['items'])throw new \InvalidArgumentException('页面不存在',404);$this->pagination=$result;}
            return $result['items'];
        }));
        $registry->register('category',new CallbackTag(['parent'=>['type'=>'string','default'=>'0']],static fn($p,RenderContext $c)=>(new CategoryService())->listing($c->siteId,$c->locale,true,$p['parent']===''?null:$p['parent'])));
        $registry->register('menu',new CallbackTag(['code'=>['type'=>'string','default'=>'header'],'parent'=>['type'=>'string','default'=>'0']],static fn($p,RenderContext $c)=>(new MenuService())->items($c->siteId,$c->locale,$p['code'],$p['parent'])));
        $registry->register('navigation',new CallbackTag(['code'=>['type'=>'string','default'=>'header'],'template'=>['type'=>'string','default'=>'']],static function($p,RenderContext $c):TrustedHtml {
            if($p['template']!=='')return (new \app\cms\service\NavigationView())->render((string)($c->page['theme']??'default'),$p['template'],(new MenuService())->items($c->siteId,$c->locale,$p['code']),$c);
            $link=static fn(array $item,string $title):string=>'<a href="'.\app\cms\service\Seo::escape($item['url']).'"'.($item['open_in_new_tab']?' target="_blank" rel="noopener noreferrer"':'').'>'.\app\cms\service\Seo::escape($title).'</a>';
            $allPrefix=str_starts_with($c->locale,'zh')?'查看全部':'View all ';
            $render=function(array $items,?string $overview=null)use(&$render,$link,$allPrefix):string {
                $html='<ul class="cms-menu">'.($overview===null?'':'<li class="cms-menu-overview">'.$overview.'</li>');
                foreach($items as $item){
                    // 标题负责展开；父栏目入口放进下拉列表，避免链接吞掉原生开关点击。
                    $html.='<li>'.($item['children']
                        ?'<details class="cms-menu-dropdown"><summary>'.\app\cms\service\Seo::escape($item['title']).'</summary>'.$render($item['children'],$link($item,$allPrefix.$item['title'])).'</details>'
                        :$link($item,$item['title'])).'</li>';
                }
                return $html.'</ul>';
            };
            return new TrustedHtml($render((new MenuService())->items($c->siteId,$c->locale,$p['code'])));
        },false));
        $registry->register('fields',new CallbackTag([],static fn($p,RenderContext $c)=>$c->page['fields']??[],false));
        $registry->register('site',new CallbackTag([],static fn($p,RenderContext $c)=>(new SiteService())->get($c->siteId),false));
        $registry->register('languages',new CallbackTag([],static function($p,RenderContext $c): array {
            $items=[];foreach((new SiteService())->locales($c->siteId,true) as $language){
                $type=$c->page['target_type']??'home';$target=null;
                if($type==='content'&&!empty($c->page['content_id']))$target=ContentLocale::query()->where('site_id',$c->siteId)->where('content_id',$c->page['content_id'])->where('locale',$language['locale'])->where('status','published')->value('id');
                elseif($type==='category'&&!empty($c->page['category_id']))$target=CategoryLocale::query()->where('site_id',$c->siteId)->where('category_id',$c->page['category_id'])->where('locale',$language['locale'])->where('status',1)->value('id');
                elseif($type==='home'||$type==='search')$target=$language['id'];
                if(!$target)continue;if($type==='content'){$detail=(new ContentService())->publicDetail($c->siteId,(string)$target);if(!$detail||str_contains($detail['robots'],'noindex'))continue;}
                if($type==='category'&&!(new CategoryService())->detail($c->siteId,(string)$target))continue;
                $route=(new RouteService())->canonical($c->siteId,$type==='search'?'home':$type,(string)$target);if($route){$url=RouteService::encoded($route['path']);if($type==='search')$url=rtrim($url,'/').'/search/?'.http_build_query(['q'=>$c->page['query']??''],'','&',PHP_QUERY_RFC3986);$items[]=['locale'=>$language['locale'],'name'=>$language['name'],'url'=>$url];}
            }return $items;
        },false));
        $registry->register('breadcrumb',new CallbackTag([],static function($p,RenderContext $c): array {
            $items=[];$id=$c->page['category_id']??null;$seen=[];
            while($id!==null&&!in_array($id,$seen,true)&&count($seen)<32){$seen[]=$id;$translation=CategoryLocale::query()->where('site_id',$c->siteId)->where('category_id',$id)->where('locale',$c->locale)->where('status',1)->first();if(!$translation)break;$row=(new CategoryService())->detail($c->siteId,(string)$translation->id);if(!$row)break;array_unshift($items,$row);$id=$row['parent_id'];}return $items;
        },false));
        $registry->register('pagination',new CallbackTag([],function($p,RenderContext $c): TrustedHtml {
            if(!$this->pagination)return new TrustedHtml('');$data=$this->pagination;$pages=(int)ceil($data['total']/$data['limit']);if($pages<=1)return new TrustedHtml('');$html='<nav aria-label="分页" class="pagination">';$basePath=$c->page['base_path']??$c->page['path']??'/';$base=rtrim($basePath,'/');
            for($page=max(1,$data['page']-3);$page<=min($pages,$data['page']+3);$page++){$url=$page===1?$basePath:$base.'/page/'.$page.'/';$url=RouteService::encoded($url);if(($c->page['target_type']??'')==='search')$url=RouteService::encoded($basePath).'?'.http_build_query(['q'=>$c->page['query']??'','page'=>$page],'','&',PHP_QUERY_RFC3986);$html.='<a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"'.($page===$data['page']?' aria-current="page"':'').'>'.$page.'</a>';}
            return new TrustedHtml($html.'</nav>');
        },false));
        // 表单定义只作为数据传入可信文件模板；脚本每页只输出一次。
        $registry->register('form',new CallbackTag(['name'=>['type'=>'string','required'=>true]],function($p,RenderContext $c):TrustedHtml {
            $html=(new \app\cms\service\FormRenderer())->render($p['name'],$c);
            if($html!==''&&!$this->formScript){$this->formScript=true;$html.=\app\cms\service\FormRenderer::SCRIPT;}
            return new TrustedHtml($html);
        },false));
        $configFile=dirname(__DIR__).'/config/app.php';$configuration=is_file($configFile)?require $configFile:[];
        foreach(($configuration['tag_providers']??[]) as $provider){$instance=new $provider();if(!method_exists($instance,'register'))throw new \InvalidArgumentException('标签扩展必须提供 register');$instance->register($registry);}
        return $registry;
    }
}
