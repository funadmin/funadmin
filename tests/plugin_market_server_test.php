<?php

declare(strict_types=1);

// 插件市场服务端（plugins/market）与插件中心客户端的协议一致性：签名载荷逐字节相同、版本约束判定相同。
require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
foreach (['MarketProtocol', 'VersionConstraint', 'MarketSettings', 'MarketSigner', 'payment/PaymentChannel', 'payment/PaymentResult', 'payment/PemKey', 'payment/AlipayChannel', 'payment/WechatChannel'] as $class) {
    require_once $root . '/plugins/market/app/market/service/' . $class . '.php';
}

use app\common\plugin\marketplace\dto\DownloadDescriptorDto;
use app\common\plugin\marketplace\dto\MarketplaceProtocol;
use app\common\plugin\package\PluginPackageDownloader;
use app\common\plugin\sdk\DependencyValidator;
use app\market\service\MarketProtocol;
use app\market\service\MarketSigner;
use app\market\service\VersionConstraint;
use app\market\service\payment\AlipayChannel;
use app\market\service\payment\WechatChannel;

function marketExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

marketExpect(MarketProtocol::MANIFEST_SCHEMA === MarketplaceProtocol::MANIFEST_SCHEMA, 'manifest schema 常量必须与客户端一致');
marketExpect(MarketProtocol::PACKAGE_FORMAT === MarketplaceProtocol::PACKAGE_FORMAT, 'package format 常量必须与客户端一致');
marketExpect(MarketProtocol::SIGNATURE_ALGORITHM === MarketplaceProtocol::SIGNATURE_ALGORITHM, '签名算法常量必须与客户端一致');

$meta = [
    'code' => 'demo',
    'code_version' => '1.2.3-beta.1',
    'database_capability' => '002_create_items',
    'sha256' => str_repeat('a', 64),
    'size' => 18081,
    'tree_hash' => str_repeat('b', 64),
];
$descriptor = new DownloadDescriptorDto(
    'https://203.0.113.10/packages/demo.zip',
    $meta['code'],
    $meta['code_version'],
    $meta['sha256'],
    'c2lnbmF0dXJl',
    'ed25519',
    $meta['size'],
    MarketplaceProtocol::MANIFEST_SCHEMA,
    MarketplaceProtocol::PACKAGE_FORMAT,
    $meta['tree_hash'],
    $meta['database_capability']
);
marketExpect(MarketSigner::payload($meta) === PluginPackageDownloader::signaturePayload($descriptor), '市场签名载荷必须与客户端验签载荷逐字节一致');

if (function_exists('sodium_crypto_sign_keypair')) {
    $keypair = sodium_crypto_sign_keypair();
    $signature = sodium_crypto_sign_detached(MarketSigner::payload($meta), sodium_crypto_sign_secretkey($keypair));
    marketExpect(
        sodium_crypto_sign_verify_detached($signature, PluginPackageDownloader::signaturePayload($descriptor), sodium_crypto_sign_publickey($keypair)),
        '市场签名必须能被客户端载荷验证'
    );
}

$validator = new ReflectionMethod(DependencyValidator::class, 'matches');
$client = new DependencyValidator('8.0', PHP_VERSION);
$cases = [
    ['8.0', '>=1.0.0'], ['8.0', '>=8.0.0'], ['8.0', '>=8.0'], ['8.0', '^8.0'], ['8.0', '^7.2'],
    ['8.1.3', '>=8.1 <9.0'], ['8.1.3', '>=8.1,<8.1.3'], ['8.5.10', '>=8.1'], ['7.4.33', '>=8.1'], ['1.0.0', '=1.0.0'], ['1.0.0', ''],
];
foreach ($cases as [$version, $constraint]) {
    marketExpect(
        VersionConstraint::matches($version, $constraint) === $validator->invoke($client, $version, $constraint),
        "版本约束判定必须与客户端 DependencyValidator 一致：{$version} {$constraint}"
    );
}

// 支付宝：待签名串去掉 sign/sign_type 与空值并按键名排序；金额分与元互转无浮点误差。
marketExpect(AlipayChannel::signContent(['b' => '2', 'sign' => 'x', 'a' => '1', 'sign_type' => 'RSA2', 'c' => '', 'd' => '&<']) === 'a=1&b=2&d=&<', '支付宝待签名串规则错误');
marketExpect(AlipayChannel::yuan(5990) === '59.90' && AlipayChannel::yuan(5) === '0.05' && AlipayChannel::cents('59.9') === 5990 && AlipayChannel::cents('199') === 19900 && AlipayChannel::cents('1e3') === -1, '支付宝金额换算错误');

$rsa = static fn () => openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$platform = $rsa();
$platformPublic = openssl_pkey_get_details($platform)['key'];
openssl_pkey_export($rsa(), $merchantPem);
$alipay = new AlipayChannel(['app_id' => 'app', 'private_key' => $merchantPem, 'alipay_public_key' => $platformPublic, 'sandbox' => true]);
$notify = ['out_trade_no' => '2026092500000000000001', 'trade_status' => 'TRADE_SUCCESS', 'total_amount' => '59.90', 'subject' => 'a&b'];
openssl_sign(AlipayChannel::signContent($notify), $signature, $platform, OPENSSL_ALGO_SHA256);
marketExpect($alipay->verifyParams($notify + ['sign' => base64_encode($signature), 'sign_type' => 'RSA2']), '支付宝通知验签应通过');
marketExpect(!$alipay->verifyParams(['total_amount' => '0.01'] + $notify + ['sign' => base64_encode($signature)]), '篡改金额的支付宝通知必须验签失败');

// 微信支付：应答/回调验签（公钥 ID、时间戳防重放）与 AES-256-GCM 资源解密。
$apiV3 = str_repeat('k', 32);
$wechat = new WechatChannel(['mch_id' => '1900000109', 'app_id' => 'wx1', 'serial_no' => 's', 'private_key' => $merchantPem, 'api_v3_key' => $apiV3, 'platform_public_key_id' => 'PUB_KEY_ID_1', 'platform_public_key' => $platformPublic]);
$body = '{"id":"1"}';
$now = (string) time();
openssl_sign($now . "\nnonce\n" . $body . "\n", $wxSignature, $platform, OPENSSL_ALGO_SHA256);
marketExpect($wechat->verifySignature($now, 'nonce', $body, base64_encode($wxSignature), 'PUB_KEY_ID_1', true), '微信回调验签应通过');
marketExpect(!$wechat->verifySignature($now, 'nonce', $body, base64_encode($wxSignature), 'PUB_KEY_ID_2', true), '未知微信支付公钥 ID 必须拒绝');
$old = (string) (time() - 600);
openssl_sign($old . "\nnonce\n" . $body . "\n", $oldSignature, $platform, OPENSSL_ALGO_SHA256);
marketExpect(!$wechat->verifySignature($old, 'nonce', $body, base64_encode($oldSignature), 'PUB_KEY_ID_1', true), '过期的微信回调必须拒绝（防重放）');
$cipher = openssl_encrypt('{"out_trade_no":"1","trade_state":"SUCCESS"}', 'aes-256-gcm', $apiV3, OPENSSL_RAW_DATA, 'nonce0123456', $tag, 'transaction');
$resource = ['algorithm' => 'AEAD_AES_256_GCM', 'ciphertext' => base64_encode($cipher . $tag), 'associated_data' => 'transaction', 'nonce' => 'nonce0123456'];
marketExpect(($wechat->decryptResource($resource)['trade_state'] ?? '') === 'SUCCESS', '微信回调资源应能解密');
marketExpect($wechat->decryptResource(['ciphertext' => base64_encode(strrev($cipher) . $tag)] + $resource) === null, '被篡改的微信回调密文必须解密失败');

echo "plugin market server protocol ok\n";
