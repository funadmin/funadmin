<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\Manifest;

function nativeHardCutExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$plugins = ['example', 'shop'];
$layers = ['controller', 'model', 'service', 'validate', 'middleware'];
$newBase = 'app\\common\\plugin\\sdk\\Plugin';

nativeHardCutExpect(is_file($root . '/app/common/plugin/sdk/Plugin.php'), '插件 SDK 必须提供唯一新基类文件');
nativeHardCutExpect(class_exists($newBase), '新插件基类必须可通过自动加载解析');
$entryFactorySource = (string) file_get_contents($root . '/app/common/plugin/sdk/PluginEntryFactory.php');
nativeHardCutExpect(str_contains($entryFactorySource, 'instanceof Plugin'), '插件入口工厂必须在运行时强制唯一新基类');

$sourceRoots = [$root . '/app', $root . '/config', $root . '/plugins', $root . '/tests'];
foreach ($sourceRoots as $sourceRoot) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        nativeHardCutExpect(preg_match('/extends\\s+Plugins\\b/', $source) !== 1, 'PHP 源码不得继承旧 Plugins 短类名：' . $file->getPathname());
    }
}

foreach ($plugins as $code) {
    $manifest = Manifest::fromDirectory($root . '/plugins/' . $code);
    nativeHardCutExpect($manifest->code() === $code, $code . ' 源目录必须只通过 Manifest 校验');
    $entryClass = $manifest->toArray()['entry']['class'];
    nativeHardCutExpect(is_subclass_of($entryClass, $newBase), $code . ' 插件入口必须继承唯一新基类');

    $applicationRoot = $root . '/plugins/' . $code . '/app/' . $code;
    if (is_dir($applicationRoot)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($applicationRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($applicationRoot) + 1);
            $layer = explode(DIRECTORY_SEPARATOR, $relative, 2)[0];
            $source = (string) file_get_contents($file->getPathname());
            nativeHardCutExpect(
                str_contains($source, 'namespace app\\' . $code . '\\' . $layer . ';'),
                $code . ' application 源码必须使用 app\\code\\layer 原生 namespace：' . $relative
            );
            nativeHardCutExpect(!str_contains($source, 'namespace plugin\\'), 'application 不得保留 plugin namespace：' . $relative);
        }
    }

    foreach ($layers as $layer) {
        $consoleRoot = $root . '/plugins/' . $code . '/app/console/' . $layer;
        if (!is_dir($consoleRoot)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($consoleRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            nativeHardCutExpect(
                str_contains($source, 'namespace app\\console\\' . $layer . '\\plugin\\' . $code . ';'),
                $code . ' console 源码必须使用 app\\console\\layer\\plugin\\code 原生 namespace'
            );
            nativeHardCutExpect(!str_contains($source, 'namespace plugin\\'), 'console 不得保留 plugin namespace');
        }
    }
}

$annotation = require $root . '/config/annotation.php';
nativeHardCutExpect(($annotation['route']['controllers'] ?? null) === [], 'Attribute 扫描不得直接扫描插件源目录');
$annotationService = (string) file_get_contents($root . '/vendor/topthink/think-annotation/src/InteractsWithRoute.php');
nativeHardCutExpect(
    str_contains($annotationService, 'RecursiveDirectoryIterator') && str_contains($annotationService, '[$this->controllerDir]'),
    'Attribute 扫描必须递归覆盖发布后的 console/controller/plugin/code'
);

$consoleController = (string) file_get_contents($root . '/plugins/shop/app/console/controller/ProductController.php');
nativeHardCutExpect(str_contains($consoleController, "#[Group('plugin/shop/product')]"), '发布后的 Console controller 必须保留 Attribute 路由');
foreach (['routes/plugin.php', 'config/services.php', 'config/events.php'] as $legacyRuntimeSource) {
    nativeHardCutExpect(
        !is_file($root . '/plugins/example/' . $legacyRuntimeSource),
        '示例插件源目录不得保留旧 runtime 文件：' . $legacyRuntimeSource
    );
}

echo "plugin native hard cut tests passed\n";
