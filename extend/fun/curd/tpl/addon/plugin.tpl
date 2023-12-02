<?php

namespace addons\{{$addon}};

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
     * 实现初始化钩子方法
     * @return mixed
     */
    public function AddonsInit($param)
    {

    }

    /**
     * 实现化钩子方法
     * @return mixed
     */
    public function demoHook($param)
    {
        return true;
    }

}
