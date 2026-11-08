<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\controller;
use app\common\controller\AdminController;
use app\admin\plugins\oss\service\ProfileService;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
#[RouteGroup('/admin/plugins/oss/storage')]
final class Storage extends AdminController
{
    private function service():ProfileService{return \support\Container::get(ProfileService::class);}
    private function action(callable $operation):Response{
        try{return $this->ok(data:$operation());}
        catch(\InvalidArgumentException $error){return $this->fail(msg:$error->getMessage(),code:422);}
        catch(\RuntimeException $error){return $this->fail(msg:match($error->getCode()){503=>'云存储服务未就绪，请检查加密主密钥和插件依赖',default=>'云存储操作失败，请检查配置、网络及脱敏日志'},code:422);}
        catch(\Throwable){return $this->fail(msg:'云存储操作失败，请检查插件安装及服务器日志',code:500);}
    }
    #[Get('')]
    public function index():Response{return $this->action(function():array{$service=$this->service();$current=$service->current();return ['profiles'=>$service->listing(),'current'=>$current,'managed'=>$current!==null];});}
    #[Post('/profiles')]
    public function save():Response{return $this->action(fn()=>$this->service()->save($this->request->post()));}
    #[Post('/activate')]
    public function activate():Response{return $this->action(fn()=>$this->service()->activate((string)$this->request->post('id','')));}
    #[Post('/test')]
    public function test():Response{return $this->action(fn()=>$this->service()->testConnection((string)$this->request->post('id','')));}
}
