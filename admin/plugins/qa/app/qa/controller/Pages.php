<?php
declare(strict_types=1);
namespace app\qa\controller;
use app\qa\service\PublicPages;
use support\annotation\route\{Route,RouteGroup};
use support\{Request,Response};
/**
 * 问答前台 SEO 页面（1.3.0）：服务端渲染的问题列表与详情，站点按请求域名识别（与 CMS 前台相同）。
 * 站点域名的反向代理需要把 /qa/questions 转发到后端同一路径（见插件 README）。
 */
#[RouteGroup('/qa/questions')]
final class Pages
{
    #[Route('', ['GET','HEAD'])]
    #[Route('/', ['GET','HEAD'])]
    public function index(Request $request): Response { return (new PublicPages())->handle($request); }
    #[Route('/{id:[1-9][0-9]{0,19}}', ['GET','HEAD'])]
    public function detail(Request $request, string $id): Response { return (new PublicPages())->handle($request, $id); }
}
