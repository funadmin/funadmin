<?php

// 进程环境变量优先；ThinkPHP 的 .env 不写入 getenv()，需再经 env() 读取。
$marketplaceEnv = static fn (string $name): string => trim((string) (getenv($name) ?: (function_exists('env') ? env($name, '') : '')));
$marketplacePublicKey = $marketplaceEnv('PLUGIN_MARKETPLACE_PUBLIC_KEY');
$decodedMarketplacePublicKey = base64_decode($marketplacePublicKey, true);
if (!defined('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES')
    || $decodedMarketplacePublicKey === false
    || strlen($decodedMarketplacePublicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
    || !hash_equals(base64_encode($decodedMarketplacePublicKey), $marketplacePublicKey)) {
    $marketplacePublicKey = '';
}

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
    'marketplace' => [
        // 为空时使用 funadmin.api_domain；自建市场填写插件市场服务端的对外地址，例如 https://market.example.com/market
        'url' => rtrim($marketplaceEnv('PLUGIN_MARKETPLACE_URL'), '/'),
        // 仅在配置了自建市场地址时，向该市场上报云市场插件的安装/升级/卸载/启用/禁用事件；设为 false 关闭。
        'telemetry' => strtolower($marketplaceEnv('PLUGIN_MARKETPLACE_TELEMETRY')) !== 'false',
        'public_key' => $marketplacePublicKey,
        'unsigned_policy' => 'reject_unsigned',
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'max_redirects' => 3,
        'max_package_bytes' => 104857600,
    ],
];