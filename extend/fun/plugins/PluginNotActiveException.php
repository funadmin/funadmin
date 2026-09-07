<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 插件存在但当前不允许进入指定应用。 */
final class PluginNotActiveException extends RuntimeException
{
}
