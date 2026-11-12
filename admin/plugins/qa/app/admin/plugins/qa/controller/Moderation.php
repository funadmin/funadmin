<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;
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
