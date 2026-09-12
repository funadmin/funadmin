<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** 插件存在但当前不允许进入指定应用。 */
final class PluginNotActiveException extends RuntimeException
{
}
