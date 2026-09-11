<?php

declare(strict_types=1);

return [
    // 身份发行者必须由部署配置固定，禁止从请求 Host 或代理头推导。
    'issuer' => env('identity.issuer', ''),
    // 仅用于后续入口请求校验；不会参与 issuer 计算。
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('identity.trusted_hosts', ''))))),
    // 默认不信任反向代理；显式配置后方可消费其转发信息。
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('identity.trusted_proxies', ''))))),
];