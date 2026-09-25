<?php

declare(strict_types=1);

namespace app\market\service\payment;

use OpenSSLAsymmetricKey;

/** 支付平台常以裸 Base64 下发 RSA 密钥，统一补齐 PEM 头后加载。 */
final class PemKey
{
    public static function load(string $key, bool $public): ?OpenSSLAsymmetricKey
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }
        if (!str_contains($key, '-----BEGIN')) {
            $body = chunk_split(preg_replace('/\s+/', '', $key) ?? '', 64, "\n");
            $key = $public
                ? "-----BEGIN PUBLIC KEY-----\n{$body}-----END PUBLIC KEY-----"
                : "-----BEGIN PRIVATE KEY-----\n{$body}-----END PRIVATE KEY-----";
        }
        $loaded = $public ? openssl_pkey_get_public($key) : openssl_pkey_get_private($key);
        if ($loaded === false && !$public && !str_contains(trim($key), 'RSA PRIVATE KEY') && str_contains($key, 'BEGIN PRIVATE KEY')) {
            // 支付宝工具生成的 PKCS#1 裸私钥。
            $pkcs1 = str_replace(['BEGIN PRIVATE KEY', 'END PRIVATE KEY'], ['BEGIN RSA PRIVATE KEY', 'END RSA PRIVATE KEY'], $key);
            $loaded = openssl_pkey_get_private($pkcs1);
        }
        return $loaded === false ? null : $loaded;
    }
}
