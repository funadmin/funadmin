<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\template\TrustedHtml;
use support\Db;
final class Seo
{
    private const JSON=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR;
    public static function escape(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    /** 标题、描述、canonical、hreflang、分页 prev、RSS、Open Graph/Twitter Card 与按模型区分的 JSON-LD；所有用户文本经属性转义或 JSON 十六进制转义输出。 */
    public static function head(array $site,array $language,array $page,array $languages,array $content=[]): TrustedHtml
    {
        $title=$page['seo_title']??'';if($title==='')$title=$page['title']??$language['seo_title']?:$language['name'];
        $description=$page['seo_description']??'';if($description==='')$description=$page['summary']??$language['seo_description'];$description=(string)$description;
        $origin='https://'.$site['primary_host'];$url=$origin.RouteService::encoded($page['path']);
        $siteName=(string)(($page['config']['brand_name']??'')?:$language['name']);$number=(int)($page['page']??1);$type=(string)($page['target_type']??'');
        $html='<title>'.self::escape($title).'</title><meta name="description" content="'.self::escape($description).'">';
        $html.='<meta name="robots" content="'.self::escape($page['robots']??'index,follow').'"><link rel="canonical" href="'.self::escape($url).'">';
        if($number>1&&$type!=='search'){$base=rtrim((string)$page['base_path'],'/').'/';$html.='<link rel="prev" href="'.self::escape($origin.RouteService::encoded($number===2?$base:$base.'page/'.($number-1).'/')).'">';}
        if($number===1&&!str_contains($page['robots']??'','noindex'))foreach($languages as $translation)$html.='<link rel="alternate" hreflang="'.self::escape($translation['locale']).'" href="'.self::escape($origin.$translation['url']).'">';
        $home=self::homePath($site,$language);
        if($home!==null)$html.='<link rel="alternate" type="application/rss+xml" href="'.self::escape($origin.RouteService::encoded($home.'feed.xml')).'" title="'.self::escape($siteName).'">';
        $model=(string)($content['model_code']??$page['model_code']??'');$image=self::image($content['cover_attach_id']??null,$origin);
        $published=self::time($content['published_at']??null);$modified=self::time($content['published_updated_at']??null)??$published;
        $og=['og:type'=>$model==='article'?'article':'website','og:title'=>$title,'og:description'=>$description,'og:url'=>$url,'og:site_name'=>$siteName,'og:locale'=>self::ogLocale((string)$language['locale'])];
        if($image)$og+=['og:image'=>$image['url']]+($image['width']>0&&$image['height']>0?['og:image:width'=>(string)$image['width'],'og:image:height'=>(string)$image['height']]:[]);
        if($model==='article'&&$published)$og+=['article:published_time'=>$published,'article:modified_time'=>$modified];
        foreach($og as $property=>$value)$html.='<meta property="'.$property.'" content="'.self::escape($value).'">';
        foreach(['twitter:card'=>$image?'summary_large_image':'summary','twitter:title'=>$title,'twitter:description'=>$description]+($image?['twitter:image'=>$image['url']]:[]) as $name=>$value)$html.='<meta name="'.$name.'" content="'.self::escape($value).'">';
        $structured=['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$title,'url'=>$url,'inLanguage'=>$language['locale']];
        if($description!=='')$structured['description']=$description;
        $publisher=['@type'=>'Organization','name'=>$siteName,'url'=>$origin.'/'];$logo=self::absolute((string)($page['config']['logo']??''),$origin);if($logo!=='')$publisher['logo']=$logo;
        if($model==='article')$structured=array_replace($structured,['@type'=>'Article','headline'=>mb_substr($title,0,110),'mainEntityOfPage'=>$url,'publisher'=>$publisher]+($image?['image'=>[$image['url']]]:[])+($published?['datePublished'=>$published,'dateModified'=>$modified]:[]));
        elseif($model==='product'){$structured=array_replace($structured,['@type'=>'Product','brand'=>['@type'=>'Brand','name'=>$siteName]]+($image?['image'=>[$image['url']]]:[]));$sku=$content['data']['sku']??'';if(is_string($sku)&&$sku!=='')$structured['sku']=$sku;}
        elseif($type==='home'&&$number===1&&$home!==null)$structured=array_replace($structured,['@type'=>'WebSite','name'=>$siteName,'publisher'=>$publisher,'potentialAction'=>['@type'=>'SearchAction','target'=>['@type'=>'EntryPoint','urlTemplate'=>$origin.RouteService::encoded($home.'search/').'?q={search_term_string}'],'query-input'=>'required name=search_term_string']]);
        return new TrustedHtml($html.'<script type="application/ld+json">'.json_encode($structured,self::JSON).'</script>');
    }
    /** 语言首页路径，以 / 结尾；首页未发布时返回 null。 */
    public static function homePath(array $site,array $language): ?string
    {
        if(empty($language['id']))return null;
        $home=(new RouteService())->canonical($site['id'],'home',$language['id']);return $home?rtrim($home['path'],'/').'/':null;
    }
    /** 数据库时间按 UTC 存储，输出 ISO 8601。 */
    public static function time(mixed $value): ?string
    {
        if(!is_string($value)||$value==='')return null;$stamp=strtotime($value.' UTC');return $stamp===false?null:gmdate('c',$stamp);
    }
    public static function absolute(string $url,string $origin): string
    {
        if($url===''||!HtmlSanitizer::safeUrl($url))return '';
        if(preg_match('~^https?://~i',$url))return $url;
        if(str_starts_with($url,'//'))return 'https:'.$url;
        return str_starts_with($url,'/')?$origin.$url:'';
    }
    /** @return array{url:string,width:int,height:int}|null */
    private static function image(mixed $id,string $origin): ?array
    {
        if(!$id||!Db::schema()->hasTable('attach'))return null;
        try{$id=Input::id($id);}catch(\InvalidArgumentException){return null;}
        $file=Db::table('attach')->where('id',$id)->where('status',1)->whereNull('deleted_at')->first();
        if(!$file||!in_array(strtolower((string)$file->ext),['jpg','jpeg','png','gif','webp','avif'],true))return null;
        $url=self::absolute((string)($file->url?:$file->path),$origin);
        return $url===''?null:['url'=>$url,'width'=>(int)($file->width??0),'height'=>(int)($file->height??0)];
    }
    private static function ogLocale(string $locale): string
    {
        $parts=explode('-',$locale,2);return count($parts)===2?strtolower($parts[0]).'_'.strtoupper($parts[1]):strtolower($locale);
    }
}
