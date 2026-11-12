<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\controller;
use app\common\controller\AdminController;
use app\admin\plugins\oss\service\ProfileService;
use support\annotation\route\{Get,Post,RouteGroup};
use support\Response;
/**
 * 云存储配置。权限码由方法名推导（oss:storage:<方法>）：index 查看、save 保存、activate 设为当前、test 连接测试、
 * delete 删除（软删除）、recycle 查看回收站、restore 恢复、destroy 彻底删除。
 */
#[RouteGroup('/admin/plugins/oss/storage')]
final class Storage extends AdminController
{
    private function service():ProfileService{return \support\Container::get(ProfileService::class);}
    private function action(callable $operation):Response{
        try{return $this->ok(data:$operation());}
        catch(\InvalidArgumentException $error){return $this->fail(msg:$error->getMessage(),code:in_array($error->getCode(),[404,409],true)?$error->getCode():422);}
        catch(\RuntimeException $error){return $this->fail(msg:match($error->getCode()){503=>'云存储服务未就绪，请检查加密主密钥和插件依赖',default=>'云存储操作失败，请检查配置、网络及脱敏日志'},code:422);}
        catch(\Throwable){return $this->fail(msg:'云存储操作失败，请检查插件安装及服务器日志',code:500);}
    }
    private function profileId():string{$id=$this->request->post('id','');return is_string($id)?$id:'';}
    #[Get('')]
    public function index():Response{return $this->action(function():array{$service=$this->service();$current=$service->current();return ['profiles'=>$service->listing(),'current'=>$current,'managed'=>$current!==null];});}
    #[Post('/profiles')]
    public function save():Response{return $this->action(fn()=>$this->service()->save($this->request->post()));}
    #[Post('/activate')]
    public function activate():Response{return $this->action(fn()=>$this->service()->activate($this->profileId()));}
    /** 用表单当前值测试（无需先保存）；只传 id 时测试已保存的配置。 */
    #[Post('/test')]
    public function test():Response{return $this->action(fn()=>$this->service()->testConnection($this->request->post()));}
    #[Post('/delete')]
    public function delete():Response{return $this->action(fn()=>$this->service()->delete($this->profileId()));}
    #[Get('/recycle')]
    public function recycle():Response{return $this->action(fn()=>$this->service()->recycle());}
    #[Post('/restore')]
    public function restore():Response{return $this->action(fn()=>$this->service()->restore($this->profileId()));}
    #[Post('/destroy')]
    public function destroy():Response{return $this->action(fn()=>$this->service()->destroy($this->profileId()));}
}
