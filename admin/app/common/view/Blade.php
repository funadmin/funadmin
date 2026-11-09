<?php

declare(strict_types=1);

namespace app\common\view;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use Webman\View;

/**
 * Blade 视图处理器（config/view.php 的 handler）。
 *
 * 模板定位规则与框架自带的 support\view\Blade 一致：
 * - 以 / 开头：按项目相对路径定位，路径中的 /view/ 之前为视图目录，例如 /app/document/view/public/reader；
 * - 否则单应用在 app/view/ 下查找，多应用在 app/<应用>/view/ 下查找，插件在 plugin/<插件>/app/... 下查找。
 * 模板文件后缀为 .blade.php，编译结果缓存在 runtime/views。
 *
 * 框架自带的处理器依赖 jenssegers/blade（webman/blade 尚不支持 illuminate 13），这里直接基于 illuminate/view 实现。
 * 扩展：config/view.php 的 extension 回调接收 BladeCompiler 与 Factory，可注册指令（directive）与组件（component）。
 */
final class Blade implements View
{
    /** @var array<string, Factory> 视图目录 → 视图工厂（每个 worker 复用） */
    private static array $factories = [];

    public static function assign(string|array $name, mixed $value = null): void
    {
        $request = request();
        if ($request === null) return;
        $request->_view_vars = array_merge((array) ($request->_view_vars ?? []), is_array($name) ? $name : [$name => $value]);
    }

    public static function render(string $template, array $vars, ?string $app = null, ?string $plugin = null): string
    {
        $request = request();
        $plugin ??= (string) ($request->plugin ?? '');
        $app ??= (string) ($request->app ?? '');
        $configPrefix = $plugin !== '' ? "plugin.$plugin." : '';
        $baseViewPath = $plugin !== '' ? base_path() . "/plugin/$plugin/app" : app_path();
        if ($template !== '' && $template[0] === '/') {
            if (str_contains($template, '/view/')) {
                [$viewPath, $template] = explode('/view/', $template, 2);
                $viewPath = base_path(ltrim($viewPath, '/') . '/view');
            } else {
                $viewPath = base_path();
                $template = ltrim($template, '/');
            }
        } else {
            $viewPath = $app === '' ? "$baseViewPath/view" : "$baseViewPath/$app/view";
        }
        $factory = self::$factories[$viewPath] ??= self::factory($viewPath, $configPrefix);
        if ($request !== null && isset($request->_view_vars)) {
            $vars = array_merge((array) $request->_view_vars, $vars);
        }
        return $factory->make($template, $vars)->render();
    }

    private static function factory(string $viewPath, string $configPrefix): Factory
    {
        $files = new Filesystem();
        $cachePath = runtime_path('views');
        if (!is_dir($cachePath) && !mkdir($cachePath, 0755, true) && !is_dir($cachePath)) {
            throw new \RuntimeException('无法创建视图缓存目录：' . $cachePath);
        }
        $compiler = new BladeCompiler($files, $cachePath);
        $resolver = new EngineResolver();
        $resolver->register('blade', static fn (): CompilerEngine => new CompilerEngine($compiler, $files));
        $resolver->register('php', static fn (): PhpEngine => new PhpEngine($files));
        $container = new Container();
        $factory = new Factory($resolver, new FileViewFinder($files, [$viewPath]), new Dispatcher($container));
        $factory->setContainer($container);
        $extension = config("{$configPrefix}view.extension");
        if (is_callable($extension)) {
            $extension($compiler, $factory);
        }
        return $factory;
    }
}
