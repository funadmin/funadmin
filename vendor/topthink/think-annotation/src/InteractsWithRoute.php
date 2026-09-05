<?php

namespace think\annotation;

use ReflectionClass;
use ReflectionMethod;
use think\annotation\route\Group;
use think\annotation\route\Middleware;
use think\annotation\route\Model;
use think\annotation\route\Pattern;
use think\annotation\route\Resource;
use think\annotation\route\Route;
use think\annotation\route\Validate;
use think\App;
use think\event\RouteLoaded;
use think\helper\Arr;
use think\helper\Str;

/**
 * Trait InteractsWithRoute
 * @package think\annotation\traits
 * @property App $app
 */
trait InteractsWithRoute
{
    /**
     * @var \think\Route
     */
    protected $route;

    protected $parsedClass = [];

    protected $controllerDir;

    protected $controllerSuffix;

    protected function registerAnnotationRoute()
    {
        if ($this->app->config->get('annotation.route.enable', true)) {
            $this->app->event->listen(RouteLoaded::class, function () {

                $this->route            = $this->app->route;
                $this->controllerDir    = realpath($this->app->getAppPath() . $this->app->config->get('route.controller_layer'));
                $this->controllerSuffix = $this->app->config->get('route.controller_suffix') ? 'Controller' : '';

                $dirs = array_merge(
                    $this->app->config->get('annotation.route.controllers', []),
                    [$this->controllerDir]
                );

                foreach ($dirs as $dir => $options) {
                    if (is_numeric($dir)) {
                        $dir     = $options;
                        $options = [];
                    }

                    if (is_dir($dir)) {
                        $this->scanDir(realpath($dir), $options);
                    }
                }
            });
        }
    }

    protected function scanDir($dir, $options = [])
    {
        $groups = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            $dir,
            \FilesystemIterator::FOLLOW_SYMLINKS,
        ));

        $namespace = Arr::pull($options, 'namespace') ?: $this->getNamespace($dir, $this->controllerDir, "{$this->app->getNamespace()}\\controller");

        if (empty($namespace)) {
            return;
        }

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getBasename('.php') === $fileInfo->getBasename()) {
                continue;
            }

            $filename = $fileInfo->getRealPath();

            $classNamespace = $this->getNamespace(dirname($filename), $dir, $namespace);
            if (empty($namespace)) {
                continue;
            }

            $class = "\\{$classNamespace}\\{$fileInfo->getBasename('.php')}";

            if (in_array($class, $this->parsedClass)) {
                continue;
            }

            $this->parsedClass[] = $class;

            $refClass = new ReflectionClass($class);

            if ($refClass->isAbstract() || $refClass->isInterface() || $refClass->isTrait()) {
                continue;
            }

            $prefix = $class;

            if (Str::startsWith($filename, $this->controllerDir)) {
                //控制器
                $filename = Str::substr($filename, strlen($this->controllerDir) + 1);
                $prefix   = str_replace($this->controllerSuffix . '.php', '', str_replace('/', '.', $filename));
            }

            $routes = [];
            //方法
            foreach ($refClass->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod) {
                if ($routeAnn = $this->reader->getAnnotation($refMethod, Route::class)) {

                    $routes[] = function () use ($routeAnn, $prefix, $refMethod) {
                        //注册路由
                        $rule = $this->route->rule($routeAnn->rule, "{$prefix}/{$refMethod->getName()}", $routeAnn->method);

                        $rule->option($routeAnn->options);

                        //变量规则
                        if (!empty($patternsAnn = $this->reader->getAnnotations($refMethod, Pattern::class))) {
                            foreach ($patternsAnn as $patternAnn) {
                                $rule->pattern([$patternAnn->name => $patternAnn->value]);
                            }
                        }

                        //中间件
                        if (!empty($middlewaresAnn = $this->reader->getAnnotations($refMethod, Middleware::class))) {
                            foreach ($middlewaresAnn as $middlewareAnn) {
                                $rule->middleware($middlewareAnn->value, ...$middlewareAnn->params);
                            }
                        }

                        //绑定模型,支持多个
                        if (!empty($modelsAnn = $this->reader->getAnnotations($refMethod, Model::class))) {
                            foreach ($modelsAnn as $modelAnn) {
                                $rule->model($modelAnn->var, $modelAnn->value, $modelAnn->exception);
                            }
                        }

                        //验证
                        if ($validateAnn = $this->reader->getAnnotation($refMethod, Validate::class)) {
                            $rule->validate($validateAnn->value, $validateAnn->scene, $validateAnn->message, $validateAnn->batch);
                        }
                    };
                }
            }

            $groups[] = function () use ($routes, $refClass, $prefix) {
                $groupName    = '';
                $groupOptions = [];
                if ($groupAnn = $this->reader->getAnnotation($refClass, Group::class)) {
                    $groupName    = $groupAnn->name;
                    $groupOptions = $groupAnn->options;
                }

                $group = $this->route->group($groupName, function () use ($refClass, $prefix, $routes) {
                    //注册路由
                    foreach ($routes as $route) {
                        $route();
                    }

                    if ($resourceAnn = $this->reader->getAnnotation($refClass, Resource::class)) {
                        //资源路由
                        $this->route->resource($resourceAnn->rule, $prefix)->option($resourceAnn->options);
                    }
                });

                $group->option($groupOptions);

                //变量规则
                if (!empty($patternsAnn = $this->reader->getAnnotations($refClass, Pattern::class))) {
                    foreach ($patternsAnn as $patternAnn) {
                        $group->pattern([$patternAnn->name => $patternAnn->value]);
                    }
                }

                //中间件
                if (!empty($middlewaresAnn = $this->reader->getAnnotations($refClass, Middleware::class))) {
                    foreach ($middlewaresAnn as $middlewareAnn) {
                        $group->middleware($middlewareAnn->value, ...$middlewareAnn->params);
                    }
                }
            };
        }

        if (!empty($groups)) {
            $name = Arr::pull($options, 'name', '');
            $this->route->group($name, function () use ($groups) {
                //注册路由
                foreach ($groups as $group) {
                    $group();
                }
            })->option($options);
        }
    }

    /**
     * Calculate the namespace based on the relative path between directories
     *
     * @param string $dir The directory to get the namespace for
     * @param string $base The base directory
     * @param string $baseNamespace The base namespace
     * @return string|null The calculated namespace or null if $dir is not a subdirectory of $base
     */
    protected function getNamespace($dir, $base, $baseNamespace)
    {
        // Normalize directory separators
        $dir  = rtrim(str_replace('\\', '/', $dir), '/') . '/';
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';

        // Check if $dir is a subdirectory of $base
        if (!str_starts_with($dir, $base)) {
            return null;
        }

        // Get the relative path
        $relativePath = substr($dir, strlen($base));

        // If the relative path is empty, return the base namespace
        if (empty($relativePath) || $relativePath === '/') {
            return $baseNamespace;
        }

        // Convert directory separators to namespace separators
        $namespace = str_replace('/', '\\', rtrim($relativePath, '/'));

        // Combine with the base namespace
        return $baseNamespace . '\\' . $namespace;
    }
}
