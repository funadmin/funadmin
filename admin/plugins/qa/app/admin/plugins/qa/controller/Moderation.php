<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\cms\service\ContributionService;
use app\cms\service\Input;
use app\cms\service\SiteAccess;
use app\cms\service\SiteAuthorization;
use app\qa\service\QuestionService;
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
use app\cms\model\MemberSubmission;
#[RouteGroup('/admin/plugins/qa/moderation')]
final class Moderation extends AdminController
{
    /**
     * 获取待审核或指定状态的问题与回答（每页 20 条）。
     *
     * @OA\Get(
     *     path="/admin/plugins/qa/moderation",
     *     operationId="admin_plugins_qa_controller_Moderation_index_get",
     *     tags={"问答审核"},
     *     summary="获取待审核或指定状态的问题与回答（每页 20 条）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Parameter(name="site_id", in="query", required=true, description="站点 ID。", @OA\Schema(type="string", pattern="^[1-9][0-9]{0,19}$")),
     *     @OA\Parameter(name="locale", in="query", required=false, description="内容语言代码，默认 zh-cn。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="投稿状态，默认 pending。", @OA\Schema(type="string", enum={"pending", "published", "rejected", "withdrawn", "hidden"})),
     *     @OA\Parameter(name="page", in="query", required=false, description="页码，从 1 开始。", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        return $this->action(function():array {
            $site=$this->site('cms:submission:list');$input=$this->input();$locale=Input::locale($input['locale']??'zh-cn');$status=Input::text($input['status']??'pending',20);
            if(!in_array($status,['pending','published','rejected','withdrawn','hidden'],true))throw new \InvalidArgumentException('投稿状态无效',422);
            $raw=Input::text($input['page']??'1',10);if(!preg_match('/^[1-9][0-9]{0,8}$/D',$raw))throw new \InvalidArgumentException('页码无效',422);$page=(int)$raw;
            // 只列出问答侧未删除的问题与回答（问题已删除时其回答也不列出）；已删除的在问答回收站处理。
            $query=MemberSubmission::query()->where('site_id',$site)->where('locale',$locale)->where('status',$status)->where(function($w):void {
                $w->where(fn($q)=>$q->where('kind','question')->whereExists(fn($e)=>$e->from('qa_question as q')->whereColumn('q.id','cms_member_submission.id')->whereNull('q.deleted_at')))
                  ->orWhere(fn($q)=>$q->where('kind','answer')->whereExists(fn($e)=>$e->from('qa_answer as a')->join('qa_question as q','q.id','=','a.question_id')->whereColumn('a.id','cms_member_submission.id')->whereNull('a.deleted_at')->whereNull('q.deleted_at')));
            });
            $total=$query->count();$ids=$query->orderByDesc('id')->offset(($page-1)*20)->limit(20)->pluck('id');$service=new ContributionService();$items=[];
            foreach($ids as $id)$items[]=$service->get($site,(string)$id);
            return ['items'=>$items,'total'=>$total,'page'=>$page,'limit'=>20];
        });
    }
    /**
     * 审核问题或回答（通过或驳回）。
     *
     * @OA\Post(
     *     path="/admin/plugins/qa/moderation/review",
     *     operationId="admin_plugins_qa_controller_Moderation_review_post",
     *     tags={"问答审核"},
     *     summary="审核问题或回答（通过或驳回）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"site_id", "id", "lock_version", "decision"},
     *             @OA\Property(property="site_id", type="string", pattern="^[1-9][0-9]{0,19}$", description="站点 ID。"),
     *             @OA\Property(property="id", type="string", pattern="^[1-9][0-9]{0,19}$", description="投稿 ID。"),
     *             @OA\Property(property="lock_version", type="integer", description="投稿版本。"),
     *             @OA\Property(property="decision", type="string", description="审核决定。"),
     *             @OA\Property(property="reason", type="string", description="驳回原因，最多 1000 字。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/review')]
    public function review(): Response
    {
        return $this->action(function():array {
            $site=$this->site('cms:submission:review');$input=$this->input();$raw=Input::text($input['lock_version']??'',10);
            if(!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$raw)||(int)$raw>2147483647)throw new \InvalidArgumentException('投稿版本无效',422);
            return (new QuestionService())->review($site,$this->actor(),Input::id(Input::text($input['id']??'')),(int)$raw,Input::text($input['decision']??'',20),Input::text($input['reason']??'',1000));
        });
    }
    private function input(): array { return array_replace($this->request->get(),$this->request->post()); }
    private function actor(): string { return Input::id(Input::text($this->request->context['admin']['id']??'')); }
    private function site(string $operation): string { $site=Input::id(Input::text($this->input()['site_id']??''));(new SiteAccess())->assert($site,$this->actor());(new SiteAuthorization())->assert($site,$this->actor(),$operation);return $site; }
    private function action(callable $callback): Response
    {
        try{return $this->ok(data:$callback());}
        catch(\InvalidArgumentException $e){return $this->fail(msg:$e->getMessage(),code:in_array($e->getCode(),[401,403,404,409,422,429],true)?$e->getCode():422);}
        catch(\Illuminate\Database\UniqueConstraintViolationException){return $this->fail(msg:'记录冲突，请刷新后重试',code:409);}
        catch(\Throwable){return $this->fail(msg:'问答审核暂时不可用',code:500);}
    }
}
