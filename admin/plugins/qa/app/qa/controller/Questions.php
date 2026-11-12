<?php
declare(strict_types=1);
namespace app\qa\controller;
use app\cms\controller\MemberEndpoint;
use app\cms\service\{ContributionPolicy,Input};
use app\qa\service\{AttachmentService,QuestionService};
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
#[RouteGroup('/qa/api')]
final class Questions extends MemberEndpoint
{
    #[Get('/context')]
    public function context(): Response { return $this->action(fn()=>(new ContributionPolicy())->context()); }
    #[Get('/questions')]
    public function index(): Response { return $this->action(fn()=>(new QuestionService())->listing($this->site(),$this->locale(),Input::text($this->input()['q']??'',100),$this->page())); }
    #[Get('/questions/{id}')]
    public function detail(string $id): Response { return $this->action(fn()=>(new QuestionService())->detail($this->site(),$this->locale(),Input::id($id))); }
    #[Post('/questions')]
    public function ask(): Response { return $this->action(fn()=>(new QuestionService())->ask($this->site(),$this->locale(),$this->member(),$this->input(),$this->uploads())); }
    #[Post('/questions/{id}/answers')]
    public function answer(string $id): Response { return $this->action(fn()=>(new QuestionService())->answer($this->site(),$this->locale(),$this->member(),Input::id($id),$this->input(),$this->uploads())); }
    /** 会员下载问答附件（1.3.0）：需要登录；公开内容任何会员可下载，未公开的只有上传者本人。失败时返回与其他接口相同的 JSON 错误。 */
    #[Get('/attachments/{id}')]
    public function attachment(string $id): Response
    {
        $file=null;$error=$this->action(function()use($id,&$file){$file=(new AttachmentService())->memberFile($this->site(),$this->member(),Input::id($id));return null;});
        return $file===null?$error:AttachmentService::response($file);
    }
    #[Post('/questions/{id}/accept')]
    public function accept(string $id): Response { return $this->action(fn()=>(new QuestionService())->accept($this->site(),$this->locale(),$this->member(),Input::id($id),Input::id(Input::text($this->input()['answer_id']??'')),$this->version())); }
    /** multipart 表单中的附件（attachments 或 attachments[]）；JSON 请求没有附件。 */
    private function uploads(): array { return AttachmentService::uploads($this->request->file(AttachmentService::FIELD)); }
    private function site(): string { return Input::id(Input::text($this->input()['site_id']??'')); }
    private function locale(): string { return Input::locale($this->input()['locale']??'zh-cn'); }
    private function page(): int { $value=Input::text($this->input()['page']??'1',10);if(!preg_match('/^[1-9][0-9]{0,8}$/D',$value))throw new \InvalidArgumentException('页码无效',422);return (int)$value; }
    private function version(): int { $value=Input::text($this->input()['question_version']??'',10);if(!preg_match('/^(?:0|[1-9][0-9]{0,9})$/D',$value)||(int)$value>2147483647)throw new \InvalidArgumentException('问题版本无效',422);return (int)$value; }
}
