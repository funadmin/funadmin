<?php
declare(strict_types=1);
namespace app\cms\template;

/** Registered, bounded formatters; results always pass through the engine's HTML escaping. */
final class TemplateFormatting
{
    public static function text(array $parameters): string
    {
        $raw=$parameters['value'];$value=(string)$raw;
        if($parameters['strip_html'])$value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        if($value===''&&!is_bool($raw))$value=$parameters['default'];
        $length=$parameters['length'];
        return $length>0&&mb_strlen($value,'UTF-8')>$length?mb_substr($value,0,$length,'UTF-8').$parameters['suffix']:$value;
    }
    public static function date(array $parameters): string
    {
        $value=$parameters['value'];$format=$parameters['format'];
        if(strlen($format)>80)throw new \InvalidArgumentException('日期格式最多80字节');
        try{
            if(preg_match('/\A-?\d{1,11}\z/D',$value))$date=new \DateTimeImmutable('@'.$value);
            elseif(preg_match('/\A\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?\z/D',$value)){
                $date=new \DateTimeImmutable($value);
                $errors=\DateTimeImmutable::getLastErrors();
                if($errors&&($errors['warning_count']||$errors['error_count']))return $parameters['default'];
            }else return $parameters['default'];
            return $date->format($format);
        }catch(\Exception){return $parameters['default'];}
    }
}
