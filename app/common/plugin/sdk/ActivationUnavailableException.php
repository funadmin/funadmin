<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** 激活清单缺失或损坏。 */
final class ActivationUnavailableException extends RuntimeException
{
}
