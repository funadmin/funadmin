<?php
declare(strict_types=1);
return [
    'tag_providers'=>[],
    // 公开页面缓存：store 为空使用默认缓存（多进程部署需为共享存储，如 file/redis）；ttl 秒（1-3600）。
    'public_page_cache'=>['enabled'=>true,'ttl'=>300,'store'=>''],
];
