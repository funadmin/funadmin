<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\Route;
use support\{Request,Response};
use InvalidArgumentException;
final class PublicSite
{
    public function handle(Request $request): Response
    {
        try{$response=$this->dispatch($request);}catch(InvalidArgumentException $error){$code=in_array($error->getCode(),[400,404],true)?$error->getCode():503;$response=$this->error($code);}catch(\Throwable $error){$response=$this->error(503);}
        if($request->method()==='HEAD')$response->withBody('');return $response;
    }
    private function dispatch(Request $request): Response
    {
        if(!in_array($request->method(),['GET','HEAD'],true))return new Response(405,['Allow'=>'GET, HEAD'],'');
        $sites=new SiteService();$site=$sites->byHost((string)$request->host());if(!$site)return $this->error(404);
        $internal=$request->path();if($internal!=='/cms'&&!str_starts_with($internal,'/cms/'))return $this->error(404);
        try{$path=RouteService::normalize(substr($internal,4)?:'/',false);}catch(InvalidArgumentException){return $this->error(400);}
        if($site['domain']['host']!==$site['primary_host'])return $this->redirect($site,$path,$request->queryString());
        if($path==='/robots.txt')return new Response(200,['Content-Type'=>'text/plain; charset=utf-8'],($site['settings']['robots']??"User-agent: *\nAllow: /\n")."\nSitemap: https://".$site['primary_host']."/sitemap.xml\n");
        if($path==='/sitemap.xml')return $this->cached($request,$site,(string)$site['default_locale'],'/sitemap.xml','sitemap|'.(string)($site['updated_at']??''),'application/xml',fn():string=>$this->sitemap($site));
        $pageNumber=1;$routes=new RouteService();$route=$routes->resolve($site['id'],$path);$search=false;$query='';
        if(!$route&&preg_match('~^(.*)/page/([1-9][0-9]*)/?$~',$path,$m)) { $pageNumber=(int)$m[2];if($pageNumber>10000)return $this->error(404);$route=$routes->resolve($site['id'],($m[1]?:'').'/');if($route&&!in_array($route['target_type'],['home','category'],true))$route=null; }
        if(!$route&&preg_match('~^(.*)/feed\.xml$~',$path,$m))return $this->feed($request,$site,($m[1]?:'').'/');
        if(!$route){foreach($sites->locales($site['id'],true) as $candidate){$home=$routes->canonical($site['id'],'home',$candidate['id']);if($home&&$path===rtrim($home['path'],'/').'/search/'){$route=$home;$search=true;try{$query=Input::text($request->get('q',''),100);}catch(InvalidArgumentException){return $this->error(400);}break;}}}
        if(!$route)return $this->error(404);
        $language=$sites->language($site['id'],$route['locale']);$canonical=$routes->canonical($site['id'],$route['target_type'],$route['target_id']);if(!$canonical)return $this->error(404);
        $content=[];$category=[];$template='index';$page=['target_type'=>$route['target_type'],'target_id'=>$route['target_id'],'page'=>$pageNumber,'path'=>$canonical['path'],'base_path'=>$canonical['path'],'title'=>$language['name'],'seo_title'=>$language['seo_title'],'seo_description'=>$language['seo_description'],'query'=>$query];
        if($route['target_type']==='content'){$content=(new ContentService())->publicDetail($site['id'],$route['target_id']);if(!$content)return $this->error(404);$template=$content['template'];$page=array_replace($page,array_intersect_key($content,array_flip(['title','summary','seo_title','seo_description','robots','model_code','content_id','category_id'])));}
        elseif($route['target_type']==='category'){$category=(new CategoryService())->detail($site['id'],$route['target_id']);if(!$category)return $this->error(404);$template=$category['list_template'];$page=array_replace($page,['title'=>$category['name'],'seo_title'=>$category['seo_title'],'seo_description'=>$category['seo_description'],'category_id'=>$category['id']]);}
        $expected=$canonical['path'];if($pageNumber>1)$expected=rtrim($expected,'/').'/page/'.$pageNumber.'/';
        if($route['kind']==='redirect')return $this->redirect($site,$expected,$request->queryString());
        if($search){$requestedPage=filter_var($request->get('page',1),FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);if($requestedPage===false)return $this->error(404);$page['page']=$requestedPage;$page['base_path']=$path;$expected=$path;$page['robots']='noindex,follow';$page['title']='搜索：'.$query;$page['seo_title']=$page['title'];$page['target_type']='search';if(BuiltinThemes::get($site['theme'])){$template='search';$page['title']=ThemePresentation::labels($language['locale'])['search_results'].' · '.$query;$page['seo_title']=$page['title'];}}
        elseif($path!==$expected)return $this->redirect($site,$expected,$request->queryString());
        $page['path']=$expected;
        // 搜索结果随查询变化不缓存；其余公开页按站点版本缓存。
        $variant=(string)($site['updated_at']??'').'|'.(string)($site['theme']??'').'|'.(string)($language['updated_at']??'');
        $render=fn():string=>(new Renderer())->render($site,$language,$page,$template,$content,$category);
        return $search?new Response(200,['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-cache','X-Content-Type-Options'=>'nosniff'],$render()):$this->cached($request,$site,(string)$language['locale'],$expected,$variant,'text/html',$render);
    }
    private function redirect(array $site,string $path,string $query=''): Response
    {
        return new Response(301,['Location'=>'https://'.$site['primary_host'].RouteService::encoded($path).($query!==''?'?'.$query:'')],'');
    }
    /** 语言首页下的 feed.xml：最近 20 篇已发布、未设置 noindex 的内容，RSS 2.0；与页面共用站点版本缓存。 */
    private function feed(Request $request,array $site,string $home): Response
    {
        $sites=new SiteService();$routes=new RouteService();$language=null;
        foreach($sites->locales($site['id'],true) as $candidate){$route=$routes->canonical($site['id'],'home',$candidate['id']);if($route&&rtrim($route['path'],'/').'/'===$home){$language=$sites->language($site['id'],$candidate['locale']);break;}}
        if(!$language)return $this->error(404);
        return $this->cached($request,$site,(string)$language['locale'],$home.'feed.xml','rss|'.(string)($site['updated_at']??'').'|'.(string)($language['updated_at']??''),'application/rss+xml',function()use($site,$language,$home):string{
            $x=fn(string $v):string=>htmlspecialchars($v,ENT_XML1|ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$origin='https://'.$site['primary_host'];$items='';$latest=null;
            $configuration=(new SiteConfiguration())->resolve($site['id'],$language['locale']);$name=(string)(($configuration['brand_name']??'')?:$language['name']);
            foreach((new ContentService())->listing($site['id'],$language['locale'],['limit'=>20,'order'=>'published_desc'])['items'] as $item){
                if(str_contains((string)($item['robots']??''),'noindex'))continue;$link=$origin.$item['url'];$stamp=strtotime(($item['published_at']??'').' UTC')?:null;$updated=strtotime(($item['published_updated_at']??'').' UTC')?:$stamp;if($updated&&$updated>$latest)$latest=$updated;
                $items.='<item><title>'.$x((string)$item['title']).'</title><link>'.$x($link).'</link><guid isPermaLink="true">'.$x($link).'</guid>'.($stamp?'<pubDate>'.gmdate(DATE_RSS,$stamp).'</pubDate>':'').(($item['summary']??'')!==''?'<description>'.$x((string)$item['summary']).'</description>':'').'</item>';
            }
            $xml='<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel><title>'.$x($name).'</title><link>'.$x($origin.RouteService::encoded($home)).'</link><description>'.$x((string)($language['seo_description']??'')).'</description><language>'.$x((string)$language['locale']).'</language>';
            $xml.='<atom:link href="'.$x($origin.RouteService::encoded($home.'feed.xml')).'" rel="self" type="application/rss+xml"/>'.($latest?'<lastBuildDate>'.gmdate(DATE_RSS,$latest).'</lastBuildDate>':'').$items.'</channel></rss>';
            return $xml;
        });
    }
    /** 共享缓存 + ETag：命中时直接返回，If-None-Match 匹配返回 304；缓存不可用时降级为直接生成。 */
    private function cached(Request $request,array $site,string $locale,string $path,string $variant,string $type,callable $build): Response
    {
        $cache=PublicPageCache::enabled()?new PublicPageCache():null;$cached=null;
        if($cache){try{$cached=$cache->get((string)$site['id'],$locale,$path,$variant);}catch(\Throwable){$cache=null;}}
        if($cached){$body=$cached['body'];$etag=$cached['etag'];}
        else{$body=$build();$etag=null;if($cache){$etag=$cache->etag($body);try{$cache->put((string)$site['id'],$locale,$path,$body,$etag,null,$variant);}catch(\Throwable){}}}
        $headers=['Content-Type'=>$type.'; charset=utf-8','Cache-Control'=>'no-cache','X-Content-Type-Options'=>'nosniff'];
        if($etag===null)return new Response(200,$headers,$body);
        // no-cache：浏览器与 CDN 每次带 ETag 回源校验，发布后不会继续展示旧内容。
        $headers=['Cache-Control'=>'public, no-cache','ETag'=>$etag]+$headers;
        if(PublicPageCache::matches((string)$request->header('if-none-match',''),$etag)){unset($headers['Content-Type']);return new Response(304,$headers,'');}
        return new Response(200,$headers,$body);
    }
    private function sitemap(array $site): string
    {
        $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach(Route::query()->forSite($site['id'])->where('kind','page')->orderBy('id')->limit(50000)->cursor() as $route){
            try{(new SiteService())->language($site['id'],$route->locale);}catch(InvalidArgumentException){continue;}
            $detail=null;if($route->target_type==='content'){$detail=(new ContentService())->publicDetail($site['id'],(string)$route->target_id);if(!$detail||str_contains($detail['robots'],'noindex'))continue;}
            elseif($route->target_type==='category'&&!(new CategoryService())->detail($site['id'],(string)$route->target_id))continue;
            $xml.='<url><loc>'.htmlspecialchars('https://'.$site['primary_host'].RouteService::encoded($route->path),ENT_XML1|ENT_QUOTES,'UTF-8').'</loc>';
            if($detail)$xml.='<lastmod>'.gmdate('c',strtotime($detail['published_updated_at'].' UTC')).'</lastmod>';
            $xml.='</url>';
        }
        return $xml.'</urlset>';
    }
    private function error(int $status): Response
    {
        $message=match($status){404=>'页面不存在',400=>'请求无效',default=>'服务暂不可用'};
        return new Response($status,['Content-Type'=>'text/html; charset=utf-8','Cache-Control'=>'no-store'],'<!doctype html><html lang="zh-cn"><meta charset="utf-8"><meta name="robots" content="noindex"><title>'.$status.'</title><h1>'.$status.'</h1><p>'.$message.'</p></html>');
    }
}
