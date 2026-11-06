<?php
declare(strict_types=1);
use app\admin\plugins\oss\service\ProfileService;
use support\Container;
return [
    'upload'=>static function($driver){$profiles=Container::get(ProfileService::class);$current=$profiles->current();return $current===null?$driver:$profiles->driver('oss_'.$current);},
    'resolve'=>static function($driver,array $context){if($driver!==null)return $driver;return Container::get(ProfileService::class)->driver((string)($context['name']??''));},
    'drivers'=>static function(array $drivers):array{return [...$drivers,...Container::get(ProfileService::class)->drivers()];},
];
