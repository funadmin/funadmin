<?php
declare(strict_types=1);
namespace app\cms\service;

/** Names and presentation assets for the themes shipped with CMS. Custom themes remain independent. */
final class BuiltinThemes
{
    public static function definitions(): array
    {
        $base='/plugin-assets/cms/public/';
        return [
            'default'=>['name'=>'默认企业与资讯主题','stylesheet'=>'','hero'=>$base.'images/studio.svg'],
            'corporate'=>['name'=>'商务蓝白','stylesheet'=>$base.'themes/corporate.css','hero'=>$base.'themes/images/corporate.svg'],
            'industrial'=>['name'=>'工业科技','stylesheet'=>$base.'themes/industrial.css','hero'=>$base.'themes/images/industrial.svg'],
            'studio'=>['name'=>'现代品牌','stylesheet'=>$base.'themes/studio.css','hero'=>$base.'themes/images/studio.svg'],
        ];
    }
    public static function get(string $code): ?array { return self::definitions()[$code]??null; }
}
