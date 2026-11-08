<?php

declare(strict_types=1);

namespace app\document\controller;

use app\document\service\PublicDocumentService;
use app\document\service\ReaderPage;
use InvalidArgumentException;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;

/** 公开阅读页面；插件激活保护由全局 PluginApplicationGuardMiddleware 执行。 */
#[RouteGroup('/docs')]
final class Reader
{
    #[Get('')]
    #[Get('/')]
    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    #[Get('/assets/reader.css')]
    public function stylesheet(): Response { return $this->asset('reader.css', 'text/css; charset=utf-8'); }

    #[Get('/assets/reader.js')]
    public function script(): Response { return $this->asset('reader.js', 'text/javascript; charset=utf-8'); }

    private function asset(string $name, string $type): Response
    {
        return new Response(200, ['Content-Type'=>$type, 'Cache-Control'=>'public, max-age=86400, immutable', 'X-Content-Type-Options'=>'nosniff'], (string)file_get_contents(ReaderPage::assetPath($name)));
    }

    #[Get('/{slug:[a-z0-9][a-z0-9-]{0,119}}')]
    public function detail(Request $request, string $slug): Response
    {
        return $this->page($request, $slug);
    }

    private function page(Request $request, ?string $slug = null): Response
    {
        try {
            $service = new PublicDocumentService();
            $document = $slug === null ? null : $service->find($slug);
            if ($slug === null && $request->get() === []) {
                $first = $service->listing([])['list'][0] ?? null;
                if ($first) $document = $service->find($first['slug']);
            }

            // 点击文档范围或目录时直接打开该处的第一篇文档；只有搜索才显示结果列表。
            $query = $request->get();
            if ($slug === null && ($query['keyword'] ?? '') === '' && (($query['scope'] ?? '') !== '' || ($query['categoryId'] ?? '') !== '')) {
                $first = $service->firstSlug($query);
                if ($first !== null) return new Response(302, ['Location'=>'/docs/'.rawurlencode($first), 'Cache-Control'=>'no-store']);
            }

            if ($slug !== null && $document === null) return $this->html((new ReaderPage())->render(['list'=>[],'total'=>0,'page'=>1,'pageSize'=>20], [], null, [], '文档不存在或尚未公开'), 404);
            $filters = $document ? ['scope'=>$document['scope']] : $request->get();
            return $this->html((new ReaderPage())->render($service->listing($filters), $service->navigation(), $document, $filters));
        } catch (InvalidArgumentException $error) {
            return $this->html((new ReaderPage())->render(['list'=>[],'total'=>0,'page'=>1,'pageSize'=>20], [], null, [], $error->getMessage()), 422);
        } catch (\Throwable $error) {
            \support\Log::error('document.public.failed', ['exception'=>$error::class]);
            return $this->html((new ReaderPage())->render(['list'=>[],'total'=>0,'page'=>1,'pageSize'=>20], [], null, [], '文档暂时无法加载，请稍后重试'), 500);
        }
    }

    private function html(string $html, int $status = 200): Response
    {
        return new Response($status, [
            'Content-Type'=>'text/html; charset=utf-8', 'Cache-Control'=>'no-store',
            'X-Content-Type-Options'=>'nosniff', 'Referrer-Policy'=>'strict-origin-when-cross-origin',
            'Content-Security-Policy'=>"default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' https: http:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ], $html);
    }
}
