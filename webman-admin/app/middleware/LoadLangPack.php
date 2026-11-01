<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Cookie;
use think\Request;
use think\Response;

/**
 * 多语言加载（继承框架中间件）。
 *
 * 两处扩展：
 * 1. 全局 cookie 配置 httponly=true，框架默认写出的 think_lang 为 HttpOnly，
 *    前端 appStore.setLocale 通过 document.cookie 同步语言时会被浏览器静默拒绝，
 *    导致后端语言检测永远停留在旧值。这里仅放开 think_lang 的 HttpOnly，
 *    不影响其他 cookie 的安全姿态。
 * 2. 框架 switchLangSet 只加载 lang/{langset}.* 单文件语言包，
 *    lang/{langset}/*.php 分组语言文件（如生成的 crud 模块消息）不会自动加载，
 *    这里显式并入；分组文件按 [group => [key => message]] 嵌套返回，
 *    平铺文件（如遗留英文 key 映射）保持原有查找行为。
 */
class LoadLangPack extends \think\middleware\LoadLangPack
{
    /**
     * 路由初始化：语言侦测 + 单文件/分组语言包加载 + 语言 cookie 写回
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $langset = $this->detect($request);

        // 无条件切换：默认语言时父类不会调用 switchLangSet，
        // 而分组并入后 Lang::get 不会再惰性加载单文件应用语言包。
        $this->lang->switchLangSet($langset);

        $this->loadGroupPacks($langset);

        $this->saveToCookie($this->app->cookie, $langset);

        return $next($request);
    }

    /**
     * 加载应用分组语言目录 lang/{langset}/*.php（框架只自动加载单文件包）
     */
    protected function loadGroupPacks(string $langset): void
    {
        $files = (array) glob($this->app->getAppPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . DIRECTORY_SEPARATOR . '*.php');
        if ($files !== []) {
            $this->lang->load($files);
        }
    }

    /**
     * 保存当前语言到 Cookie（允许 js 读写）
     * @param Cookie $cookie Cookie对象
     * @param string $langSet 语言
     */
    protected function saveToCookie(Cookie $cookie, string $langSet): void
    {
        if ($this->config['use_cookie']) {
            $cookie->set($this->config['cookie_var'], $langSet, ['httponly' => false]);
        }
    }
}
