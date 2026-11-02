<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
declare(strict_types=1);

namespace app\common\plugin\sdk;

use Psr\Container\ContainerInterface;
use RuntimeException;
use support\Container;
use support\Request;
use Webman\View;

abstract class Plugin
{
    // app 容器
    protected ContainerInterface $app;
    // 请求对象
    protected ?Request $request {
        get => request();
    }
    // 当前插件标识
    protected string $code;
    // 模板布局
    protected bool $layout = false;
    // 插件路径
    protected string $plugin_path;
    // 视图模型
    protected string $view;
    protected array $viewVariables = [];
    // 插件配置
    protected string $plugin_config;

    protected array $info;
    // 插件信息
    protected string $plugin_info;

    /**
     * 插件构造函数
     * Plugin constructor.
     * @param \think\App $app
     */
    public function __construct()
    {
        $this->app = Container::instance();
        $this->code = $this->getCode();
        $entryFile = (new \ReflectionClass($this))->getFileName();
        if ($entryFile === false) {
            throw new RuntimeException('插件入口缺少物理文件');
        }
        $this->plugin_path = dirname($entryFile) . DIRECTORY_SEPARATOR;
        if (basename(dirname($entryFile)) !== $this->code) {
            throw new RuntimeException('插件入口目录与标识不一致');
        }
        $this->plugin_config = "plugin_{$this->code}_config";
        $this->plugin_info = "plugin_{$this->code}_info";
        $this->info = $this->getInfo();
        $this->view = (string) config('view.handler', \support\view\Raw::class);
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize(): void
    {
    }

    /**
     * 获取插件标识
     * @return mixed|null
     */
    final protected function getCode(): string
    {
        $class = get_class($this);
        if (preg_match('/^plugins\\\\([a-z][a-z0-9]*)\\\\Plugin$/D', $class, $match) !== 1) {
            throw new RuntimeException('插件入口命名空间无效');
        }
        return $match[1];
    }

    /**
     * 加载模板输出
     * @param string $template
     * @param array $vars           模板文件名
     * @return void
     * @throws \think\Exception
     */
    protected function fetch(string $template = '', array $vars = []): string
    {
        if ($this->request === null) {
            throw new RuntimeException('插件模板渲染需要 HTTP 请求上下文');
        }
        if (preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$~D', $template) !== 1) {
            throw new RuntimeException('插件模板路径无效');
        }
        $base = realpath(base_path());
        $directory = realpath($this->plugin_path . 'view');
        if ($base === false || $directory === false || !str_starts_with($directory, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('插件视图必须位于当前项目内');
        }
        $suffix = (string) config('view.options.view_suffix', 'html');
        $file = realpath($directory . '/' . $template . '.' . $suffix);
        if ($file === false || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('插件模板不存在或越界');
        }
        return ($this->view)::render(substr($directory, strlen($base)) . '/' . $template, array_merge($this->viewVariables, $vars), '', '');
    }

    /**
     * 渲染内容输出
     * @access protected
     * @param  string $content 模板内容
     * @param  array  $vars    模板输出变量
     * @return void
     */
    protected function display(string $content = '', array $vars = []): string
    {
        $replacements = [];
        foreach (array_merge($this->viewVariables, $vars) as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements['{{' . $name . '}}'] = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }
        return strtr($content, $replacements);
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
        $this->viewVariables = array_merge($this->viewVariables, is_array($name) ? $name : [(string) $name => $value]);

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
        $handler = is_array($engine) ? ($engine['handler'] ?? '') : $engine;
        if (!is_string($handler) || !is_subclass_of($handler, View::class)) {
            throw new RuntimeException('插件视图引擎必须实现 Webman View');
        }
        $this->view = $handler;

        return $this;
    }

    /**
     * 插件基础信息
     * @return array
     */
    final public function getInfo(): array
    {
        return Manifest::fromDirectory($this->plugin_path)->toArray();
    }

    /**
     * 获取配置信息
     * @param bool $type 是否获取完整配置
     * @return array|mixed
     */
    final public function getConfig(bool $type = false): array
    {
        $config = [];
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
