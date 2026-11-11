<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\model\Route;
use support\Db;
use InvalidArgumentException;
final class RouteService
{
    public static function normalize(string $path,bool $publishing=true): string
    {
        if(strlen($path)>2048||!str_starts_with($path,'/')||str_starts_with($path,'//')||preg_match('/[\\\\\x00-\x20?#]/',$path)||preg_match('/%(?:2f|5c|25)/i',$path)||preg_match('/%(?![0-9a-f]{2})/i',$path)) throw new InvalidArgumentException('访问路径无效');
        $path=rawurldecode($path);
        if(!mb_check_encoding($path,'UTF-8')||mb_strlen($path)>512||str_contains($path,'//')||preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x20?#]~',$path)) throw new InvalidArgumentException('访问路径无效');
        $path=mb_strtolower($path,'UTF-8');
        if($publishing&&(preg_match('~^/(?:admin|web|api|identity|install|cms|member|forms|plugin-assets|uploads|storage|health)(?:/|$)~',$path)||in_array(rtrim($path,'/'),['/sitemap.xml','/robots.txt','/search'],true)||preg_match('~/(?:page/[0-9]+|search)/?$~',$path))) throw new InvalidArgumentException('访问路径属于系统保留空间');
        return $path;
    }
    public function publish(string $siteId,string $locale,string $type,string|int $targetId,string $path): string
    {
        $siteId=Input::id($siteId);$targetId=Input::id($targetId);$locale=Input::locale($locale);$path=self::normalize($path);
        $table=$this->targetTable($type);
        return Db::transaction(function()use($siteId,$locale,$type,$targetId,$path,$table): string {
            (new SiteService())->lock($siteId);
            $target=Db::table($table)->where('id',$targetId)->where('site_id',$siteId)->where('locale',$locale)->whereNull('deleted_at')->lockForUpdate()->first();
            if(!$target) throw new InvalidArgumentException('路由目标不属于本站或当前语言');
            if($path==='/'&&$type!=='home') throw new InvalidArgumentException('根路径保留给首页');
            $alternatives=array_unique([$path,$path==='/'?'/':(str_ends_with($path,'/')?rtrim($path,'/'):$path.'/')]);
            foreach(Route::query()->forSite($siteId)->whereIn('path',$alternatives)->lockForUpdate()->get() as $occupied) if($occupied->target_type!==$type||(string)$occupied->target_id!==$targetId) throw new InvalidArgumentException('访问路径已被其他页面或历史地址占用',409);
            $now=Input::now();
            Route::query()->forSite($siteId)->where('target_type',$type)->where('target_id',$targetId)->update(['kind'=>'redirect','updated_at'=>$now]);
            $old=Route::query()->forSite($siteId)->where('path',$path)->first();
            if($old) { $id=(string)$old->getRawOriginal('id');Route::query()->where('id',$id)->update(['kind'=>'page','updated_at'=>$now]); }
            else { $row=new Route();$row->fill(['site_id'=>$siteId,'path'=>$path,'locale'=>$locale,'kind'=>'page','target_type'=>$type,'target_id'=>$targetId,'created_at'=>$now,'updated_at'=>$now])->save();$id=(string)$row->getKey(); }
            Db::table($table)->where('id',$targetId)->where('site_id',$siteId)->whereNull('deleted_at')->update(['canonical_route_id'=>$id]);
            return $id;
        });
    }
    public function resolve(string $siteId,string $path): ?array
    {
        $path=self::normalize($path,false);
        $row=Route::query()->forSite(Input::id($siteId))->where('path',$path)->first();
        if(!$row&&$path!=='/') $row=Route::query()->forSite($siteId)->where('path',str_ends_with($path,'/')?rtrim($path,'/'):$path.'/')->first();
        return Input::row($row);
    }
    public function canonical(string $siteId,string $type,string|int $targetId): ?array
    {
        $target=Db::table($this->targetTable($type))->where('site_id',Input::id($siteId))->where('id',Input::id($targetId))->whereNull('deleted_at')->first();
        if(!$target||!$target->canonical_route_id) return null;
        return Input::row(Route::query()->where('id',$target->canonical_route_id)->forSite($siteId)->where('target_type',$type)->where('target_id',$targetId)->where('kind','page')->first());
    }
    public function targetTable(string $type): string
    {
        return match($type){'home'=>'cms_site_locale','category'=>'cms_category_locale','content'=>'cms_content_locale',default=>throw new InvalidArgumentException('路由目标类型无效')};
    }
    public static function encoded(string $path): string { return implode('/',array_map(rawurlencode(...),explode('/',$path))); }
}
