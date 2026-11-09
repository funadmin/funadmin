<?php
declare(strict_types=1);
namespace app\admin\plugins\cms\controller;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
use app\cms\service\CategoryService;
#[RouteGroup('/admin/plugins/cms/categories')]
final class Categories extends Base
{
    #[Get('')]
    /** with_forms=1 时额外返回“附带表单”下拉选项（不新增接口，沿用栏目查看权限）；默认仍返回栏目数组。 */
    public function index(): Response { return $this->action(function(): array {
        $site=$this->site('cms:category:list');$rows=(new CategoryService())->listing($site,$this->locale(),false);
        return !empty($this->input()['with_forms'])?['categories'=>$rows,'forms'=>(new \app\cms\service\FormService())->options($site)]:$rows;
    }); }
    #[Post('/status')]
    public function status(): Response { return $this->action(fn()=>(new \app\cms\service\MutationAuthorization())->setStatus($this->site('cms:category:status'),$this->actor(),'category',$this->locale(),$this->input())); }
    #[Post('/save')]
    public function save(): Response { return $this->action(fn()=>$this->mutate('category',fn($site)=>(new CategoryService())->save($site,$this->locale(),$this->input()))); }
}
