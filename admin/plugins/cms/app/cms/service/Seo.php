<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\template\TrustedHtml;
final class Seo
{
    public static function escape(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public static function head(array $site,array $language,array $page,array $languages): TrustedHtml
    {
        $title=$page['seo_title']??'';if($title==='')$title=$page['title']??$language['seo_title']?:$language['name'];
        $description=$page['seo_description']??'';if($description==='')$description=$page['summary']??$language['seo_description'];
        $url='https://'.$site['primary_host'].RouteService::encoded($page['path']);
        $html='<title>'.self::escape($title).'</title><meta name="description" content="'.self::escape($description).'">';
        $html.='<meta name="robots" content="'.self::escape($page['robots']??'index,follow').'"><link rel="canonical" href="'.self::escape($url).'">';
        if(($page['page']??1)===1&&!str_contains($page['robots']??'','noindex'))foreach($languages as $translation)$html.='<link rel="alternate" hreflang="'.self::escape($translation['locale']).'" href="'.self::escape('https://'.$site['primary_host'].$translation['url']).'">';
        $structured=['@context'=>'https://schema.org','@type'=>($page['model_code']??'')==='article'?'Article':'WebPage','name'=>$title,'url'=>$url,'inLanguage'=>$language['locale']];
        return new TrustedHtml($html.'<script type="application/ld+json">'.json_encode($structured,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).'</script>');
    }
}
