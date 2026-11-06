<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\ModelService;
use app\cms\service\SiteAccess;
#[RouteGroup('/admin/plugin/cms/models')]
final class Models extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(function(): array {$this->site('cms:model:list');return (new ModelService())->listing();}); }
    #[Get('/bindings')]
    public function bindings(): Response { return $this->action(fn()=>\app\cms\service\Input::rows(\support\Db::table('cms_site_model')->where('site_id',$this->site('cms:model:list'))->get())); }
    #[Post('/save')]
    public function save(): Response { return $this->action(function(): array {(new SiteAccess())->super($this->actor());return (new ModelService())->save($this->input());}); }
}
