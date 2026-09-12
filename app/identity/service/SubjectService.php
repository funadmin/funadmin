<?php

declare(strict_types=1);

namespace app\identity\service;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/** 生成 OIDC 对外 subject，避免直接暴露内部自增 ID 或跨服务关联标识。 */
final class SubjectService
{
    public function __construct(private readonly ?string $pepper = null)
    {
    }

    public function publicSubject(string $tenantPublicId, string $userPublicId): string
    {
        $this->assertPublicIds($tenantPublicId, $userPublicId);
        return $this->uuidSubject('public:' . strtolower($tenantPublicId) . ':' . strtolower($userPublicId));
    }

    public function pairwiseSubject(string $tenantPublicId, string $sector, string $userPublicId): string
    {
        $this->assertPublicIds($tenantPublicId, $userPublicId);
        $sector = trim($sector);
        if ($sector === '' || strlen($sector) > 253) throw new InvalidArgumentException('OIDC sector 无效');
        return $this->uuidSubject('pairwise:' . strtolower($tenantPublicId) . ':' . $sector . ':' . strtolower($userPublicId));
    }

    private function uuidSubject(string $material): string
    {
        $pepper = $this->pepper ?? (function_exists('config') ? (string) config('oauth.subject_pepper', '') : '');
        if ($pepper === '') throw new RuntimeException('OIDC subject pepper 未配置');
        $hex = bin2hex(substr(hash_hmac('sha256', $material, $pepper, true), 0, 16));
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    private function assertPublicIds(string $tenantPublicId, string $userPublicId): void
    {
        if (!Uuid::isValid($tenantPublicId) || !Uuid::isValid($userPublicId)) throw new InvalidArgumentException('OIDC public_id 必须为 UUID');
    }
}
