<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;
use app\cms\service\Input;
use app\qa\service\{AdminQuestionService,AttachmentService};
use app\qa\service\QaRecycleBin;
use app\admin\plugins\qa\service\AdminEndpoint;
use app\common\controller\AdminController;
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
/** 后台问题管理：列表、详情、附件下载、关闭/重新开放、置顶、删除，以及问题回收站（列表、恢复、彻底删除）。 */
#[RouteGroup('/admin/plugins/qa/questions')]
final class Questions extends AdminController
{
    use AdminEndpoint;
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new AdminQuestionService())->listing($this->site('cms:submission:list'),$this->locale(),Input::text($this->input()['status']??'',20),Input::text($this->input()['q']??'',100),$this->page())); }
    #[Get('/detail')]
    public function detail(): Response { return $this->action(fn()=>(new AdminQuestionService())->detail($this->site('cms:submission:list'),$this->locale(),$this->id())); }
    /** 下载问题或回答的会员附件（1.3.0）：本站未删除的附件；鉴权同详情（cms:submission:list）。 */
    #[Get('/attachment')]
    public function attachment(): Response { return $this->download(fn()=>(new AttachmentService())->adminFile($this->site('cms:submission:list'),$this->id())); }
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new AdminQuestionService())->status($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->version(),$this->flag('closed'))); }
    #[Post('/pin')]
    public function pin(): Response { return $this->action(fn()=>(new AdminQuestionService())->pin($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->version(),$this->flag('pinned'))); }
    #[Post('/delete')]
    public function delete(): Response { return $this->action(fn()=>(new AdminQuestionService())->delete($this->site('cms:submission:review'),$this->locale(),$this->id())); }
    #[Get('/recycle')]
    public function recycle(): Response { return $this->action(fn()=>(new QaRecycleBin())->trashedQuestions($this->site('cms:submission:list'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    #[Post('/restore')]
    public function restore(): Response { return $this->action(fn()=>(new QaRecycleBin())->restoreQuestions($this->site('cms:submission:review'),$this->input()['ids']??null)); }
    #[Post('/destroy')]
    public function destroy(): Response { return $this->action(fn()=>(new QaRecycleBin())->destroyQuestions($this->site('cms:submission:review'),$this->input()['ids']??null)); }
}
