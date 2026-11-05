<?php

declare(strict_types=1);

namespace app\common\service\identity;

/** 明确的身份资源状态，保持领域异常兼容性。 */
final class IdentityResourceException extends \DomainException
{
    public function __construct(string $message, int $code)
    {
        if (!in_array($code, [403, 404, 409], true)) {
            throw new \InvalidArgumentException('身份资源异常状态无效');
        }
        parent::__construct($message, $code);
    }
}
