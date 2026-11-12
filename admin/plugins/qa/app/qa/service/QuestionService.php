<?php
declare(strict_types=1);
namespace app\qa\service;
use app\cms\service\{ContributionService,Input,SiteService};
use app\common\service\member\MemberAuthService;
use Illuminate\Database\Eloquent\Builder;
use support\Db;
use InvalidArgumentException;
use app\cms\model\MemberSubmission;
use app\qa\model\{QaAnswer,QaQuestion};
final class QuestionService
{
    /** @param list<array{tmp?:mixed,name?:mixed,size?:mixed}> $uploads 会员附件（1.3.0），写入前整体校验；重放不再保存附件。 */
    public function ask(string $site,string $locale,string $member,array $input,array $uploads=[]): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$member=Input::id($member);
        $attachments=new AttachmentService();$files=$attachments->inspect($uploads);$stored=[];
        return $this->storing($attachments,$site,$stored,function()use($site,$locale,$member,$input,$attachments,$files,&$stored):array {
            (new SiteService())->lock($site);
            // 重放（同一会员、同一 request_key）只返回已有记录，不再补问答行：问题被彻底删除后重放不能让它复活。
            $replay=MemberSubmission::query()->where('site_id',$site)->where('member_id',$member)->where('request_key',Input::text($input['request_key']??'',64))->exists();
            $submission=(new ContributionService())->submit($site,$locale,$member,'question',$this->payload($input));
            // 含已软删除的行：主键复用投稿 id，同一投稿不能再插入一行。
            if(!$replay&&!QaQuestion::withTrashed()->where('id',$submission['id'])->exists()) {
                $now=Input::now();QaQuestion::query()->insert(['id'=>$submission['id'],'site_id'=>$site,'locale'=>$locale,'accepted_answer_id'=>null,'closed'=>false,'lock_version'=>0,'created_at'=>$now,'updated_at'=>$now]);
                $attachments->attach($site,$submission['id'],$submission['id'],$member,$files,$stored);
            }
            return $this->withState($submission,$this->state($site,$locale,$submission['id']));
        });
    }
    /** @param list<array{tmp?:mixed,name?:mixed,size?:mixed}> $uploads 会员附件（1.3.0），写入前整体校验；重放不再保存附件。 */
    public function answer(string $site,string $locale,string $member,string $question,array $input,array $uploads=[]): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$member=Input::id($member);$question=Input::id($question);
        $attachments=new AttachmentService();$files=$attachments->inspect($uploads);$stored=[];
        return $this->storing($attachments,$site,$stored,function()use($site,$locale,$member,$question,$input,$attachments,$files,&$stored):array {
            (new SiteService())->lock($site);$this->context($site,$locale);
            $service=new ContributionService();$payload=$this->payload($input);
            $key=Input::text($payload['request_key']??'',64);
            // Let the shared service authenticate and compare the full payload hash
            // before applying parent eligibility rules that only govern new writes.
            if(MemberSubmission::query()->where('site_id',$site)->where('member_id',$member)->where('request_key',$key)->exists()) {
                return $service->submit($site,$locale,$member,'answer',$payload,$question);
            }
            $state=$this->state($site,$locale,$question,true);$this->publishedQuestion($site,$locale,$question,true);
            if((bool)$state['closed'])throw new InvalidArgumentException('问题已关闭',409);
            $submission=$service->submit($site,$locale,$member,'answer',$payload,$question);
            // 新回答同时建问答侧状态行（隐藏、删除都记在这里）；前台只显示有未删除、未隐藏状态行的回答。
            $now=Input::now();QaAnswer::query()->insert(['id'=>$submission['id'],'question_id'=>$question,'site_id'=>$site,'locale'=>$locale,'hidden'=>false,'created_at'=>$now,'updated_at'=>$now]);
            $attachments->attach($site,$question,$submission['id'],$member,$files,$stored);
            return $submission;
        });
    }
    public function listing(string $site,string $locale,string $q='',int $page=1): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$this->context($site,$locale);$q=Input::text($q,100);$page=max(1,$page);
        $query=$this->publicQuestions($site,$locale);
        if($q!=='')$query->where('s.title','like','%'.$q.'%');
        $total=$query->count();$rows=$query->orderByDesc('q.pinned')->orderByDesc('s.id')->offset(($page-1)*20)->limit(20)->get();
        return ['items'=>array_map($this->publicQuestion(...),$rows->all()),'total'=>$total,'page'=>$page,'limit'=>20];
    }
    public function detail(string $site,string $locale,string $id): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);$this->context($site,$locale);
        $row=$this->publicQuestions($site,$locale)->where('s.id',$id)->first()??throw new InvalidArgumentException('问题不存在',404);
        $answers=MemberSubmission::query()->from('cms_member_submission as s')->join('qa_answer as a',function($j):void {$this->liveAnswer($j);$j->where('a.hidden',false);})->leftJoin('member as m','m.id','=','s.member_id')->where('s.site_id',$site)->where('s.locale',$locale)->where('s.kind','answer')->where('s.parent_id',$id)->where('s.status','published');
        $total=$answers->count();$rows=$answers->select($this->publicColumns())->orderBy('s.id')->limit(100)->get();
        $question=$this->publicQuestion($row);$items=array_map($this->publicSubmission(...),$rows->all());
        $files=(new AttachmentService())->forSubmissions($site,[$question['id'],...array_column($items,'id')]);
        foreach($items as &$item)$item['attachments']=$files[$item['id']]??[];unset($item);
        return $question+['attachments'=>$files[$question['id']]??[],'answers'=>$items,'answers_total'=>$total];
    }
    public function accept(string $site,string $locale,string $member,string $question,string $answer,int $questionVersion): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$member=Input::id($member);$question=Input::id($question);$answer=Input::id($answer);
        return Db::transaction(function()use($site,$locale,$member,$question,$answer,$questionVersion):array {
            (new SiteService())->lock($site);$this->context($site,$locale);
            if((new MemberAuthService())->activeMember((int)$member)===null)throw new InvalidArgumentException('会员不可用',403);
            $state=$this->state($site,$locale,$question,true);$submission=$this->publishedQuestion($site,$locale,$question,true);
            if((string)$submission['member_id']!==$member)throw new InvalidArgumentException('仅问题作者可以采纳回答',403);
            if((int)$state['lock_version']!==$questionVersion||(bool)$state['closed'])throw new InvalidArgumentException('问题状态已变更，请刷新',409);
            $candidate=MemberSubmission::query()->where('id',$answer)->where('site_id',$site)->where('locale',$locale)->where('parent_id',$question)->where('kind','answer')->where('status','published')->lockForUpdate()->first();
            if($candidate&&!QaAnswer::query()->where('id',$answer)->where('question_id',$question)->where('hidden',false)->lockForUpdate()->exists())$candidate=null;
            if(!$candidate)throw new InvalidArgumentException('回答不存在',404);
            $changed=QaQuestion::query()->where('id',$question)->where('site_id',$site)->where('lock_version',$questionVersion)->update(['accepted_answer_id'=>$answer,'closed'=>true,'lock_version'=>$questionVersion+1,'updated_at'=>Input::now()]);
            if($changed!==1)throw new InvalidArgumentException('问题状态已变更，请刷新',409);
            return $this->detail($site,$locale,$question);
        });
    }
    public function review(string $site,string $actor,string $submission,int $version,string $decision,string $reason=''): array
    {
        $site=Input::id($site);$actor=Input::id($actor);$submission=Input::id($submission);
        return Db::transaction(function()use($site,$actor,$submission,$version,$decision,$reason):array {
            (new SiteService())->lock($site);
            $service=new ContributionService();$record=$service->get($site,$submission);
            if(!in_array($record['kind'],['question','answer'],true))throw new InvalidArgumentException('不是问答投稿',422);
            $question=$record['kind']==='question'?$record['id']:$record['parent_id'];
            $state=$this->state($site,$record['locale'],Input::id($question),true);
            // 问答侧已删除的回答在回收站里处理，不再审核。
            if($record['kind']==='answer'&&!QaAnswer::query()->where('id',$submission)->where('question_id',$question)->lockForUpdate()->exists())throw new InvalidArgumentException('回答不存在',404);
            $result=$service->review($site,$actor,$submission,$version,$decision,$reason);
            if($result['status']==='hidden'&&$record['kind']==='answer'&&$state['accepted_answer_id']===$submission) {
                QaQuestion::query()->where('id',$question)->where('site_id',$site)->update(['accepted_answer_id'=>null,'closed'=>false,'lock_version'=>(int)$state['lock_version']+1,'updated_at'=>Input::now()]);
            }
            return $record['kind']==='question'?$this->withState($result,$this->state($site,$record['locale'],$question)):$result;
        });
    }
    /**
     * 在事务中执行写入；事务失败（回滚）时删除本次已落盘的附件文件（$stored 由写入闭包按引用追加），数据库与磁盘保持一致。
     * @param list<string> $stored
     */
    private function storing(AttachmentService $attachments,string $site,array &$stored,\Closure $write): array
    {
        try{return Db::transaction($write);}
        catch(\Throwable $e){$attachments->unlink($site,$stored);throw $e;}
    }
    private function payload(array $input): array { return array_intersect_key($input,array_flip(['request_key','title','body_html'])); }
    private function context(string $site,string $locale): void { $sites=new SiteService();$sites->get($site,true);$sites->language($site,$locale); }
    private function state(string $site,string $locale,string $question,bool $lock=false): array
    {
        $q=QaQuestion::query()->where('id',$question)->where('site_id',$site)->where('locale',$locale);if($lock)$q->lockForUpdate();
        return Input::row($q->first())??throw new InvalidArgumentException('问题不存在',404);
    }
    private function publishedQuestion(string $site,string $locale,string $question,bool $lock=false): array
    {
        $q=MemberSubmission::query()->where('id',$question)->where('site_id',$site)->where('locale',$locale)->where('kind','question')->where('status','published');if($lock)$q->lockForUpdate();
        return Input::row($q->first())??throw new InvalidArgumentException('问题不存在',404);
    }
    private function withState(array $submission,array $state): array { return $submission+['accepted_answer_id'=>$state['accepted_answer_id'],'closed'=>(bool)$state['closed'],'question_version'=>(int)$state['lock_version']]; }
    private function publicColumns(): array { return ['s.id','s.title','s.body_html','s.member_id','s.status','s.lock_version','s.created_at','m.nickname as author_name']; }
    private function publicQuestions(string $site,string $locale): Builder
    {
        return MemberSubmission::query()->from('cms_member_submission as s')->join('qa_question as q',function($j):void {$j->on('q.id','=','s.id')->on('q.site_id','=','s.site_id')->on('q.locale','=','s.locale')->whereNull('q.deleted_at');})->leftJoin('member as m','m.id','=','s.member_id')->where('s.site_id',$site)->where('s.locale',$locale)->where('s.kind','question')->where('s.status','published')->select([...$this->publicColumns(),'q.accepted_answer_id','q.closed','q.pinned','q.lock_version as question_version']);
    }
    /** 回答投稿与问答侧状态行的联表条件：同一投稿 id、站点、语言，状态行未删除。 */
    private function liveAnswer(\Illuminate\Database\Query\JoinClause $j): void { $j->on('a.id','=','s.id')->on('a.site_id','=','s.site_id')->on('a.locale','=','s.locale')->whereNull('a.deleted_at'); }
    private function publicSubmission(object $row): array
    {
        $row=Input::attributes($row);return ['id'=>(string)$row['id'],'title'=>(string)$row['title'],'body_html'=>(string)$row['body_html'],'member_id'=>(string)$row['member_id'],'author_name'=>(string)($row['author_name']?:'会员'),'status'=>(string)$row['status'],'lock_version'=>(int)$row['lock_version'],'created_at'=>$row['created_at']];
    }
    private function publicQuestion(object $row): array { $state=Input::attributes($row);return $this->publicSubmission($row)+['question_version'=>(int)$state['question_version'],'accepted_answer_id'=>$state['accepted_answer_id']===null?null:(string)$state['accepted_answer_id'],'closed'=>(bool)$state['closed'],'pinned'=>(bool)$state['pinned']]; }
}
