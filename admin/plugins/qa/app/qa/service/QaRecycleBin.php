<?php
declare(strict_types=1);
namespace app\qa\service;
use app\cms\model\MemberSubmission;
use app\cms\service\{Input,RecycleBin,SiteService};
use app\qa\model\{QaAnswer,QaQuestion};
use InvalidArgumentException;
use support\Db;
/**
 * 问答回收站（1.2.0）：问题与回答各自列表、恢复、彻底删除。
 *
 * - 只处理问答侧状态行。CMS 没有删除会员投稿的接口，问答不删除也不恢复 CMS 投稿；彻底删除后 CMS 投稿原样保留
 *   （问答页面不再显示它，因为没有问答状态行）。
 * - 恢复问题：对应的 CMS 投稿已删除或不存在（或已不是问题投稿）时返回 409，问题留在回收站。
 * - 回答回收站只列出问题未删除的回答；恢复回答：问题已删除或不存在、或 CMS 回答投稿已删除或不存在时返回 409。
 * - 彻底删除问题：删除问题行和它的全部回答状态行（含已删除的）；彻底删除回答：只删除该回答状态行。
 * - ids 规则与 CMS 回收站一致（1–100 个、去重，任何一个不在回收站整批 404）；409 时整批回滚。
 */
final class QaRecycleBin
{
    public function trashedQuestions(string $site,string $locale,mixed $page,mixed $pageSize): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);[$page,$size]=RecycleBin::paging($page,$pageSize);
        $query=QaQuestion::onlyTrashed()->where('site_id',$site)->where('locale',$locale);
        $total=(clone $query)->count();
        $rows=$query->orderByDesc('deleted_at')->orderByDesc('id')->offset(($page-1)*$size)->limit($size)->get()->all();
        $ids=array_map(static fn($row)=>(string)$row->getRawOriginal('id'),$rows);
        $submissions=$this->submissions($site,$ids);
        $counts=AdminQuestionService::answerCounts($ids);
        $list=[];
        foreach($rows as $row){
            $id=(string)$row->getRawOriginal('id');$submission=$submissions[$id]??null;$live=$submission!==null&&$submission['kind']==='question'&&$submission['deleted_at']===null;
            $list[]=['id'=>$id,'title'=>(string)($submission['title']??''),'member_id'=>$submission===null?null:(string)$submission['member_id'],'status'=>(string)($submission['status']??''),'answers_count'=>$counts[$id]??0,'submission_missing'=>!$live,'deleted_at'=>(string)$row->getRawOriginal('deleted_at')];
        }
        return RecycleBin::result($list,$total,$page,$size);
    }

    public function restoreQuestions(string $site,mixed $ids): array
    {
        $site=Input::id($site);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($site,$ids):array {
            (new SiteService())->lock($site);
            $rows=RecycleBin::locked(QaQuestion::class,$site,$ids,'问题不在回收站');
            foreach($rows as $row){
                $id=(string)$row->getRawOriginal('id');
                $live=MemberSubmission::query()->where('id',$id)->where('site_id',$site)->where('locale',$row->locale)->where('kind','question')->sharedLock()->exists();
                if(!$live)throw new InvalidArgumentException('问题对应的 CMS 投稿已删除或不存在，无法恢复',409);
                $row->restore();
            }
            return ['count'=>count($rows),'skipped'=>0];
        });
    }

    public function destroyQuestions(string $site,mixed $ids): array
    {
        $site=Input::id($site);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($site,$ids):array {
            (new SiteService())->lock($site);
            RecycleBin::locked(QaQuestion::class,$site,$ids,'问题不在回收站');
            QaAnswer::withTrashed()->where('site_id',$site)->whereIn('question_id',$ids)->forceDelete();
            $count=QaQuestion::onlyTrashed()->where('site_id',$site)->whereIn('id',$ids)->forceDelete();
            return ['count'=>(int)$count];
        });
    }

    public function trashedAnswers(string $site,string $locale,mixed $page,mixed $pageSize): array
    {
        $site=Input::id($site);$locale=Input::locale($locale);[$page,$size]=RecycleBin::paging($page,$pageSize);
        $query=QaAnswer::onlyTrashed()->where('site_id',$site)->where('locale',$locale)->whereIn('question_id',QaQuestion::query()->select('id')->where('site_id',$site));
        $total=(clone $query)->count();
        $rows=$query->orderByDesc('deleted_at')->orderByDesc('id')->offset(($page-1)*$size)->limit($size)->get()->all();
        $ids=array_map(static fn($row)=>(string)$row->getRawOriginal('id'),$rows);
        $questions=array_values(array_unique(array_map(static fn($row)=>(string)$row->getRawOriginal('question_id'),$rows)));
        $submissions=$this->submissions($site,array_merge($ids,$questions));
        $list=[];
        foreach($rows as $row){
            $id=(string)$row->getRawOriginal('id');$question=(string)$row->getRawOriginal('question_id');$submission=$submissions[$id]??null;
            $live=$submission!==null&&$submission['kind']==='answer'&&$submission['deleted_at']===null;
            $list[]=['id'=>$id,'question_id'=>$question,'question_title'=>(string)($submissions[$question]['title']??''),'excerpt'=>mb_substr(trim(preg_replace('/\s+/u',' ',strip_tags((string)($submission['body_html']??'')))??''),0,80),'member_id'=>$submission===null?null:(string)$submission['member_id'],'status'=>(string)($submission['status']??''),'submission_missing'=>!$live,'deleted_at'=>(string)$row->getRawOriginal('deleted_at')];
        }
        return RecycleBin::result($list,$total,$page,$size);
    }

    public function restoreAnswers(string $site,mixed $ids): array
    {
        $site=Input::id($site);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($site,$ids):array {
            (new SiteService())->lock($site);
            $rows=RecycleBin::locked(QaAnswer::class,$site,$ids,'回答不在回收站');
            foreach($rows as $row){
                $id=(string)$row->getRawOriginal('id');$question=(string)$row->getRawOriginal('question_id');
                if(!QaQuestion::query()->where('id',$question)->where('site_id',$site)->sharedLock()->exists())throw new InvalidArgumentException('回答所属的问题已删除，请先恢复问题',409);
                $live=MemberSubmission::query()->where('id',$id)->where('site_id',$site)->where('locale',$row->locale)->where('kind','answer')->where('parent_id',$question)->sharedLock()->exists();
                if(!$live)throw new InvalidArgumentException('回答对应的 CMS 投稿已删除或不存在，无法恢复',409);
                $row->restore();
            }
            return ['count'=>count($rows),'skipped'=>0];
        });
    }

    public function destroyAnswers(string $site,mixed $ids): array
    {
        $site=Input::id($site);$ids=RecycleBin::ids($ids);
        return Db::transaction(function()use($site,$ids):array {
            (new SiteService())->lock($site);
            RecycleBin::locked(QaAnswer::class,$site,$ids,'回答不在回收站');
            $count=QaAnswer::onlyTrashed()->where('site_id',$site)->whereIn('id',$ids)->forceDelete();
            return ['count'=>(int)$count];
        });
    }

    /**
     * 回收站展示用的 CMS 投稿（含 CMS 已删除的，用于判断能否恢复）。只读。
     * @param list<string> $ids
     * @return array<string,array>
     */
    private function submissions(string $site,array $ids): array
    {
        if($ids===[])return [];
        $result=[];
        foreach(MemberSubmission::withTrashed()->where('site_id',$site)->whereIn('id',$ids)->get(['id','kind','title','body_html','member_id','status','deleted_at']) as $row){$item=Input::attributes($row);$result[(string)$item['id']]=$item;}
        return $result;
    }
}
