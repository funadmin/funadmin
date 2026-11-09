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
            'editorial'=>['name'=>'杂志资讯','stylesheet'=>$base.'themes/editorial.css','hero'=>$base.'themes/images/editorial.svg'],
            'minimal'=>['name'=>'极简黑白','stylesheet'=>$base.'themes/minimal.css','hero'=>$base.'themes/images/minimal.svg'],
            'aurora'=>['name'=>'数字科技','stylesheet'=>$base.'themes/aurora.css','hero'=>$base.'themes/images/aurora.svg'],
            'heritage'=>['name'=>'东方雅韵','stylesheet'=>$base.'themes/heritage.css','hero'=>$base.'themes/images/heritage.svg'],
        ];
    }
    public static function get(string $code): ?array { return self::definitions()[$code]??null; }
}
