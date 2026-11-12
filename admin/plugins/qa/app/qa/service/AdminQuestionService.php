<?php
declare(strict_types=1);
namespace app\qa\service;
use app\cms\model\MemberSubmission;
use app\cms\service\{ContributionService,Input,SiteService};
use app\qa\model\{QaAnswer,QaQuestion};
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use support\Db;
/**
 * 后台问题管理（1.2.0）：列表、详情（含全部回答）、关闭/重新开放、置顶，删除问题，隐藏/显示与删除回答。
 *
 * 只改问答侧的状态行（qa_question / qa_answer）；问题和回答的正文、审核状态在 CMS 会员投稿表，这里只读：
 * CMS 没有提供删除投稿的接口，问答不擅自删除 CMS 数据。删除问题不级联回答：回答按问题是否存在过滤，
 * 恢复问题后回答原样回来。所有写操作先锁站点行，再锁问答状态行。
 */
final class AdminQuestionService
{
    public const PAGE_SIZE = 20;
    public const ANSWER_LIMIT = 100;

    public function listing(string $site,string $locale,string $status='',string $q='',int $page=1): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$q=Input::text($q,100);$page=max(1,min(10000,$page));
        if($status!==''&&!in_array($status,ContributionService::STATUSES,true))throw new InvalidArgumentException('投稿状态无效',422);
        $query=$this->questions($site,$locale);
        if($status!=='')$query->where('s.status',$status);
        if($q!=='')$query->where('s.title','like','%'.$q.'%');
        $total=(clone $query)->count();
        $rows=$query->orderByDesc('q.pinned')->orderByDesc('s.id')->offset(($page-1)*self::PAGE_SIZE)->limit(self::PAGE_SIZE)->get()->all();
        $counts=$this->answerCounts(array_map(static fn($row)=>(string)Input::attributes($row)['id'],$rows));
        $items=[];foreach($rows as $row){$item=$this->question($row);$items[]=array_diff_key($item,['body_html'=>true,'attachments'=>true])+['answers_count'=>$counts[$item['id']]??0];}
        return ['items'=>$items,'total'=>$total,'page'=>$page,'limit'=>self::PAGE_SIZE];
    }

    public function detail(string $site,string $locale,string $id): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);
        $row=$this->questions($site,$locale)->where('s.id',$id)->first()??throw new InvalidArgumentException('问题不存在',404);
        $answers=$this->answers($site,$locale)->where('s.parent_id',$id);
        $total=(clone $answers)->count();
        $rows=$answers->orderBy('s.id')->limit(self::ANSWER_LIMIT)->get()->all();
        $question=$this->question($row);
        return $question+['answers'=>array_map(fn($answer)=>$this->answer($answer,$question['accepted_answer_id']),$rows),'answers_total'=>$total];
    }

    /** 关闭或重新开放问题（问答状态，不改 CMS 审核状态），按问题版本做乐观锁。 */
    public function status(string $site,string $locale,string $id,int $version,bool $closed): array
    {
        return $this->changeState($site,$locale,$id,$version,['closed'=>$closed]);
    }

    public function pin(string $site,string $locale,string $id,int $version,bool $pinned): array
    {
        return $this->changeState($site,$locale,$id,$version,['pinned'=>$pinned]);
    }

    /** 软删除问题：只写 qa_question.deleted_at；CMS 投稿与回答状态行保持原样。 */
    public function delete(string $site,string $locale,string $id): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);
        return Db::transaction(function()use($site,$locale,$id):array {
            (new SiteService())->lock($site);
            $row=QaQuestion::query()->where('id',$id)->where('site_id',$site)->where('locale',$locale)->lockForUpdate()->first()??throw new InvalidArgumentException('问题不存在',404);
            $row->delete();
            return ['count'=>1];
        });
    }

    /** 隐藏或显示回答（问答侧状态）；隐藏已采纳的回答时取消采纳并重新开放问题。 */
    public function answerVisibility(string $site,string $locale,string $id,bool $hidden): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);
        return Db::transaction(function()use($site,$locale,$id,$hidden):array {
            (new SiteService())->lock($site);
            [$answer,$question]=$this->lockedAnswer($site,$locale,$id);
            if((bool)$answer->hidden!==$hidden){
                QaAnswer::query()->where('id',$id)->update(['hidden'=>$hidden,'updated_at'=>Input::now()]);
                if($hidden)$this->unaccept($question,$id);
            }
            return $this->detail($site,$locale,(string)$question->getRawOriginal('id'));
        });
    }

    /** 软删除回答（问答侧状态行），进入回答回收站；已采纳时取消采纳并重新开放问题。 */
    public function deleteAnswer(string $site,string $locale,string $id): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);
        return Db::transaction(function()use($site,$locale,$id):array {
            (new SiteService())->lock($site);
            [$answer,$question]=$this->lockedAnswer($site,$locale,$id);
            $answer->delete();$this->unaccept($question,$id);
            return ['count'=>1];
        });
    }

    private function changeState(string $site,string $locale,string $id,int $version,array $changes): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);$id=Input::id($id);
        return Db::transaction(function()use($site,$locale,$id,$version,$changes):array {
            (new SiteService())->lock($site);
            $row=QaQuestion::query()->where('id',$id)->where('site_id',$site)->where('locale',$locale)->lockForUpdate()->first()??throw new InvalidArgumentException('问题不存在',404);
            if((int)$row->lock_version!==$version)throw new InvalidArgumentException('问题状态已变更，请刷新',409);
            QaQuestion::query()->where('id',$id)->where('lock_version',$version)->update($changes+['lock_version'=>$version+1,'updated_at'=>Input::now()]);
            return $this->detail($site,$locale,$id);
        });
    }

    /** @return array{0:QaAnswer,1:QaQuestion} 未删除的回答状态行及其未删除的问题（都加锁） */
    private function lockedAnswer(string $site,string $locale,string $id): array
    {
        $answer=QaAnswer::query()->where('id',$id)->where('site_id',$site)->where('locale',$locale)->lockForUpdate()->first()??throw new InvalidArgumentException('回答不存在',404);
        $question=QaQuestion::query()->where('id',$answer->question_id)->where('site_id',$site)->lockForUpdate()->first()??throw new InvalidArgumentException('回答不存在',404);
        return [$answer,$question];
    }

    private function unaccept(QaQuestion $question,string $answer): void
    {
        if((string)$question->accepted_answer_id!==$answer)return;
        $version=(int)$question->lock_version;
        QaQuestion::query()->where('id',$question->getRawOriginal('id'))->update(['accepted_answer_id'=>null,'closed'=>false,'lock_version'=>$version+1,'updated_at'=>Input::now()]);
    }

    /** 未删除问题（问答行未删除，CMS 投稿未删除），带提问会员。 */
    private function questions(string $site,string $locale): Builder
    {
        return MemberSubmission::query()->from('cms_member_submission as s')
            ->join('qa_question as q',function($j):void {$j->on('q.id','=','s.id')->on('q.site_id','=','s.site_id')->on('q.locale','=','s.locale')->whereNull('q.deleted_at');})
            ->leftJoin('member as m','m.id','=','s.member_id')->where('s.site_id',$site)->where('s.locale',$locale)->where('s.kind','question')
            ->select(['s.id','s.title','s.body_html','s.member_id','s.status','s.lock_version','s.review_reason','s.data','s.created_at','m.username','m.nickname','q.accepted_answer_id','q.closed','q.pinned','q.lock_version as question_version']);
    }

    /** 未删除的回答（问答状态行未删除，CMS 投稿未删除），含隐藏的与任何审核状态的。 */
    private function answers(string $site,string $locale): Builder
    {
        return MemberSubmission::query()->from('cms_member_submission as s')
            ->join('qa_answer as a',function($j):void {$j->on('a.id','=','s.id')->on('a.site_id','=','s.site_id')->on('a.locale','=','s.locale')->whereNull('a.deleted_at');})
            ->leftJoin('member as m','m.id','=','s.member_id')->where('s.site_id',$site)->where('s.locale',$locale)->where('s.kind','answer')
            ->select(['s.id','s.parent_id','s.body_html','s.member_id','s.status','s.lock_version','s.review_reason','s.data','s.created_at','m.username','m.nickname','a.hidden']);
    }

    /** @param list<string> $ids @return array<string,int> 每个问题未删除的回答数（一次分组查询） */
    private function answerCounts(array $ids): array
    {
        if($ids===[])return [];
        $counts=[];
        foreach(QaAnswer::query()->toBase()->whereIn('question_id',$ids)->whereNull('deleted_at')->selectRaw('question_id, count(*) as aggregate')->groupBy('question_id')->get() as $row)$counts[(string)$row->question_id]=(int)$row->aggregate;
        return $counts;
    }

    private function member(array $row): array { return ['id'=>(string)$row['member_id'],'username'=>(string)($row['username']??''),'nickname'=>(string)($row['nickname']??'')]; }

    /** 投稿 data 中的附件（会员附件由后续版本写入；没有时为空数组）。 */
    private function attachments(array $row): array
    {
        $data=is_string($row['data']??null)?json_decode($row['data'],true):($row['data']??[]);
        $files=is_array($data)&&is_array($data['attachments']??null)?$data['attachments']:[];
        return array_values(array_filter($files,'is_array'));
    }

    private function question(object $row): array
    {
        $row=Input::attributes($row);
        return ['id'=>(string)$row['id'],'title'=>(string)$row['title'],'body_html'=>(string)$row['body_html'],'status'=>(string)$row['status'],'lock_version'=>(int)$row['lock_version'],'review_reason'=>(string)($row['review_reason']??''),'created_at'=>$row['created_at'],'member'=>$this->member($row),'attachments'=>$this->attachments($row),'accepted_answer_id'=>$row['accepted_answer_id']===null?null:(string)$row['accepted_answer_id'],'closed'=>(bool)$row['closed'],'pinned'=>(bool)$row['pinned'],'question_version'=>(int)$row['question_version']];
    }

    private function answer(object $row,?string $accepted): array
    {
        $row=Input::attributes($row);
        return ['id'=>(string)$row['id'],'body_html'=>(string)$row['body_html'],'status'=>(string)$row['status'],'lock_version'=>(int)$row['lock_version'],'review_reason'=>(string)($row['review_reason']??''),'created_at'=>$row['created_at'],'member'=>$this->member($row),'attachments'=>$this->attachments($row),'hidden'=>(bool)$row['hidden'],'accepted'=>$accepted===(string)$row['id']];
    }
}
