<?php

declare(strict_types=1);

namespace app\market\service\payment;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use RuntimeException;
use think\Request;
use think\Response;

/**
 * 支付宝电脑网站支付（alipay.trade.page.pay），RSA2 签名，公钥模式验签。
 */
final class AlipayChannel implements PaymentChannel
{
    private const GATEWAY = 'https://openapi.alipay.com/gateway.do';
    private const SANDBOX_GATEWAY = 'https://openapi-sandbox.dl.alipaydev.com/gateway.do';

    public function __construct(private readonly array $config, private readonly ?ClientInterface $http = null)
    {
    }

    public static function configured(array $config): bool
    {
        return trim((string) $config['app_id']) !== '' && trim((string) $config['private_key']) !== '' && trim((string) $config['alipay_public_key']) !== '';
    }

    public function create(array $order, string $returnUrl, string $notifyUrl): array
    {
        $params = $this->commonParams('alipay.trade.page.pay') + [
            'return_url' => $returnUrl,
            'notify_url' => $notifyUrl,
            'biz_content' => json_encode([
                'out_trade_no' => $order['order_no'],
                'product_code' => 'FAST_INSTANT_TRADE_PAY',
                'total_amount' => self::yuan((int) $order['amount']),
                'subject' => mb_substr((string) $order['subject'], 0, 128),
                'time_expire' => date('Y-m-d H:i:s', strtotime((string) $order['expire_at'])),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
        $params['sign'] = $this->sign($params);
        return ['type' => 'redirect', 'url' => $this->gateway() . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986)];
    }

    public function parseNotify(Request $request): ?PaymentResult
    {
        // 验签必须基于原始参数值，不能经过框架的全局输入过滤。
        parse_str((string) $request->getInput(), $params);
        if (!$this->verifyParams($params)) {
            return null;
        }
        if (($params['app_id'] ?? '') !== $this->config['app_id']) {
            return null;
        }
        return $this->result($params);
    }

    public function notifyAck(bool $success, string $message = ''): Response
    {
        return response($success ? 'success' : 'fail', 200, ['Content-Type' => 'text/plain']);
    }

    public function query(array $order): ?PaymentResult
    {
        $params = $this->commonParams('alipay.trade.query') + [
            'biz_content' => json_encode(['out_trade_no' => $order['order_no']], JSON_UNESCAPED_SLASHES),
        ];
        $params['sign'] = $this->sign($params);
        $body = (string) $this->http()->request('POST', $this->gateway(), [
            'form_params' => $params,
            'timeout' => 15,
            'connect_timeout' => 5,
            'http_errors' => false,
        ])->getBody();
        if (!preg_match('/"alipay_trade_query_response"\s*:\s*(\{.*?\})\s*,\s*"sign"\s*:\s*"([^"]+)"/s', $body, $matches)) {
            return null;
        }
        if (!$this->verifyString($matches[1], $matches[2])) {
            throw new RuntimeException('支付宝查询响应验签失败');
        }
        $response = json_decode($matches[1], true);
        if (!is_array($response) || ($response['code'] ?? '') !== '10000') {
            return null;
        }
        return $this->result($response);
    }

    /** 与支付宝通知/同步返回相同的待签名串：去掉 sign、sign_type 与空值，按键名升序以 & 连接。 */
    public static function signContent(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null && !is_array($value));
        ksort($params, SORT_STRING);
        $pairs = [];
        foreach ($params as $key => $value) {
            $pairs[] = $key . '=' . $value;
        }
        return implode('&', $pairs);
    }

    public function verifyParams(array $params): bool
    {
        $signature = (string) ($params['sign'] ?? '');
        return $signature !== '' && ($params['sign_type'] ?? 'RSA2') === 'RSA2' && $this->verifyString(self::signContent($params), $signature);
    }

    private function result(array $data): PaymentResult
    {
        $status = (string) ($data['trade_status'] ?? '');
        return new PaymentResult(
            (string) ($data['out_trade_no'] ?? ''),
            (string) ($data['trade_no'] ?? ''),
            self::cents((string) ($data['total_amount'] ?? '0')),
            in_array($status, ['TRADE_SUCCESS', 'TRADE_FINISHED'], true),
            $data
        );
    }

    private function commonParams(string $method): array
    {
        return [
            'app_id' => (string) $this->config['app_id'],
            'method' => $method,
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0',
        ];
    }

    private function sign(array $params): string
    {
        $key = PemKey::load((string) $this->config['private_key'], false) ?? throw new RuntimeException('支付宝应用私钥无效');
        if (!openssl_sign(self::signContent($params), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('支付宝请求签名失败');
        }
        return base64_encode($signature);
    }

    private function verifyString(string $content, string $signature): bool
    {
        $key = PemKey::load((string) $this->config['alipay_public_key'], true);
        $raw = base64_decode($signature, true);
        return $key !== null && $raw !== false && openssl_verify($content, $raw, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private function gateway(): string
    {
        return $this->config['sandbox'] ? self::SANDBOX_GATEWAY : self::GATEWAY;
    }

    private function http(): ClientInterface
    {
        return $this->http ?? new Client();
    }

    public static function yuan(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function cents(string $yuan): int
    {
        if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', trim($yuan), $matches)) {
            return -1;
        }
        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0');
    }
}
