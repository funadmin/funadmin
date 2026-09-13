<?php

declare(strict_types=1);

namespace app\common\service\identity;

/** 可安全展示的身份管理输入校验失败；保留原参数异常继承关系。 */
final class IdentityValidationException extends \InvalidArgumentException
{
}
