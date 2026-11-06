<?php
declare(strict_types=1);
spl_autoload_register(static function(string $class): void {
    $prefix='app\\admin\\plugins\\oss\\';
    if(str_starts_with($class,$prefix)){
        $file=dirname(__DIR__,2).'/plugins/oss/app/admin/plugins/oss/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
        if(is_file($file))require_once $file;
    }
},true,true);
