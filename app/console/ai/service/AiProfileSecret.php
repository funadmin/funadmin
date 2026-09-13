<?php

declare(strict_types=1);
namespace app\console\ai\service;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use RuntimeException;
use Throwable;

/** 复用 OAuth 的 Defuse 依赖和主密钥入口；禁止口令降级及自动生成。 */
final class AiProfileSecret
{
    public function __construct(private readonly string $masterKey) {}

    private function key(): Key
    {
        try { return Key::loadFromAsciiSafeString($this->masterKey); }
        catch (Throwable) { throw new RuntimeException('未配置有效的加密主密钥', 503); }
    }

    public function seal(string $secret, int $adminId): string
    {
        return Crypto::encrypt(json_encode(['purpose'=>'ai-profile', 'admin_id'=>$adminId, 'secret'=>$secret], JSON_THROW_ON_ERROR), $this->key());
    }

    public function open(string $cipher, int $adminId): string
    {
        $key = $this->key();
        try {
            $data = json_decode(Crypto::decrypt($cipher, $key), true, 512, JSON_THROW_ON_ERROR);
            if (($data['purpose'] ?? '') !== 'ai-profile' || ($data['admin_id'] ?? null) !== $adminId || !is_string($data['secret'] ?? null)) throw new RuntimeException();
            return $data['secret'];
        } catch (Throwable) { throw new RuntimeException('档案密钥不可用', 503); }
    }
}
