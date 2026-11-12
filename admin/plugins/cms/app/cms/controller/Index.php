<?php
declare(strict_types=1);
namespace app\cms\controller;

use OpenApi\Annotations as OA;

use support\annotation\route\{Route,RouteGroup};
use support\{Request,Response};
use app\cms\service\PublicSite;
#[RouteGroup('/cms')]
final class Index
{
    /**
     * 渲染 CMS 前台页面（首页、栏目、内容等）。
     *
     * @OA\Get(
     *     path="/cms",
     *     operationId="cms_controller_Index_index_get",
     *     tags={"CMS 前台页面"},
     *     summary="渲染 CMS 前台页面（首页、栏目、内容等）。",
     *     description="站点由请求域名确定；/cms/member 与 /cms/forms 不由本路由处理。同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面；未找到时为 404 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     *
     * @OA\Get(
     *     path="/cms/",
     *     operationId="cms_controller_Index_index_slash_get",
     *     tags={"CMS 前台页面"},
     *     summary="渲染 CMS 前台页面（首页、栏目、内容等）。",
     *     description="站点由请求域名确定；/cms/member 与 /cms/forms 不由本路由处理。同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面；未找到时为 404 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     *
     * @OA\Get(
     *     path="/cms/{path}",
     *     operationId="cms_controller_Index_index_path_get",
     *     tags={"CMS 前台页面"},
     *     summary="渲染 CMS 前台页面（首页、栏目、内容等）。",
     *     description="站点由请求域名确定；/cms/member 与 /cms/forms 不由本路由处理。同一路径也接受 HEAD。",
     *     security={},
     *     @OA\Parameter(name="path", in="path", required=true, description="站内页面路径，不以 member、forms 开头。", @OA\Schema(type="string", pattern="^(?!(?:member|forms)(?:/|$)).+$")),
     *     @OA\Response(response=200, description="服务端渲染的 HTML 页面；未找到时为 404 页面。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, description="页面不存在（HTML 页面）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response="default", description="错误页面（HTML）。", @OA\MediaType(mediaType="text/html", @OA\Schema(type="string")))
     * )
     */
    #[Route('', ['GET','HEAD'])]
    #[Route('/', ['GET','HEAD'])]
    // Reserve member and public form API paths so the public catch-all cannot shadow their routes.
    #[Route('/{path:(?!(?:member|forms)(?:/|$)).+}', ['GET','HEAD'])]
    public function index(Request $request,string $path=''): Response { return (new PublicSite())->handle($request); }
}
