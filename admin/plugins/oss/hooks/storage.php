<?php
declare(strict_types=1);
use app\admin\plugins\oss\service\ProfileService;
use support\Container;
return [
    // 新上传：云端写入后、宿主写附件行前再确认配置未在上传期间被删除。
    'upload'=>static function($driver){$profiles=Container::get(ProfileService::class);$current=$profiles->current();return $current===null?$driver:$profiles->uploadDriver($current);},
    // 历史附件读取与删除：已软删除的配置仍可解析（彻底删除前附件继续可用）。
    'resolve'=>static function($driver,array $context){if($driver!==null)return $driver;return Container::get(ProfileService::class)->resolve((string)($context['name']??''));},
    'drivers'=>static function(array $drivers):array{return [...$drivers,...Container::get(ProfileService::class)->drivers()];},
];
