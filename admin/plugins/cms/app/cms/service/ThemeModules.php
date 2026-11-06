<?php
declare(strict_types=1);
namespace app\cms\service;

/** Explicit projection protects shipped theme attributes even for custom text-based schemas. */
final class ThemeModules
{
    // Reserve enough of the shared 200-call template budget for navigation/content and all four modules.
    public const LIMITS=['slides'=>8,'partners'=>12,'contacts'=>6,'ads'=>4];
    public static function build(array $configuration): array
    {
        $result=['slides'=>[],'partners'=>[],'contacts'=>[],'ads'=>[]];
        $fields=['slides'=>['title','image','mobile_image','url'],'partners'=>['name','logo','url'],'contacts'=>['name','phone','email','qrcode'],'ads'=>['name','image','url','position']];
        foreach($fields as $key=>$names){
            $rows=$configuration[$key]??[];if(!is_array($rows)||!array_is_list($rows))continue;
            foreach(array_slice($rows,0,100) as $row){
                if(!is_array($row)||($row['enabled']??true)!==true)continue;$item=[];
                foreach($names as $name){
                    $value=is_scalar($row[$name]??null)?(string)$row[$name]:'';
                    if(in_array($name,['image','mobile_image','logo','qrcode','url'],true)&&$value!==''&&!HtmlSanitizer::safeUrl($value,$name==='url'))$value='';
                    if($name==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))$value='';
                    $item[$name]=$value;
                }
                if($key==='slides'&&$item['image']==='')continue;
                if($key==='partners'&&$item['name']===''&&$item['logo']==='')continue;
                if($key==='contacts'&&$item['name']===''&&$item['phone']===''&&$item['email']==='')continue;
                if($key==='ads'&&(!in_array($item['position'],['','home'],true)||($item['name']===''&&$item['image']==='')))continue;
                $result[$key][]=$item;
                if(count($result[$key])>=self::LIMITS[$key])break;
            }
        }
        return $result;
    }
}
