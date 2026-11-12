<?php
declare(strict_types=1);
namespace app\admin\plugins\qa\controller;
use app\qa\service\AdminQuestionService;
use app\qa\service\QaRecycleBin;
use app\admin\plugins\qa\service\AdminEndpoint;
use app\common\controller\AdminController;
use support\Response;
use support\annotation\route\{Get,Post,RouteGroup};
/** 后台回答管理：隐藏/显示、删除，以及回答回收站（列表、恢复、彻底删除）。 */
#[RouteGroup('/admin/plugins/qa/answers')]
final class Answers extends AdminController
{
    use AdminEndpoint;
    #[Post('/visibility')]
    public function visibility(): Response { return $this->action(fn()=>(new AdminQuestionService())->answerVisibility($this->site('cms:submission:review'),$this->locale(),$this->id(),$this->flag('hidden'))); }
    #[Post('/delete')]
    public function delete(): Response { return $this->action(fn()=>(new AdminQuestionService())->deleteAnswer($this->site('cms:submission:review'),$this->locale(),$this->id())); }
    #[Get('/recycle')]
    public function recycle(): Response { return $this->action(fn()=>(new QaRecycleBin())->trashedAnswers($this->site('cms:submission:list'),$this->locale(),$this->input()['page']??1,$this->input()['pageSize']??20)); }
    #[Post('/restore')]
    public function restore(): Response { return $this->action(fn()=>(new QaRecycleBin())->restoreAnswers($this->site('cms:submission:review'),$this->input()['ids']??null)); }
    #[Post('/destroy')]
    public function destroy(): Response { return $this->action(fn()=>(new QaRecycleBin())->destroyAnswers($this->site('cms:submission:review'),$this->input()['ids']??null)); }
}
