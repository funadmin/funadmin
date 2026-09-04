<?php

declare(strict_types=1);

use think\facade\Env;

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
// API 接口配置
return [
    'jwt_secret' => Env::get('API_JWT_SECRET', ''),
    'refresh_jwt_secret' => Env::get('API_REFRESH_JWT_SECRET', ''),
    'access_token_ttl' => (int) Env::get('API_ACCESS_TOKEN_TTL', 3600 * 2),
    'refresh_token_ttl' => (int) Env::get('API_REFRESH_TOKEN_TTL', 3600 * 24 * 30),
    'issuer' => Env::get('API_JWT_ISSUER', 'funadmin.com'),
    'audience' => Env::get('API_JWT_AUDIENCE', 'funadmin'),
];
