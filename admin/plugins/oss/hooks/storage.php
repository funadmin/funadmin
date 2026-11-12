<?php
declare(strict_types=1);
use app\admin\plugins\oss\service\ProfileService;
use support\Container;
return [
    'upload'=>static function($driver){$profiles=Container::get(ProfileService::class);$current=$profiles->current();return $current===null?$driver:$profiles->driver('oss_'.$current);},
    // 历史附件读取与删除：已软删除的配置仍可解析（彻底删除前附件继续可用）。
    'resolve'=>static function($driver,array $context){if($driver!==null)return $driver;return Container::get(ProfileService::class)->resolve((string)($context['name']??''));},
    'drivers'=>static function(array $drivers):array{return [...$drivers,...Container::get(ProfileService::class)->drivers()];},
];
