<?php
declare(strict_types=1);
namespace app\cms\service;
use support\Db;

/** Public website configuration. Definitions belong to a site; locales only override translated values. */
final class SiteConfiguration
{
    public static function defaults(): array
    {
        $rows=[['brand_name','品牌简称','text','品牌形象',true],['brand_tagline','品牌短句','text','品牌形象',true],['logo','Logo 图片地址','image','品牌形象',false],['favicon','浏览器图标地址','image','品牌形象',false],['hero_title','首页主标题','text','首页展示',true],['hero_subtitle','首页副标题','text','首页展示',true],['hero_description','首页介绍','textarea','首页展示',true],['phone','联系电话','text','联系信息',false],['email','联系邮箱','email','联系信息',false],['address','联系地址','textarea','联系信息',true],['contact_url','联系页面地址','url','联系信息',true],['footer_text','页脚品牌文案','text','版权备案',true],['copyright','版权信息','text','版权备案',true],['icp_number','ICP备案号','text','版权备案',false],['icp_url','备案查询链接','url','版权备案',false]];
        return array_map(static fn($r)=>['key'=>$r[0],'label'=>$r[1],'type'=>$r[2],'group'=>$r[3],'localized'=>$r[4],'default'=>'','options'=>[]],$rows);
    }
    public static function fields(mixed $fields,bool $nested=false): array
    {
        if(!is_array($fields)||!array_is_list($fields)||count($fields)>80)throw new \InvalidArgumentException('配置项定义必须是列表，最多80项',422);
        $result=[];$seen=[];
        foreach($fields as $field){
            if(!is_array($field))throw new \InvalidArgumentException('配置项格式无效',422);
            $key=(string)($field['key']??'');if(!preg_match('/^[a-z][a-z0-9_]{0,63}$/D',$key)||in_array($key,['prototype','constructor','__proto__'],true)||isset($seen[$key]))throw new \InvalidArgumentException('配置标识无效或重复：'.$key,422);$seen[$key]=true;
            $type=$field['type']??'text';if(!in_array($type,['text','textarea','number','boolean','url','email','image','color','select','list'],true))throw new \InvalidArgumentException('配置类型无效',422);
            if($nested&&($type==='list'||$key==='enabled'))throw new \InvalidArgumentException('列表字段不能嵌套列表，enabled 为条目启停保留字段',422);
            $requiredType=$nested?null:(['logo'=>'image','favicon'=>'image','contact_url'=>'url','icp_url'=>'url','email'=>'email'][$key]??null);
            if($requiredType!==null&&$type!==$requiredType)throw new \InvalidArgumentException('内置配置 '.$key.' 必须使用 '.$requiredType.' 类型',422);
            $label=Input::text($field['label']??'',80);$group=Input::text($field['group']??'其他',40);if($label===''||$group==='')throw new \InvalidArgumentException('配置名称和分组不能为空',422);
            $localized=$field['localized']??false;if(!is_bool($localized))throw new \InvalidArgumentException('多语言标记必须为布尔值',422);
            $options=$type==='select'?($field['options']??[]):[];if(!is_array($options)||!array_is_list($options)||count($options)>50)throw new \InvalidArgumentException('下拉选项无效',422);
            $options=array_map(static fn($v)=>Input::text($v,100),$options);if($type==='select'&&(!$options||in_array('',$options,true)||count($options)!==count(array_unique($options))))throw new \InvalidArgumentException('下拉选项不能为空或重复',422);
            $row=['key'=>$key,'label'=>$label,'type'=>$type,'group'=>$group,'localized'=>$localized,'options'=>$options];
            if($type==='list'){
                $children=$field['fields']??null;
                if(!is_array($children)||!array_is_list($children)||!$children||count($children)>20)throw new \InvalidArgumentException('列表模型需要1至20个字段',422);
                $row['fields']=self::fields($children,true);
            }
            $row['default']=self::value($row,$field['default']??match($type){'boolean'=>false,'number'=>0,'list'=>[],default=>''});$result[]=$row;
        }
        return $result;
    }
    private static function value(array $field,mixed $value): mixed
    {
        $type=$field['type'];
        if($type==='list'){
            if(!is_array($value)||!array_is_list($value)||count($value)>100)throw new \InvalidArgumentException($field['label'].'必须是列表，最多100条',422);
            $result=[];$keys=array_merge(array_column($field['fields'],'key'),['enabled']);
            foreach($value as $item){
                if(!is_array($item)||($item!==[]&&array_is_list($item))||array_diff(array_keys($item),$keys))throw new \InvalidArgumentException($field['label'].'条目包含未声明字段',422);
                $enabled=array_key_exists('enabled',$item)?$item['enabled']:true;if(!is_bool($enabled))throw new \InvalidArgumentException('条目启停必须为开关值',422);
                $row=['enabled'=>$enabled];
                foreach($field['fields'] as $child)$row[$child['key']]=self::value($child,array_key_exists($child['key'],$item)?$item[$child['key']]:$child['default']);
                $result[]=$row;
            }
            return $result;
        }
        if($value===null)return null;
        if($type==='boolean'){if(!is_bool($value))throw new \InvalidArgumentException($field['label'].'必须为开关值',422);return $value;}
        if($type==='number'){if(!(is_int($value)||is_float($value))||!is_finite((float)$value))throw new \InvalidArgumentException($field['label'].'必须为有限数字',422);return $value;}
        $text=Input::text($value,$type==='textarea'?10000:2000);if($text==='')return '';
        if(in_array($type,['url','image'],true)&&!HtmlSanitizer::safeUrl($text,$type==='url'))throw new \InvalidArgumentException($field['label'].'地址无效',422);
        if($type==='email'&&!filter_var($text,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('邮箱格式无效',422);
        if($type==='color'&&!preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6}|[a-f0-9]{8})$/iD',$text))throw new \InvalidArgumentException('颜色必须使用十六进制格式',422);
        if($type==='select'&&!in_array($text,$field['options'],true))throw new \InvalidArgumentException('配置选项无效',422);
        return $text;
    }
    private static function values(array $fields,mixed $input,bool $localized=false): array
    {
        if(!is_array($input)||($input!==[]&&array_is_list($input)))throw new \InvalidArgumentException('配置值必须是键值对象',422);
        $byKey=array_column($fields,null,'key');$result=[];
        foreach($input as $key=>$value){$field=$byKey[$key]??null;if(!$field||$localized&&!$field['localized'])throw new \InvalidArgumentException('配置项未声明或不可按语言覆盖：'.$key,422);$result[$key]=self::value($field,$value);}
        return $result;
    }
    public static function siteSettings(array $settings): array
    {
        if(!array_key_exists('configuration',$settings))return $settings;
        $data=$settings['configuration'];if(!is_array($data)||array_diff(array_keys($data),['fields','values']))throw new \InvalidArgumentException('站点配置格式无效',422);
        $fields=self::fields($data['fields']??self::defaults());$settings['configuration']=['fields'=>$fields,'values'=>self::values($fields,$data['values']??[])];return $settings;
    }
    public static function localeSettings(string $siteId,array $settings): array
    {
        if(!array_key_exists('configuration',$settings))return $settings;
        $data=$settings['configuration'];if(!is_array($data)||array_diff(array_keys($data),['values']))throw new \InvalidArgumentException('语言配置只允许覆盖值，不能修改配置定义',422);
        $site=(new SiteService())->get($siteId);$fields=self::fields($site['settings']['configuration']['fields']??self::defaults());$settings['configuration']=['values'=>self::values($fields,$data['values']??[],true)];return $settings;
    }
    /** All schema writers share locale reconciliation under the owning site's transaction lock. */
    public static function reconcileLocales(string $siteId,array $fields): void
    {
        $localized=array_column(array_values(array_filter($fields,fn($f)=>$f['localized'])),'key');
        foreach((new SiteService())->locales($siteId) as $language){
            $settings=$language['settings']??[];if(!isset($settings['configuration']))continue;
            $old=$settings['configuration']['values']??[];$next=self::values($fields,array_intersect_key($old,array_flip($localized)),true);
            if($old!==$next){$settings['configuration']=['values'=>$next];Db::table('cms_site_locale')->where('site_id',$siteId)->where('id',$language['id'])->update(['settings'=>Input::json($settings),'updated_at'=>Input::now()]);}
        }
    }
    public function resolve(string $siteId,string $locale): array
    {
        $site=(new SiteService())->get($siteId);$language=(new SiteService())->language($siteId,$locale);$fields=self::fields($site['settings']['configuration']['fields']??self::defaults());
        $values=array_column($fields,'default','key');$base=$site['settings']['configuration']['values']??[];$overrides=$language['settings']['configuration']['values']??[];
        foreach($fields as $field){$key=$field['key'];if(array_key_exists($key,$base))$values[$key]=self::value($field,$base[$key]);if($field['localized']&&array_key_exists($key,$overrides))$values[$key]=self::value($field,$overrides[$key]);}
        return $values;
    }
    public function read(string $siteId,string $locale=''): array
    {
        $site=(new SiteService())->get($siteId);$fields=self::fields($site['settings']['configuration']['fields']??self::defaults());$values=self::values($fields,$site['settings']['configuration']['values']??[]);$language=$locale!==''?(new SiteService())->language($siteId,$locale):null;
        return ['fields'=>$fields,'values'=>$values,'overrides'=>$language?self::values($fields,$language['settings']['configuration']['values']??[],true):[],'effective'=>$this->resolve($siteId,$locale?:$site['default_locale']),'locale'=>$locale];
    }
    public function save(string $siteId,string $actor,array $input): array
    {
        return Db::transaction(function()use($siteId,$actor,$input):array{
            $site=(new SiteService())->lock($siteId);$locale=empty($input['locale'])?'':Input::locale($input['locale']);(new SiteAuthorization())->assert($siteId,$actor,$locale===''?'cms:site:edit':'cms:site:locale');
            if($locale!==''){
                if(array_key_exists('fields',$input))throw new \InvalidArgumentException('语言配置不能修改定义',422);
                $language=(new SiteService())->language($siteId,$locale);$settings=self::localeSettings($siteId,array_replace($language['settings']??[],['configuration'=>['values'=>$input['values']??[]]]));
                Db::table('cms_site_locale')->where('site_id',$siteId)->where('id',$language['id'])->update(['settings'=>Input::json($settings),'updated_at'=>Input::now()]);
            } else {
                $fields=self::fields($input['fields']??$site['settings']['configuration']['fields']??self::defaults());$values=self::values($fields,$input['values']??$site['settings']['configuration']['values']??[]);
                self::reconcileLocales($siteId,$fields);
                $settings=array_replace($site['settings']??[],['configuration'=>['fields'=>$fields,'values'=>$values]]);Db::table('cms_site')->where('id',$siteId)->update(['settings'=>Input::json($settings),'updated_at'=>Input::now()]);
            }
            return $this->read($siteId,$locale);
        });
    }
}
