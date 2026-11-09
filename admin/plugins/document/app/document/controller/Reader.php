<?php

declare(strict_types=1);

namespace app\document\controller;

use app\document\service\FeedbackService;
use app\document\service\PublicDocumentService;
use app\document\service\ReaderPage;
use InvalidArgumentException;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\Post;
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

    /**
     * 读者反馈。只接受同源的 JSON 请求：跨站页面无法不经预检发送 application/json，
     * 浏览器带上的 Origin / Sec-Fetch-Site 也必须是本站。
     */
    #[Post('/{slug:[a-z0-9][a-z0-9-]{0,119}}/feedback')]
    public function feedback(Request $request, string $slug): Response
    {
        if (!str_starts_with(strtolower((string)$request->header('content-type', '')), 'application/json')) return $this->json(415, '请求格式无效');
        if (!$this->sameOrigin($request)) return $this->json(403, '请求来源无效');
        $input = json_decode((string)$request->rawBody(), true);
        if (!is_array($input)) return $this->json(422, '请求格式无效');
        try {
            $data = (new FeedbackService())->submit($slug, $input, $this->ip($request));
            return $this->json(200, '感谢你的反馈', $data);
        } catch (InvalidArgumentException $error) {
            $status = in_array($error->getCode(), [404, 422, 429], true) ? $error->getCode() : 422;
            return $this->json($status, $error->getMessage());
        } catch (\Throwable $error) {
            \support\Log::error('document.feedback.failed', ['exception'=>$error::class]);
            return $this->json(500, '反馈暂时无法提交，请稍后重试');
        }
    }

    private function sameOrigin(Request $request): bool
    {
        $site = strtolower((string)$request->header('sec-fetch-site', ''));
        if ($site !== '' && !in_array($site, ['same-origin', 'none'], true)) return false;
        $origin = (string)$request->header('origin', '');
        if ($origin === '') return true;
        $host = parse_url($origin, PHP_URL_HOST);
        return is_string($host) && strtolower($host) === strtolower((string)$request->host(true));
    }

    /** 与 CMS 表单一致：内网代理后只取 X-Forwarded-For 最后一段（代理追加的地址），没有 XFF 时才用 X-Real-IP。 */
    private function ip(Request $request): string
    {
        $remote = trim((string)$request->getRemoteIp(), '[]');
        $remote = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
        if (!Request::isIntranetIp($remote)) return $remote;
        $forwarded = trim((string)$request->header('x-forwarded-for', ''));
        if ($forwarded !== '') {
            $hops = explode(',', $forwarded);
            $last = trim((string)end($hops));
            return filter_var($last, FILTER_VALIDATE_IP) ? $last : $remote;
        }
        $real = trim((string)$request->header('x-real-ip', ''));
        return filter_var($real, FILTER_VALIDATE_IP) ? $real : $remote;
    }

    private function json(int $status, string $message, array $data = []): Response
    {
        $body = json_encode(['code'=>$status === 200 ? 0 : $status, 'msg'=>$message, 'data'=>$data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return new Response($status, ['Content-Type'=>'application/json; charset=utf-8', 'Cache-Control'=>'no-store', 'X-Content-Type-Options'=>'nosniff'], $body);
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
