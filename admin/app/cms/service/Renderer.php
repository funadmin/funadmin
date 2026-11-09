<?php
declare(strict_types=1);
namespace app\cms\service;
use app\cms\template\RenderContext;
use app\cms\template\ThemeEngine;
use app\cms\template\TrustedHtml;
use app\cms\tags\BuiltinTags;
final class Renderer
{
    public function render(array $site,array $language,array $page,string $template,array $content=[],array $category=[]): string
    {
        $fields=[];
        if(!empty($content['model_version_id'])) {
            $schema=Input::row(\support\Db::table('cms_model_version')->where('id',$content['model_version_id'])->first());
            foreach($schema['field_schema']??[] as $field){$value=$content['data'][$field['name']]??null;if($value===null||$value==='')continue;
                if(in_array($field['type'],['image','file','gallery'],true)){
                    $html='';foreach($field['type']==='gallery'?$value:[$value] as $id){
                        $file=\support\Db::table('attach')->where('id',Input::id($id))->where('status',1)->whereNull('deleted_at')->first();if(!$file)continue;
                        $url=$file->url?:$file->path;if(!HtmlSanitizer::safeUrl($url))continue;
                        $html.=$field['type']==='file'?'<a href="'.Seo::escape($url).'" download>'.Seo::escape($file->original_name).'</a>':'<img src="'.Seo::escape($url).'" alt="'.Seo::escape($file->original_name).'" loading="lazy">';
                    }$value=new TrustedHtml($html);
                }elseif(is_bool($value))$value=$value?'是':'否';
                $fields[]=['name'=>$field['name'],'label'=>$field['label'],'value'=>$value];
            }
        }
        $page['fields']=$fields;
        // The navigation tag resolves its node markup inside the selected theme.
        $page['theme']=$site['theme'];
        $configuration=(new SiteConfiguration())->resolve($site['id'],$language['locale']);$page['config']=$configuration;
        $builtin=BuiltinThemes::get($site['theme']);
        $presentation=$builtin?(new ThemePresentation())->build($site,$language,$page,$content,$category,$template):[];
        if($presentation)$page['theme_lists']=$presentation['theme_lists'];
        if($builtin&&$template==='detail')$template=match($content['model_code']??''){'article'=>'article','product'=>'product','case'=>'case','page'=>'page','download'=>'download',default=>'detail'};
        $context=new RenderContext($site['id'],$language['locale'],$page+['content_id'=>$content['content_id']??null,'category_id'=>$category['id']??$content['category_id']??null]);
        $registry=(new BuiltinTags())->registry($context);(new TemplateTagService())->register($registry,$context);
        $languages=$registry->resolve('languages',[],$context)['value'];
        $variables=['site'=>array_replace($site,['name'=>$language['name'],'home_url'=>RouteService::encoded((new RouteService())->canonical($site['id'],'home',$language['id'])['path']??'/')]),'locale'=>$language['locale'],'page'=>$page+['query'=>'','search_url'=>rtrim((new RouteService())->canonical($site['id'],'home',$language['id'])['path']??'/','/').'/search/'],'category'=>$category,'content'=>$content+['body'=>new TrustedHtml($content['body_html']??'')],'seo'=>['head'=>Seo::head($site,$language,$page,$languages)]];
        $variables=array_replace($variables,$presentation,['config'=>$configuration,'has_languages'=>count($languages)>1,'theme'=>['code'=>$site['theme']]+($builtin??[])]);
        if(!empty($configuration['brand_name']))$variables['site']['name']=$configuration['brand_name'];
        $theme=Input::template($site['theme']);$root=dirname(__DIR__).'/view';$resolved=realpath($root.'/'.$theme);$base=realpath($root);
        if(!$base||!$resolved||!str_starts_with($resolved,$base.DIRECTORY_SEPARATOR))throw new \InvalidArgumentException('主题不存在');
        return (new ThemeEngine($registry,$resolved))->renderFile(Input::template($template),$variables,$context);
    }
}
