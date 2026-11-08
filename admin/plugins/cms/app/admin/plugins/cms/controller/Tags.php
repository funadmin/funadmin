<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\Input;
use app\cms\service\TemplateTagService;
use app\cms\tags\BuiltinTags;
use app\cms\template\RenderContext;
#[RouteGroup('/admin/plugins/cms/tags')]
final class Tags extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new TemplateTagService())->listing($this->site('cms:tag:list'),$this->locale())); }
    #[Get('/providers')]
    public function providers(): Response { return $this->action(fn()=>(new BuiltinTags())->registry(new RenderContext($this->site('cms:tag:list'),$this->locale()))->definitions()); }
    /** 返回当前站点、语言的标签参数选项，仅需模板标签查看权限。 */
    #[Get('/options')]
    public function options(): Response { return $this->action(fn()=>(new TemplateTagService())->editorOptions($this->site('cms:tag:list'),$this->locale())); }
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('tag',fn($site)=>(new TemplateTagService())->save($site,($this->input()['tag_locale']??'')===''?'':Input::locale($this->input()['tag_locale']),$this->input(),$this->actor()))); }
    #[Post('/publish')]
    public function publish(): Response { return $this->action(function(): bool {(new TemplateTagService())->publish($this->site('cms:tag:publish'),Input::id($this->input()['id']??''),(int)($this->input()['version']??0));return true;}); }
    #[Get('/preview')]
    public function preview(): Response { return $this->action(fn()=>['html'=>(new TemplateTagService())->preview($this->site('cms:tag:preview'),$this->locale(),Input::id($this->input()['id']??''),(int)($this->input()['version']??0))]); }
}
