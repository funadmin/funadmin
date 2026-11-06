<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/Cms/bootstrap.php';
spl_autoload_register(static function(string $class): void {
    foreach(['app\\qa\\'=>'/app/qa/','app\\admin\\plugins\\qa\\'=>'/app/admin/plugins/qa/'] as $prefix=>$directory) {
        if(str_starts_with($class,$prefix)) {
            $path=dirname(__DIR__,2).'/plugins/qa'.$directory.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
            if(is_file($path)) require_once $path;
        }
    }
},true,true);
