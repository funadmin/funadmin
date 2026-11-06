<?php
declare(strict_types=1);
namespace Tests\Qa;
require_once __DIR__.'/QaTestCase.php';
use app\cms\service\{ContributionPolicy,SiteService};
use support\Db;
final class QuestionServiceTest extends QaTestCase
{
    public function test_questions_start_pending_and_retry_uses_one_shared_id(): void
    {
        $input=['request_key'=>'question_fixed','member_id'=>'3','kind'=>'content','status'=>'published','accepted_answer_id'=>'8','closed'=>true];
        $one=$this->ask($input);$two=$this->ask($input);
        self::assertSame($one['id'],$two['id']);self::assertSame('1',$one['member_id']);self::assertSame('pending',$one['status']);self::assertFalse($one['closed']);self::assertSame(0,$one['question_version']);self::assertSame(1,Db::table('qa_question')->count());
        self::assertSame(0,$this->qa->listing($this->siteId,'zh-cn')['total']);$this->rejected(fn()=>$this->qa->detail($this->siteId,'zh-cn',$one['id']),404);
    }
    public function test_public_projection_only_returns_published_answers_without_private_fields(): void
    {
        $q=$this->question();$pending=$this->answer($q['id']);$a=$this->publish($this->answer($q['id'],'answer_0002'));
        $detail=$this->qa->detail($this->siteId,'zh-cn',$q['id']);
        self::assertSame('Question author',$detail['author_name']);self::assertSame(1,$detail['answers_total']);self::assertCount(1,$detail['answers']);self::assertSame($a['id'],$detail['answers'][0]['id']);
        foreach([$detail,$detail['answers'][0]] as $row)foreach(['review_reason','reviewer_admin_id','request_key','payload_hash','email','mobile','password','data','result'] as $key)self::assertArrayNotHasKey($key,$row);
        (new ContributionPolicy())->save($this->siteId,'1',['enabled'=>false,'model_codes'=>[]]);self::assertSame(1,$this->qa->listing($this->siteId,'zh-cn')['total']);
        $this->rejected(fn()=>$this->ask(),403);
    }
    public function test_accept_requires_owner_and_matching_published_answer_with_question_version(): void
    {
        $q=$this->question();$a=$this->publish($this->answer($q['id']));$other=$this->question();$wrong=$this->publish($this->answer($other['id'],'answer_other'));$pending=$this->answer($q['id'],'answer_pending');
        $this->rejected(fn()=>$this->qa->accept($this->siteId,'zh-cn','3',$q['id'],$a['id'],0),403);
        $this->rejected(fn()=>$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$wrong['id'],0),404);
        $this->rejected(fn()=>$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$pending['id'],0),404);
        $closed=$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0);
        self::assertTrue($closed['closed']);self::assertSame($a['id'],$closed['accepted_answer_id']);self::assertSame(1,$closed['question_version']);
        $this->rejected(fn()=>$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0),409);
        $this->rejected(fn()=>$this->answer($q['id'],'answer_closed'),409);
    }
    public function test_identical_answer_retry_after_acceptance_returns_current_record_and_conflicts_still_fail(): void
    {
        $q=$this->question();$a=$this->publish($this->answer($q['id']));$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0);
        $before=Db::table('cms_member_submission')->count();
        try{$retry=$this->answer($q['id']);}catch(\InvalidArgumentException $e){self::fail('Committed answer retry must return its record: '.$e->getCode().' '.$e->getMessage());}
        self::assertSame($a['id'],$retry['id']);self::assertSame('published',$retry['status']);self::assertSame(1,$retry['lock_version']);self::assertSame($before,Db::table('cms_member_submission')->count());
        $this->rejected(fn()=>$this->qa->answer($this->siteId,'zh-cn','2',$q['id'],['request_key'=>'answer_0001','body_html'=>'<p>Changed body</p>']),409);
        $this->rejected(fn()=>$this->answer($q['id'],'new_after_close'),409);
    }
    public function test_identical_answer_retry_after_parent_hide_returns_record_but_new_keys_and_parent_conflicts_fail(): void
    {
        $q=$this->question();$a=$this->answer($q['id']);$other=$this->question();
        $this->qa->review($this->siteId,'1',$q['id'],$q['lock_version'],'hide','Hide parent');
        try{$retry=$this->answer($q['id']);}catch(\InvalidArgumentException $e){self::fail('Hidden parent must not block committed answer replay: '.$e->getCode().' '.$e->getMessage());}
        self::assertSame($a['id'],$retry['id']);self::assertSame('pending',$retry['status']);
        $this->rejected(fn()=>$this->answer($q['id'],'new_after_hide'),404);
        $this->rejected(fn()=>$this->qa->answer($this->siteId,'zh-cn','2',$q['id'],['request_key'=>'answer_0001','body_html'=>'<p>Changed body</p>']),409);
        $this->rejected(fn()=>$this->answer($other['id']),409);
        self::assertSame(1,Db::table('cms_member_submission')->where('kind','answer')->count());
    }
    public function test_answer_replay_keeps_member_site_locale_and_parent_isolation(): void
    {
        $q=$this->question();$a=$this->publish($this->answer($q['id']));$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0);
        $input=['request_key'=>'answer_0001','body_html'=>'<p>Answer body</p>'];
        $this->rejected(fn()=>$this->qa->answer($this->siteId,'zh-cn','3',$q['id'],$input),409);
        $other=$this->site('replay_other','replay-other.test')['id'];$this->rejected(fn()=>$this->qa->answer($other,'zh-cn','2',$q['id'],$input),404);
        $this->rejected(fn()=>$this->qa->answer($this->siteId,'en-us','2',$q['id'],$input),404);
        (new SiteService())->saveLocale($this->siteId,['locale'=>'en-us','url_prefix'=>'en']);$this->rejected(fn()=>$this->qa->answer($this->siteId,'en-us','2',$q['id'],$input),409);
        Db::table('cms_site_locale')->where('site_id',$this->siteId)->where('locale','en-us')->update(['status'=>0]);$this->rejected(fn()=>$this->qa->answer($this->siteId,'en-us','2',$q['id'],$input),404);
        Db::table('member')->where('id',2)->update(['status'=>0]);$this->rejected(fn()=>$this->answer($q['id']),401);
        Db::table('member')->where('id',2)->update(['status'=>1,'deleted_at'=>gmdate('Y-m-d H:i:s')]);$this->rejected(fn()=>$this->answer($q['id']),401);
        Db::table('member')->where('id',2)->update(['deleted_at'=>null]);Db::table('cms_site')->where('id',$this->siteId)->update(['status'=>0]);$this->rejected(fn()=>$this->answer($q['id']),404);
    }
    public function test_hiding_accepted_answer_clears_acceptance_reopens_and_preserves_review_cas(): void
    {
        $q=$this->question();$a=$this->publish($this->answer($q['id']));$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0);
        $this->rejected(fn()=>$this->qa->review($this->siteId,'1',$a['id'],0,'hide','bad'),409);
        self::assertTrue($this->qa->detail($this->siteId,'zh-cn',$q['id'])['closed']);
        $this->qa->review($this->siteId,'1',$a['id'],$a['lock_version'],'hide','Removed answer');
        $detail=$this->qa->detail($this->siteId,'zh-cn',$q['id']);self::assertNull($detail['accepted_answer_id']);self::assertFalse($detail['closed']);self::assertSame(2,$detail['question_version']);self::assertSame(0,$detail['answers_total']);
        self::assertSame('pending',$this->answer($q['id'],'answer_reopen')['status']);
    }
    public function test_hiding_question_removes_public_question_and_all_replies(): void
    {
        $q=$this->question();$this->publish($this->answer($q['id']));$this->qa->review($this->siteId,'1',$q['id'],$q['lock_version'],'hide','Removed question');
        self::assertSame(0,$this->qa->listing($this->siteId,'zh-cn')['total']);$this->rejected(fn()=>$this->qa->detail($this->siteId,'zh-cn',$q['id']),404);
        $this->rejected(fn()=>$this->answer($q['id'],'answer_hidden'),404);
    }
    public function test_site_locale_and_active_member_boundaries_apply_to_reads_and_writes(): void
    {
        $q=$this->question();$a=$this->publish($this->answer($q['id']));$other=$this->site('other','other.test')['id'];
        $this->rejected(fn()=>$this->qa->detail($other,'zh-cn',$q['id']),404);$this->rejected(fn()=>$this->qa->answer($other,'zh-cn','2',$q['id'],['request_key'=>'cross_site','body_html'=>'x']),404);
        $this->rejected(fn()=>$this->qa->detail($this->siteId,'en-us',$q['id']),404);
        (new SiteService())->saveLocale($this->siteId,['locale'=>'en-us','url_prefix'=>'en']);$this->rejected(fn()=>$this->qa->accept($this->siteId,'en-us','1',$q['id'],$a['id'],0),404);
        Db::table('member')->where('id',1)->update(['status'=>0]);$this->rejected(fn()=>$this->qa->accept($this->siteId,'zh-cn','1',$q['id'],$a['id'],0),403);
        Db::table('cms_site')->where('id',$this->siteId)->update(['status'=>0]);$this->rejected(fn()=>$this->qa->listing($this->siteId,'zh-cn'),404);
    }
    public function test_listing_search_paginates_twenty_and_detail_caps_answers_with_total(): void
    {
        $q=$this->question();$seed=(array)Db::table('cms_member_submission')->where('id',$q['id'])->first();$now=gmdate('Y-m-d H:i:s');
        for($i=1;$i<=22;$i++) { $id=Db::table('cms_member_submission')->insertGetId(array_replace($seed,['id'=>null,'title'=>'Extra question '.$i,'request_key'=>'fixture_question_'.$i]));Db::table('qa_question')->insert(['id'=>$id,'site_id'=>$this->siteId,'locale'=>'zh-cn','created_at'=>$now,'updated_at'=>$now]); }
        for($i=1;$i<=105;$i++)Db::table('cms_member_submission')->insert(array_replace($seed,['id'=>null,'kind'=>'answer','parent_id'=>$q['id'],'request_key'=>'fixture_answer_'.$i]));
        $list=$this->qa->listing($this->siteId,'zh-cn','Extra',2);self::assertSame(22,$list['total']);self::assertSame(2,$list['page']);self::assertSame(20,$list['limit']);self::assertCount(2,$list['items']);
        $detail=$this->qa->detail($this->siteId,'zh-cn',$q['id']);self::assertSame(105,$detail['answers_total']);self::assertCount(100,$detail['answers']);
    }
    public function test_review_rejects_content_and_admin_without_cms_site_permission(): void
    {
        $q=$this->ask();$this->rejected(fn()=>$this->qa->review($this->siteId,'9',$q['id'],0,'publish'),403);
        Db::table('cms_member_submission')->where('id',$q['id'])->update(['kind'=>'content']);$this->rejected(fn()=>$this->qa->review($this->siteId,'1',$q['id'],0,'publish'),422);
    }
}
