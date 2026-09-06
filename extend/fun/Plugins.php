<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
declare(strict_types=1);

namespace fun;

use think\App;
use think\facade\View;
use think\Request;
use think\view\driver\Think as ThinkViewDriver;

abstract class Plugins  {
    // app 容器
    protected App $app;
    // 请求对象
    protected Request $request;
    // 当前插件标识
    protected string $code;
    // 模板布局
    protected bool $layout = false;
    // 插件路径
    protected string $plugin_path;
    // 视图模型
    protected ThinkViewDriver $view;
    // 插件配置
    protected string $plugin_config;

    protected array $info;
    // 插件信息
    protected string $plugin_info;

    /**
     * 插件构造函数
     * Plugins constructor.
     * @param \think\App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->code = $this->getCode();
        $this->plugin_path = $app->plugins->getPluginsPath() . $this->code . DS;
        $this->plugin_config = "plugin_{$this->code}_config";
        $this->plugin_info = "plugin_{$this->code}_info";
        $this->info = $this->getInfo();
        $this->view = clone View::engine('Think');
        app()->view->engine()->layout($this->layout);
        $this->view->config([
            'view_path' => $this->plugin_path . 'view' . DS
        ]);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize(): void
    {}

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getCode(): string
    {
        $class = get_class($this);
        list(, $code, ) = explode('\\', $class);
        $this->request->plugin = $code;

        return $code;
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return void
     * @throws \think\Exception
     */
    protected function fetch(string $template = '', array $vars = []): void
    {
        $this->view->fetch($template, $vars);
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @return void
     */
    protected function display(string $content = '', array $vars = []): void
    {
        $this->view->display($content, $vars);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign(mixed $name, mixed $value = ''): static
    {
        $this->view->assign([$name => $value]);

        return $this;
    }

    /**
     * 初始化模板引擎
     * @access protected
     * @param  array|string $engine 引擎参数
     * @return $this
     */
    protected function engine(array|string $engine): static
    {
        $this->view->engine($engine);

        return $this;
    }

    /**
     * 插件基础信息
     * @return array
     */
    final public function getInfo(): array
    {
        $info = config($this->plugin_info, []);
        if ($info) {
            return $info;
        }

        $manifest = \fun\plugins\Manifest::fromDirectory($this->plugin_path);
        $info = $manifest->toArray();
        config($info, $this->plugin_info);
        return $info;
    }

    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return array|mixed
     */
    final public function getConfig(bool $type = false): array
    {
        $config = config($this->plugin_config, []);
        if ($config) {
            return $config;
        }
        $config_file = $this->plugin_path . 'config.php';
        if (is_file($config_file)) {
            $temp_arr = (array)include $config_file;
            if ($type) {
                return $temp_arr;
            }
            foreach ($temp_arr as $key => $value) {
                $config[$key] = $value['value'];
            }
        }
        config($config, $this->plugin_config);
        return $config;
    }
    /**
     * 设置插件信息数据
     * @param $name
     * @param array $value
     * @return array
     */
    final public function setInfo(string $name = '', array $value = []): never
    {
        throw new \RuntimeException('plugin.json 是只读契约，运行状态必须写入插件注册表');
    }

    /**
     * 更新代码前钩子，返回 false 可中止更新。
     */
    public function beforeUpdate(string $fromVersion, string $toVersion, bool $migrate): bool
    {
        return true;
    }

    /**
     * 更新完成后钩子，插件更新后仍保持禁用。
     */
    public function afterUpdate(string $fromVersion, string $toVersion, bool $migrate): bool
    {
        return true;
    }

    /**
     * 配置持久化后的回调。
     */
    public function configChanged(array $config): bool
    {
        return true;
    }

    /**
     * 显式清理业务数据；默认拒绝，避免卸载时误删数据。
     */
    public function purgeData(): bool
    {
        return false;
    }

    //必须实现安装
    abstract public function install(): bool;

    //必须卸载插件方法
    abstract public function uninstall(): bool;
    //必须实现安装
    abstract public function enabled(): bool;
    //必须卸载插件方法
    abstract public function disabled(): bool;
}
