<?php
declare(strict_types=1);
namespace app\qa\controller;

use OpenApi\Annotations as OA;

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
    /**
     * 问题列表 SEO 页面。
     *
     * @OA\Get(
     *     path="/qa/questions",
     *     operationId="qa_controller_Pages_index_get",
     *     tags={"问答前台页面"},
     *     summary="问题列表 SEO 页面。",
     *     description="站点按请求域名识别；同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Parameter(name="q", in="query", required=false, description="标题关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     *
     * @OA\Get(
     *     path="/qa/questions/",
     *     operationId="qa_controller_Pages_index_slash_get",
     *     tags={"问答前台页面"},
     *     summary="问题列表 SEO 页面。",
     *     description="站点按请求域名识别；同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Parameter(name="q", in="query", required=false, description="标题关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     */
    #[Route('', ['GET','HEAD'])]
    #[Route('/', ['GET','HEAD'])]
    public function index(Request $request): Response { return (new PublicPages())->handle($request); }
    /**
     * 问题详情 SEO 页面。
     *
     * @OA\Get(
     *     path="/qa/questions/{id}",
     *     operationId="qa_controller_Pages_detail_get",
     *     tags={"问答前台页面"},
     *     summary="问题详情 SEO 页面。",
     *     description="站点按请求域名识别；同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Parameter(name="id", in="path", required=true, description="问题 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     */
    #[Route('/{id:[1-9][0-9]{0,19}}', ['GET','HEAD'])]
    public function detail(Request $request, string $id): Response { return (new PublicPages())->handle($request, $id); }
}
