<?php
declare(strict_types=1);
namespace app\admin\plugins\oss\controller;

use OpenApi\Annotations as OA;

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
    /**
     * 获取云存储配置列表与当前使用的配置。
     *
     * @OA\Get(
     *     path="/admin/plugins/oss/storage",
     *     operationId="admin_plugins_oss_controller_Storage_index_get",
     *     tags={"云存储配置"},
     *     summary="获取云存储配置列表与当前使用的配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index():Response{return $this->action(function():array{$service=$this->service();$current=$service->current();return ['profiles'=>$service->listing(),'current'=>$current,'managed'=>$current!==null];});}
    /**
     * 新增或保存云存储配置。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/profiles",
     *     operationId="admin_plugins_oss_controller_Storage_save_post",
     *     tags={"云存储配置"},
     *     summary="新增或保存云存储配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             description="只接受列出的字段；access_key、secret_key 只写，编辑时留空保留原值。",
     *             @OA\Property(property="id", type="string", description="配置 ID（16 位十六进制）；省略时新增。"),
     *             @OA\Property(property="name", type="string", description="配置名称。"),
     *             @OA\Property(property="provider", type="string", description="云存储厂商，如 aliyun、tencent、s3。"),
     *             @OA\Property(property="bucket", type="string", description="存储桶。"),
     *             @OA\Property(property="region", type="string", description="地域。"),
     *             @OA\Property(property="endpoint", type="string", description="服务地址（S3 兼容存储必填）。"),
     *             @OA\Property(property="domain", type="string", description="访问域名。"),
     *             @OA\Property(property="prefix", type="string", description="对象键前缀。"),
     *             @OA\Property(property="path_style", type="boolean", description="S3 是否使用路径风格。"),
     *             @OA\Property(property="access_key", writeOnly=true, type="string", description="访问密钥 ID（只写）。"),
     *             @OA\Property(property="secret_key", writeOnly=true, type="string", description="访问密钥（只写）。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/profiles')]
    public function save():Response{return $this->action(fn()=>$this->service()->save($this->request->post()));}
    /**
     * 将配置设为当前使用的存储。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/activate",
     *     operationId="admin_plugins_oss_controller_Storage_activate_post",
     *     tags={"云存储配置"},
     *     summary="将配置设为当前使用的存储。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id"},
     *             @OA\Property(property="id", type="string", description="配置 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/activate')]
    public function activate():Response{return $this->action(fn()=>$this->service()->activate($this->profileId()));}
    /**
     * 用表单当前值测试（无需先保存）；只传 id 时测试已保存的配置。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/test",
     *     operationId="admin_plugins_oss_controller_Storage_test_post",
     *     tags={"云存储配置"},
     *     summary="连接测试：用表单当前值测试（无需先保存）；只传 id 时测试已保存的配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             description="与保存接口相同的配置字段；只传 id 时测试已保存的配置。",
     *             @OA\Property(property="id", type="string", description="已保存配置 ID；只传 id 时测试该配置。"),
     *             @OA\Property(property="name", type="string", description="配置名称。"),
     *             @OA\Property(property="provider", type="string", description="云存储厂商，如 aliyun、tencent、s3。"),
     *             @OA\Property(property="bucket", type="string", description="存储桶。"),
     *             @OA\Property(property="region", type="string", description="地域。"),
     *             @OA\Property(property="endpoint", type="string", description="服务地址（S3 兼容存储必填）。"),
     *             @OA\Property(property="domain", type="string", description="访问域名。"),
     *             @OA\Property(property="prefix", type="string", description="对象键前缀。"),
     *             @OA\Property(property="path_style", type="boolean", description="S3 是否使用路径风格。"),
     *             @OA\Property(property="access_key", writeOnly=true, type="string", description="访问密钥 ID（只写）。"),
     *             @OA\Property(property="secret_key", writeOnly=true, type="string", description="访问密钥（只写）。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/test')]
    public function test():Response{return $this->action(fn()=>$this->service()->testConnection($this->request->post()));}
    /**
     * 删除云存储配置（进入回收站）。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/delete",
     *     operationId="admin_plugins_oss_controller_Storage_delete_post",
     *     tags={"云存储配置"},
     *     summary="删除云存储配置（进入回收站）。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id"},
     *             @OA\Property(property="id", type="string", description="配置 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/delete')]
    public function delete():Response{return $this->action(fn()=>$this->service()->delete($this->profileId()));}
    /**
     * 配置回收站：已删除的云存储配置。
     *
     * @OA\Get(
     *     path="/admin/plugins/oss/storage/recycle",
     *     operationId="admin_plugins_oss_controller_Storage_recycle_get",
     *     tags={"云存储配置"},
     *     summary="配置回收站：已删除的云存储配置。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('/recycle')]
    public function recycle():Response{return $this->action(fn()=>$this->service()->recycle());}
    /**
     * 从回收站恢复云存储配置；冲突时返回 409。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/restore",
     *     operationId="admin_plugins_oss_controller_Storage_restore_post",
     *     tags={"云存储配置"},
     *     summary="从回收站恢复云存储配置；冲突时返回 409。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id"},
     *             @OA\Property(property="id", type="string", description="配置 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=409, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/restore')]
    public function restore():Response{return $this->action(fn()=>$this->service()->restore($this->profileId()));}
    /**
     * 彻底删除回收站中的云存储配置，不可恢复。
     *
     * @OA\Post(
     *     path="/admin/plugins/oss/storage/destroy",
     *     operationId="admin_plugins_oss_controller_Storage_destroy_post",
     *     tags={"云存储配置"},
     *     summary="彻底删除回收站中的云存储配置，不可恢复。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。 密钥只写不读；服务未就绪（缺少加密主密钥或依赖）时返回 code=422。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id"},
     *             @OA\Property(property="id", type="string", description="配置 ID。")
     *         )
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=404, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Post('/destroy')]
    public function destroy():Response{return $this->action(fn()=>$this->service()->destroy($this->profileId()));}
}
