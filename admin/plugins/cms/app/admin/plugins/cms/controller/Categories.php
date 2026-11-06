<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\CategoryService;
#[RouteGroup('/admin/plugin/cms/categories')]
final class Categories extends Base
{
    #[Get('')]
    public function index(): Response { return $this->action(fn()=>(new CategoryService())->listing($this->site('cms:category:list'),$this->locale(),false)); }
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new \app\cms\service\MutationAuthorization())->setStatus($this->site('cms:category:status'),$this->actor(),'category',$this->locale(),$this->input())); }
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('category',fn($site)=>(new CategoryService())->save($site,$this->locale(),$this->input()))); }
}
