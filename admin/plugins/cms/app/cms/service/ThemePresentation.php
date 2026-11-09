<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/** Safe view data for the built-in corporate/editorial theme; contains no persistence. */
final class ThemePresentation
{
    public static function labels(string $locale): array
    {
        $zh=str_starts_with($locale,'zh');
        $en=[
            'skip'=>'Skip to content','content_list'=>'Content list','home'=>'Home','company'=>'Our company','products'=>'Products & solutions','cases'=>'Selected work','insights'=>'Insights & stories','search'=>'Search','menu'=>'Menu','close'=>'Close','language'=>'Language','explore'=>'Explore our work','read_insights'=>'Read our insights','discover'=>'Discover more','read_more'=>'Read the story','all_content'=>'Explore all content','empty'=>'More stories are on their way.','empty_note'=>'A new perspective is always worth waiting for. Come back soon.','search_placeholder'=>'What would you like to discover?','search_results'=>'Search results','search_note'=>'Find ideas, expertise and the details that matter.','results'=>'results','no_results'=>'No matching stories yet','no_results_note'=>'Try another keyword or browse our latest insights.','published'=>'Published','author'=>'Written by','details'=>'Details & specifications','related'=>'Keep exploring','back_home'=>'Back to home','back_top'=>'Back to top','copyright'=>'All rights reserved.','footer_line'=>'Good ideas deserve to become great work.','hero_line_one'=>'Ideas with purpose.','hero_line_two'=>'Work with impact.','hero_intro'=>'Meet our expertise, explore our work and discover the thinking behind it.','hero_badge'=>'A considered approach. A lasting connection.','hero_caption'=>'Space for the next possibility.','about_kicker'=>'BUILT AROUND YOU','about_title'=>'A little curiosity.\nA lot of possibility.','about_intro'=>'Discover who we are through the things we make, the work we share and the ideas we keep exploring.','principle_one'=>'Start with understanding','principle_one_body'=>'See the context. Ask the right questions. Find what matters.','principle_two'=>'Make room for better','principle_two_body'=>'Explore new perspectives and bring thoughtful ideas to life.','principle_three'=>'Keep moving forward','principle_three_body'=>'Every meaningful conversation is the start of something new.','products_intro'=>'Thoughtfully made. Ready for what comes next.','cases_intro'=>'The stories behind the work.','insights_intro'=>'Fresh thinking, practical knowledge and perspectives worth sharing.','newsletter_title'=>'Stay curious.\nKeep in touch.','newsletter_intro'=>'Explore more of our latest thinking and find your next idea.','article'=>'Article','product'=>'Product','case'=>'Case study','page'=>'Company','download'=>'Resources','resource_title'=>'Resources worth keeping','resource_intro'=>'Discover the information and files shared alongside this resource.','page_intro'=>'Get to know us, one story at a time.','category_intro'=>'Ideas, expertise and stories from our world.','pagination'=>'Page navigation','year'=>gmdate('Y'),
        ];
        if(!$zh)return $en;
        return array_replace($en,[
            'skip'=>'跳转到正文','content_list'=>'内容列表','home'=>'首页','company'=>'了解我们','products'=>'产品与方案','cases'=>'精选案例','insights'=>'观点与资讯','search'=>'搜索','menu'=>'菜单','close'=>'关闭','language'=>'语言','explore'=>'探索我们的工作','read_insights'=>'阅读最新观点','discover'=>'了解更多','read_more'=>'阅读全文','all_content'=>'浏览全部内容','empty'=>'更多内容，正在准备中','empty_note'=>'每一份值得分享的内容，都值得认真准备。欢迎再次来访。','search_placeholder'=>'搜索感兴趣的内容','search_results'=>'搜索结果','search_note'=>'在这里，发现你感兴趣的观点、案例与专业知识。','results'=>'条相关内容','no_results'=>'暂时没有找到相关内容','no_results_note'=>'换一个关键词试试，或去发现更多最新内容。','published'=>'发布时间','author'=>'作者','details'=>'详细信息','related'=>'继续探索','back_home'=>'返回首页','back_top'=>'回到顶部','copyright'=>'保留所有权利。','footer_line'=>'让好想法，成为更好的作品。','hero_line_one'=>'让专业被看见，','hero_line_two'=>'让合作有价值。','hero_intro'=>'从产品与方案，到实践与思考。在这里，认识我们的工作，也发现更多新的可能。','hero_badge'=>'以专业为起点，以信任相连接。','hero_caption'=>'为下一个可能，留一点空间。','about_title'=>'始于理解，\n成于每一次认真。','about_intro'=>'通过我们的产品、项目与持续分享的思考，认识我们，以及我们对工作的态度。','principle_one'=>'理解，先于行动','principle_one_body'=>'看见需求背后的问题，让每一次出发都有清晰的方向。','principle_two'=>'细节，成就表达','principle_two_body'=>'在每一个具体的选择中，寻找更合适、更有价值的答案。','principle_three'=>'分享，连接彼此','principle_three_body'=>'分享经验与新的发现，让有价值的思考被更多人看见。','products_intro'=>'从真实需求出发，发现合适的产品与方案。','cases_intro'=>'每一个项目，都是一次新的探索。','insights_intro'=>'记录新的发现，分享专业观点，让思考持续发生。','newsletter_title'=>'保持好奇，\n让思考继续。','newsletter_intro'=>'阅读更多最新内容，发现值得交流的下一个话题。','article'=>'文章资讯','product'=>'产品方案','case'=>'项目案例','page'=>'企业介绍','download'=>'资料下载','resource_title'=>'值得留存的专业资料','resource_intro'=>'在这里了解资料详情，并获取相关文件。','page_intro'=>'从一段介绍，开始认识彼此。','category_intro'=>'在观点、经验与实践之间，发现更多可能。','pagination'=>'分页导航',
        ]);
    }
    public function build(array $site,array $language,array $page,array $content=[],array $category=[],string $template='index'): array
    {
        $ui=self::labels($language['locale']);
        $overrides=array_replace((array)($site['settings']['presentation']??[]),(array)($language['settings']['presentation']??[]));
        foreach($overrides as $key=>$value)if(array_key_exists($key,$ui)&&is_string($value))$ui[$key]=mb_substr($value,0,2000);
        foreach($ui as &$text)$text=str_replace('\\n',"\n",$text);unset($text);
        $config=$page['config']??(new SiteConfiguration())->resolve($site['id'],$language['locale']);
        $ui+=str_starts_with($language['locale'],'zh')?['slides'=>'首页轮播','partners'=>'合作伙伴','contacts'=>'联系我们','ads'=>'精选推荐','previous_slide'=>'上一张','next_slide'=>'下一张']:['slides'=>'Featured highlights','partners'=>'Our partners','contacts'=>'Get in touch','ads'=>'Featured offers','previous_slide'=>'Previous slide','next_slide'=>'Next slide'];
        foreach(['hero_title'=>'hero_line_one','hero_subtitle'=>'hero_line_two','footer_text'=>'footer_line'] as $key=>$label)if(!empty($config[$key]))$ui[$label]=$config[$key];
        $homePath=RouteService::encoded((new RouteService())->canonical($site['id'],'home',$language['id'])['path']??'/');
        $isHome=$template==='index'&&($page['target_type']??'')==='home'&&(int)($page['page']??1)===1;
        $modules=ThemeModules::build($isHome?$config:[]);
        $isListing=in_array($template,['index','category','search'],true)&&in_array($page['target_type']??'',['home','category','search'],true)&&!$isHome;
        $lists=['products'=>[],'cases'=>[],'articles'=>[],'items'=>[],'related'=>[],'categories'=>[],'pagination'=>[]]+$modules;$service=new ContentService();
        if($isHome)foreach(['products'=>['product',3],'cases'=>['case',2],'articles'=>['article',4]] as $key=>[$model,$limit])$lists[$key]=$this->cards($service->listing($site['id'],$language['locale'],['model'=>$model,'limit'=>$limit])['items'],$ui);
        $total=0;
        if($isListing){
            $filters=['page'=>$page['page']??1,'limit'=>12,'q'=>($page['target_type']==='search'?$page['query']??'':'')];
            if(($page['target_type']??'')==='category')$filters['category_id']=$category['id'];
            $result=$service->listing($site['id'],$language['locale'],$filters);$total=$result['total'];$lists['items']=$this->cards($result['items'],$ui);
            if((int)$result['page']>1&&!$lists['items'])throw new \InvalidArgumentException('页面不存在',404);
            $pages=(int)ceil($total/12);$current=(int)$result['page'];$base=$page['base_path']??$homePath;
            for($n=max(1,$current-3);$pages>1&&$n<=min($pages,$current+3);$n++){
                $url=($page['target_type']==='search')?RouteService::encoded($base).'?'.http_build_query(['q'=>$page['query']??'','page'=>$n],'','&',PHP_QUERY_RFC3986):RouteService::encoded($n===1?$base:rtrim($base,'/').'/page/'.$n.'/');
                $lists['pagination'][]=['number'=>$n,'url'=>$url,'current'=>$n===$current];
            }
        }
        $lists['categories']=(new CategoryService())->listing($site['id'],$language['locale']);
        if($content){$rows=$service->listing($site['id'],$language['locale'],['model'=>$content['model_code']??'article','limit'=>4])['items'];$lists['related']=array_slice($this->cards(array_values(array_filter($rows,fn($r)=>$r['content_id']!==$content['content_id'])),$ui),0,3);}
        $display=$content?$this->card($content,$ui):['cover_url'=>'','model_label'=>'','published_label'=>''];
        $display['has_fields']=!empty($page['fields']);$display['has_body']=trim(strip_tags((string)($content['body_html']??'')))!=='';
        return ['ui'=>$ui,'home'=>['is_home'=>$isHome,'is_listing'=>$isListing,'url'=>$homePath,'has_slides'=>(bool)$lists['slides'],'has_multiple_slides'=>count($lists['slides'])>1,'has_partners'=>(bool)$lists['partners'],'has_contacts'=>(bool)$lists['contacts'],'has_ads'=>(bool)$lists['ads'],'has_products'=>(bool)$lists['products'],'has_cases'=>(bool)$lists['cases'],'has_articles'=>(bool)$lists['articles'],'intro'=>!empty($config['hero_description'])?$config['hero_description']:($language['seo_description']?:$ui['hero_intro'])],'listing'=>['has_items'=>(bool)$lists['items'],'total'=>$total,'has_pagination'=>(bool)$lists['pagination']],'display'=>$display,'theme_lists'=>$lists,'has_navigation'=>(bool)(new MenuService())->items($site['id'],$language['locale'],'header')];
    }
    public function cover(mixed $id): string
    {
        if(!$id||!Db::schema()->hasTable('attach'))return '';
        try{$id=Input::id($id);}catch(\InvalidArgumentException){return '';}
        $file=Db::table('attach')->where('id',$id)->where('status',1)->whereNull('deleted_at')->first();
        if(!$file||!in_array(strtolower((string)$file->ext),['jpg','jpeg','png','gif','webp','avif'],true))return '';
        $url=(string)($file->url?:$file->path);return $url!==''&&HtmlSanitizer::safeUrl($url)?$url:'';
    }
    private function cards(array $rows,array $ui): array { return array_map(fn($r)=>$this->card($r,$ui),$rows); }
    private function card(array $row,array $ui): array
    {
        return $row+['cover_url'=>$this->cover($row['cover_attach_id']??null),'model_label'=>$ui[$row['model_code']??'article']??$ui['article'],'published_label'=>!empty($row['published_at'])?substr($row['published_at'],0,10):''];
    }
}
