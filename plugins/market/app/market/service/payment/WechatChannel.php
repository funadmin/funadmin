<?php

declare(strict_types=1);

namespace app\market\service\payment;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use think\Request;
use think\Response;

/**
 * 微信支付 Native 扫码（APIv3）。请求用商户私钥签名，应答与回调用「微信支付公钥」验签，回调资源 AES-256-GCM 解密。
 */
final class WechatChannel implements PaymentChannel
{
    private const BASE = 'https://api.mch.weixin.qq.com';

    public function __construct(private readonly array $config, private readonly ?ClientInterface $http = null)
    {
    }

    public static function configured(array $config): bool
    {
        foreach (['mch_id', 'app_id', 'serial_no', 'private_key', 'api_v3_key', 'platform_public_key_id', 'platform_public_key'] as $field) {
            if (trim((string) $config[$field]) === '') {
                return false;
            }
        }
        return true;
    }

    public function create(array $order, string $returnUrl, string $notifyUrl): array
    {
        $body = json_encode([
            'appid' => $this->config['app_id'],
            'mchid' => $this->config['mch_id'],
            'description' => mb_substr((string) $order['subject'], 0, 127),
            'out_trade_no' => $order['order_no'],
            'time_expire' => date(DATE_RFC3339, strtotime((string) $order['expire_at'])),
            'notify_url' => $notifyUrl,
            'amount' => ['total' => (int) $order['amount'], 'currency' => 'CNY'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $data = $this->request('POST', '/v3/pay/transactions/native', $body);
        $codeUrl = (string) ($data['code_url'] ?? '');
        if ($codeUrl === '') {
            throw new RuntimeException('微信支付下单失败：' . (string) ($data['message'] ?? '未返回二维码链接'));
        }
        return ['type' => 'qrcode', 'code_url' => $codeUrl];
    }

    public function parseNotify(Request $request): ?PaymentResult
    {
        $body = (string) $request->getInput();
        if (!$this->verifySignature(
            (string) $request->header('wechatpay-timestamp', ''),
            (string) $request->header('wechatpay-nonce', ''),
            $body,
            (string) $request->header('wechatpay-signature', ''),
            (string) $request->header('wechatpay-serial', ''),
            true
        )) {
            return null;
        }
        $notify = json_decode($body, true);
        if (!is_array($notify) || ($notify['event_type'] ?? '') !== 'TRANSACTION.SUCCESS' || !is_array($notify['resource'] ?? null)) {
            return null;
        }
        $transaction = $this->decryptResource($notify['resource']);
        if ($transaction === null || ($transaction['mchid'] ?? '') !== $this->config['mch_id'] || ($transaction['appid'] ?? '') !== $this->config['app_id']) {
            return null;
        }
        return $this->result($transaction);
    }

    public function notifyAck(bool $success, string $message = ''): Response
    {
        return $success
            ? response('', 204)
            : json(['code' => 'FAIL', 'message' => $message ?: '处理失败'], 500);
    }

    public function query(array $order): ?PaymentResult
    {
        $path = '/v3/pay/transactions/out-trade-no/' . rawurlencode((string) $order['order_no']) . '?mchid=' . rawurlencode((string) $this->config['mch_id']);
        $data = $this->request('GET', $path, '');
        return isset($data['out_trade_no']) ? $this->result($data) : null;
    }

    /** 应答/回调验签：Wechatpay-Serial 必须是配置的微信支付公钥 ID，回调时间戳 5 分钟内有效。 */
    public function verifySignature(string $timestamp, string $nonce, string $body, string $signature, string $serial, bool $checkFreshness): bool
    {
        if ($timestamp === '' || $nonce === '' || $signature === '' || !hash_equals((string) $this->config['platform_public_key_id'], $serial)) {
            return false;
        }
        if ($checkFreshness && abs(time() - (int) $timestamp) > 300) {
            return false;
        }
        $key = PemKey::load((string) $this->config['platform_public_key'], true);
        $raw = base64_decode($signature, true);
        return $key !== null && $raw !== false
            && openssl_verify($timestamp . "\n" . $nonce . "\n" . $body . "\n", $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    public function decryptResource(array $resource): ?array
    {
        if (($resource['algorithm'] ?? '') !== 'AEAD_AES_256_GCM') {
            return null;
        }
        $cipher = base64_decode((string) ($resource['ciphertext'] ?? ''), true);
        if ($cipher === false || strlen($cipher) <= 16) {
            return null;
        }
        $plain = openssl_decrypt(
            substr($cipher, 0, -16),
            'aes-256-gcm',
            (string) $this->config['api_v3_key'],
            OPENSSL_RAW_DATA,
            (string) ($resource['nonce'] ?? ''),
            substr($cipher, -16),
            (string) ($resource['associated_data'] ?? '')
        );
        $data = $plain === false ? null : json_decode($plain, true);
        return is_array($data) ? $data : null;
    }

    private function result(array $transaction): PaymentResult
    {
        return new PaymentResult(
            (string) ($transaction['out_trade_no'] ?? ''),
            (string) ($transaction['transaction_id'] ?? ''),
            (int) ($transaction['amount']['total'] ?? -1),
            ($transaction['trade_state'] ?? '') === 'SUCCESS',
            $transaction
        );
    }

    private function request(string $method, string $path, string $body): array
    {
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $key = PemKey::load((string) $this->config['private_key'], false) ?? throw new RuntimeException('微信支付商户私钥无效');
        if (!openssl_sign($method . "\n" . $path . "\n" . $timestamp . "\n" . $nonce . "\n" . $body . "\n", $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('微信支付请求签名失败');
        }
        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%s",serial_no="%s"',
            $this->config['mch_id'], $nonce, base64_encode($signature), $timestamp, $this->config['serial_no']
        );
        $response = $this->http()->request($method, self::BASE . $path, [
            'headers' => ['Authorization' => $authorization, 'Accept' => 'application/json', 'Content-Type' => 'application/json', 'User-Agent' => 'FunAdmin-Market'],
            'body' => $body === '' ? null : $body,
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
        $content = (string) $response->getBody();
        $status = $response->getStatusCode();
        if ($status >= 200 && $status < 300 && !$this->verifySignature(
            $response->getHeaderLine('Wechatpay-Timestamp'),
            $response->getHeaderLine('Wechatpay-Nonce'),
            $content,
            $response->getHeaderLine('Wechatpay-Signature'),
            $response->getHeaderLine('Wechatpay-Serial'),
            false
        )) {
            throw new RuntimeException('微信支付应答验签失败，请检查微信支付公钥配置');
        }
        $data = json_decode($content, true);
        if ($status === 404 && $method === 'GET') {
            return [];
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('微信支付请求失败：' . (is_array($data) ? (string) ($data['message'] ?? $status) : $status));
        }
        return is_array($data) ? $data : [];
    }

    private function http(): ClientInterface
    {
        return $this->http ?? new Client();
    }
}
