<?php
// webman/cors 会对所有路由回显任意 Origin 并发送 Allow-Credentials，已禁用（保留本文件以免 composer 更新时重新安装为启用）。
// 跨域改由 app\common\middleware\ApiCorsMiddleware 按 config/cors.php 的白名单只作用于 /api/*。
return [
    'enable' => false,
];
