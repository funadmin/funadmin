<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 激活清单缺失或损坏。 */
final class ActivationUnavailableException extends RuntimeException
{
}
