<?php

declare(strict_types=1);

namespace app\admin\plugin;

use RuntimeException;

/**
 * 插件生命周期前置条件违规（未禁用、重复安装、版本不升反降等）。
 * 此类错误发生在任何状态变更之前，不得将插件生命周期标记为 failed。
 */
final class PluginPreconditionException extends RuntimeException
{
}
