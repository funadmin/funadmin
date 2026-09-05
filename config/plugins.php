<?php

$marketplacePublicKey = getenv('PLUGIN_MARKETPLACE_PUBLIC_KEY');
$marketplacePublicKey = str_replace('\\n', "\n", trim($marketplacePublicKey === false ? '' : $marketplacePublicKey));

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
return [
    'autoload' => true,
    'hooks' => [],
    'route' => [],
    'service' => [],
    'marketplace' => [
        'public_key' => $marketplacePublicKey,
        'unsigned_policy' => 'reject_unsigned',
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'max_redirects' => 3,
        'max_package_bytes' => 104857600,
    ],
];