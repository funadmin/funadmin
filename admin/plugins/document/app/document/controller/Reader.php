<?php

declare(strict_types=1);

namespace app\document\controller;

use OpenApi\Annotations as OA;

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
    /**
     * 文档阅读首页：无查询参数时显示第一篇公开文档；按范围或目录访问时跳转到该处第一篇文档。
     *
     * @OA\Get(
     *     path="/docs",
     *     operationId="document_controller_Reader_index_get",
     *     tags={"文档阅读"},
     *     summary="文档阅读首页：无查询参数时显示第一篇公开文档；按范围或目录访问时跳转到该处第一篇文档。",
     *     security={},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="scope", in="query", required=false, description="文档范围。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="categoryId", in="query", required=false, description="目录 ID。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=302, description="跳转到范围或目录下的第一篇文档。", @OA\Header(header="Location", description="跳转地址。", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     *
     * @OA\Get(
     *     path="/docs/",
     *     operationId="document_controller_Reader_index_slash_get",
     *     tags={"文档阅读"},
     *     summary="文档阅读首页：无查询参数时显示第一篇公开文档；按范围或目录访问时跳转到该处第一篇文档。",
     *     security={},
     *     @OA\Parameter(name="keyword", in="query", required=false, description="搜索关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="scope", in="query", required=false, description="文档范围。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="categoryId", in="query", required=false, description="目录 ID。", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=302, description="跳转到范围或目录下的第一篇文档。", @OA\Header(header="Location", description="跳转地址。", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     */
    #[Get('')]
    #[Get('/')]
    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    /**
     * 阅读页样式表。
     *
     * @OA\Get(
     *     path="/docs/assets/reader.css",
     *     operationId="document_controller_Reader_stylesheet_get",
     *     tags={"文档阅读"},
     *     summary="阅读页样式表。",
     *     security={},
     *     @OA\Response(response=200, description="阅读页样式表。", @OA\MediaType(mediaType="text/css", @OA\Schema(type="string")))
     * )
     */
    #[Get('/assets/reader.css')]
    public function stylesheet(): Response { return $this->asset('reader.css', 'text/css; charset=utf-8'); }

    /**
     * 阅读页脚本。
     *
     * @OA\Get(
     *     path="/docs/assets/reader.js",
     *     operationId="document_controller_Reader_script_get",
     *     tags={"文档阅读"},
     *     summary="阅读页脚本。",
     *     security={},
     *     @OA\Response(response=200, description="阅读页脚本。", @OA\MediaType(mediaType="text/javascript", @OA\Schema(type="string")))
     * )
     */
    #[Get('/assets/reader.js')]
    public function script(): Response { return $this->asset('reader.js', 'text/javascript; charset=utf-8'); }

    private function asset(string $name, string $type): Response
    {
        return new Response(200, ['Content-Type'=>$type, 'Cache-Control'=>'public, max-age=86400, immutable', 'X-Content-Type-Options'=>'nosniff'], (string)file_get_contents(ReaderPage::assetPath($name)));
    }

    /**
     * 文档阅读页；文档不存在或未公开时返回 404 页面。
     *
     * @OA\Get(
     *     path="/docs/{slug}",
     *     operationId="document_controller_Reader_detail_get",
     *     tags={"文档阅读"},
     *     summary="文档阅读页；文档不存在或未公开时返回 404 页面。",
     *     security={},
     *     @OA\Parameter(name="slug", in="path", required=true, description="文档标识。", @OA\Schema(type="string", pattern="^[a-z0-9][a-z0-9-]{0,119}$")),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="文档不存在或尚未公开（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     */
    #[Get('/{slug:[a-z0-9][a-z0-9-]{0,119}}')]
    public function detail(Request $request, string $slug): Response
    {
        return $this->page($request, $slug);
    }

    /**
     * 读者反馈。只接受同源的 JSON 请求：跨站页面无法不经预检发送 application/json，
     * 浏览器带上的 Origin / Sec-Fetch-Site 也必须是本站。
     *
     * @OA\Post(
     *     path="/docs/{slug}/feedback",
     *     operationId="document_controller_Reader_feedback_post",
     *     tags={"文档阅读"},
     *     summary="提交读者反馈；只接受同源 application/json 请求。",
     *     description="成功时 code=0。跨站来源返回 403，非 JSON 返回 415，频率限制返回 429。",
     *     security={},
     *     @OA\Parameter(name="slug", in="path", required=true, description="文档标识。", @OA\Schema(type="string", pattern="^[a-z0-9][a-z0-9-]{0,119}$")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"helpful", "visitor"},
     *             description="字段由 FeedbackService::submit 校验。",
     *             additionalProperties=true,
     *             @OA\Property(property="helpful", type="boolean", description="是否有帮助。"),
     *             @OA\Property(property="visitor", type="string", pattern="^[A-Za-z0-9_-]{16,64}$", description="浏览器生成的访客标识。"),
     *             @OA\Property(property="reasons", description="原因选项。", type="array", @OA\Items(type="string")),
     *             @OA\Property(property="comment", type="string", description="补充说明。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=415, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=500, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
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
