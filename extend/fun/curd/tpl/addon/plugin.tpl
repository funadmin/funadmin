<?php

namespace {%addon_dir%}\{%addon%};

use fun\Addons;

/**
 * 插件
 */
class Plugin extends Addons
{


    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {
        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件启用方法
     * @return bool
     */
    public function enabled()
    {
        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disabled()
    {
        return true;
    }

    /**
     * 更新代码前钩子；返回 false 可中止更新。
     */
    public function beforeUpdate(string $fromVersion, string $toVersion, bool $migrate): bool
    {
        return true;
    }

    /**
     * 更新完成后的钩子；返回 false 时插件保持禁用并记录错误。
     */
    public function afterUpdate(string $fromVersion, string $toVersion, bool $migrate): bool
    {
        return true;
    }

    /**
     * 配置保存后的回调。
     */
    public function configChanged(array $config): bool
    {
        return true;
    }

    /**
     * 仅在卸载请求明确传入 purge_data=true 时调用。
     * 默认不清除业务数据，插件如需清理必须显式实现。
     */
    public function purgeData(): bool
    {
        return false;
    }
}
