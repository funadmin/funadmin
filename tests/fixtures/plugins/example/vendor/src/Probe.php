<?php

namespace plugins\example\vendor;

/** 供行为探针验证插件 vendor 自动加载链已挂载。 */
class Probe
{
    public static function loaded(): bool
    {
        return defined('EXAMPLE_PLUGIN_VENDOR_LOADED');
    }
}
