<?php
declare(strict_types=1);
namespace app\qa\controller;

use OpenApi\Annotations as OA;

use app\cms\controller\MemberEndpoint;
use app\cms\service\{ContributionPolicy,Input};
use app\qa\service\{AttachmentService,QuestionService};
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
#[RouteGroup('/qa/api')]
final class Questions extends MemberEndpoint
{
    /**
     * 获取可用站点、语言与投稿策略。
     *
     * @OA\Get(
     *     path="/qa/api/context",
     *     operationId="qa_controller_Questions_context_get",
     *     tags={"问答会员接口"},
     *     summary="获取可用站点、语言与投稿策略。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={},
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/context')]
    public function context(): Response { return $this->action(fn()=>(new ContributionPolicy())->context()); }
    /**
     * 获取已公开的问题列表。
     *
     * @OA\Get(
     *     path="/qa/api/questions",
     *     operationId="qa_controller_Questions_index_get",
     *     tags={"问答会员接口"},
     *     summary="获取已公开的问题列表。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="q", in="query", required=false, description="标题关键字。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/questions')]
    public function index(): Response { return $this->action(fn()=>(new QuestionService())->listing($this->site(),$this->locale(),Input::text($this->input()['q']??'',100),$this->page())); }
    /**
     * 获取已公开问题的详情与回答。
     *
     * @OA\Get(
     *     path="/qa/api/questions/{id}",
     *     operationId="qa_controller_Questions_detail_get",
     *     tags={"问答会员接口"},
     *     summary="获取已公开问题的详情与回答。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={},
     *     @OA\Parameter(name="id", in="path", required=true, description="问题 ID。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/questions/{id}')]
    public function detail(string $id): Response { return $this->action(fn()=>(new QuestionService())->detail($this->site(),$this->locale(),Input::id($id))); }
    /**
     * 提问；可用 multipart 上传附件，同一 request_key 重放返回已有记录。
     *
     * @OA\Post(
     *     path="/qa/api/questions",
     *     operationId="qa_controller_Questions_ask_post",
     *     tags={"问答会员接口"},
     *     summary="提问；可用 multipart 上传附件，同一 request_key 重放返回已有记录。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={{"MemberBearer"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"site_id", "request_key", "title"},
     *                 @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *                 @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *                 @OA\Property(property="request_key", type="string", description="幂等键，8 到 64 位字母、数字、下划线或短横线。"),
     *                 @OA\Property(property="title", type="string", description="标题。"),
     *                 @OA\Property(property="body_html", type="string", description="正文 HTML，会被清洗。"),
     *                 @OA\Property(property="attachments[]", description="会员附件。", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         ),
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "request_key", "title"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="request_key", type="string", description="幂等键。"),
     *             @OA\Property(property="title", type="string", description="标题。"),
     *             @OA\Property(property="body_html", type="string", description="正文 HTML。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=409, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/questions')]
    public function ask(): Response { return $this->action(fn()=>(new QuestionService())->ask($this->site(),$this->locale(),$this->member(),$this->input(),$this->uploads())); }
    /**
     * 回答问题；可用 multipart 上传附件。
     *
     * @OA\Post(
     *     path="/qa/api/questions/{id}/answers",
     *     operationId="qa_controller_Questions_answer_post",
     *     tags={"问答会员接口"},
     *     summary="回答问题；可用 multipart 上传附件。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="问题 ID。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"site_id", "request_key", "body_html"},
     *                 @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *                 @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *                 @OA\Property(property="request_key", type="string", description="幂等键，8 到 64 位字母、数字、下划线或短横线。"),
     *                 @OA\Property(property="body_html", type="string", description="回答正文 HTML，会被清洗。"),
     *                 @OA\Property(property="attachments[]", description="会员附件。", type="array", @OA\Items(type="string", format="binary"))
     *             )
     *         ),
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "request_key", "body_html"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="request_key", type="string", description="幂等键。"),
     *             @OA\Property(property="body_html", type="string", description="回答正文 HTML。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=409, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/questions/{id}/answers')]
    public function answer(string $id): Response { return $this->action(fn()=>(new QuestionService())->answer($this->site(),$this->locale(),$this->member(),Input::id($id),$this->input(),$this->uploads())); }
    /**
     * 会员下载问答附件（1.3.0）：需要登录；公开内容任何会员可下载，未公开的只有上传者本人。失败时返回与其他接口相同的 JSON 错误。
     *
     * @OA\Get(
     *     path="/qa/api/attachments/{id}",
     *     operationId="qa_controller_Questions_attachment_get",
     *     tags={"问答会员接口"},
     *     summary="下载问答附件：公开内容任何会员可下载，未公开的只有上传者本人。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="附件 ID。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Response(response=200, description="文件内容；Content-Disposition 给出文件名。出错时返回 JSON 错误信封。", @OA\MediaType(mediaType="application/octet-stream", @OA\Schema(type="string", format="binary"))),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/attachments/{id}')]
    public function attachment(string $id): Response
    {
        $file=null;$error=$this->action(function()use($id,&$file){$file=(new AttachmentService())->memberFile($this->site(),$this->member(),Input::id($id));return null;});
        return $file===null?$error:AttachmentService::response($file);
    }
    /**
     * 提问者采纳回答。
     *
     * @OA\Post(
     *     path="/qa/api/questions/{id}/accept",
     *     operationId="qa_controller_Questions_accept_post",
     *     tags={"问答会员接口"},
     *     summary="提问者采纳回答。",
     *     description="响应不缓存；需要会员身份的接口使用会员 API v2 access token。",
     *     security={{"MemberBearer"={}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="问题 ID。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "answer_id", "question_version"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="locale", type="string", description="内容语言代码，默认 zh-cn。"),
     *             @OA\Property(property="answer_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="回答 ID。"),
     *             @OA\Property(property="question_version", type="integer", description="问题版本。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=401, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=403, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=409, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/questions/{id}/accept')]
    public function accept(string $id): Response { return $this->action(fn()=>(new QuestionService())->accept($this->site(),$this->locale(),$this->member(),Input::id($id),Input::id(Input::text($this->input()['answer_id']??'')),$this->version())); }
    /** multipart 表单中的附件（attachments 或 attachments[]）；JSON 请求没有附件。 */
    private function uploads(): array { return AttachmentService::uploads($this->request->file(AttachmentService::FIELD)); }
    private function site(): string { return Input::id(Input::text($this->input()['site_id']??'')); }
    private function locale(): string { return Input::locale($this->input()['locale']??'zh-cn'); }
    private function page(): int { $value=Input::text($this->input()['page']??'1',10);if(!preg_match('/^[1-9][0-9]{0,8}$/D',$value))throw new \InvalidArgumentException('页码无效',422);return (int)$value; }
    private function version(): int { $value=Input::text($this->input()['question_version']??'',10);if(!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$value)||(int)$value>2147483647)throw new \InvalidArgumentException('问题版本无效',422);return (int)$value; }
}
