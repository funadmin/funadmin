<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\common\service;

use support\Container;
use support\Cache;

/**
 * 自定义服务基类
 * Class Service
 * @package think\admin
 */
abstract class AbstractService
{
    /**
     * 应用实例
     * @var App
     */

    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct()
    {
        $this->initialize();
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        return $this;
    }

    /**
     * 静态实例对象
     * @param array $args
     * @return static
     */
    public static function instance(...$args)
    {
        return $args === [] ? Container::get(static::class) : Container::make(static::class, $args);
    }

    /**
     * 清理应用级全局缓存，供确有全局失效需求的服务复用。
     */
    protected function clearApplicationCache(): void
    {
        Cache::clear();
    }
}