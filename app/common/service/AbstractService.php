<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */

namespace app\common\service;

use think\App;
use think\Container;

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
    protected $app;

    /**
     * Service constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
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
        return Container::getInstance()->make(static::class, $args);
    }
}