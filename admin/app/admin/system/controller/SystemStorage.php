<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use OpenApi\Annotations as OA;

use app\common\controller\AdminController;
use app\common\model\Config;
use app\common\storage\StorageDriverRegistry;
use support\annotation\route\Get;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Container;
use support\Response;

/**
 * 附件存储驱动配置。
 */
#[RouteGroup('/admin/system/storage')]
final class SystemStorage extends AdminController
{

    private readonly StorageDriverRegistry $drivers;

    public function __construct(?StorageDriverRegistry $drivers = null)
    {
        parent::__construct();
        $this->drivers = $drivers ?? Container::get(StorageDriverRegistry::class);
    }

    /**
     * 获取当前存储驱动、回退状态及全部可用驱动。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Get(
     *     path="/admin/system/storage",
     *     operationId="admin_system_controller_SystemStorage_index_get",
     *     tags={"存储驱动"},
     *     summary="获取当前存储驱动、回退状态及全部可用驱动。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}}},
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Get('')]
    public function index(): Response
    {
        $configured = strtolower(trim((string) syscfg('upload', 'upload_driver')));
        $resolved = $this->drivers->forUpload($configured);
        $active = $resolved->name();
        return $this->ok(data: [
            'driver' => $active,
            'fallback' => $resolved->source() === 'core' && $configured !== '' && $configured !== $active,
            'managed' => $resolved->source() === 'core' ? null : ['source'=>$resolved->source(),'label'=>$resolved->label(),'driver'=>$active],
            'drivers' => $this->drivers->all(),
        ]);
    }

    /**
     * 校验驱动可用性后更新上传存储驱动并清理缓存。
     *
     * 请求字段：driver。
     *
     * @return Response JSON 格式的接口响应。
     *
     * @OA\Put(
     *     path="/admin/system/storage",
     *     operationId="admin_system_controller_SystemStorage_update_put",
     *     tags={"存储驱动"},
     *     summary="校验驱动可用性后更新上传存储驱动并清理缓存。",
     *     description="普通后台业务协议：成功 code=200；HTTP 200 也可能表示 code=400/422 的业务失败，请同时检查响应体 code。",
     *     security={{"AdminSession"={}, "CsrfToken"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(type="object", required={"driver"}, @OA\Property(property="driver", type="string", description="driver 参数。"))
     *     ),
     *     @OA\Response(response=200, description="业务响应；以响应体 code 判断成功与失败。", @OA\JsonContent(ref="#/components/schemas/AdminResult")),
     *     @OA\Response(response=401, ref="#/components/responses/AdminError"),
     *     @OA\Response(response=403, ref="#/components/responses/AdminError"),
     *     @OA\Response(response="default", ref="#/components/responses/AdminError")
     * )
     */
    #[Put('')]
    public function update(): Response
    {
        if ($this->drivers->forUpload((string) syscfg('upload', 'upload_driver'))->source() !== 'core') return $this->fail(msg:'上传已由存储插件接管，请在插件中切换当前存储',code:422);
        $driver = strtolower(trim((string) $this->request->post('driver', '')));
        if (!$this->drivers->has($driver)) {
            return $this->fail(msg: '存储驱动不存在或当前不可用', code: 422);
        }
        $config = Config::where('group', 'upload')->where('code', 'upload_driver')->find();
        if (!$config) {
            return $this->fail(msg: '上传驱动配置项不存在', code: 500);
        }
        $config->forceFill(['value' => $driver, 'extra' => $this->driverOptions()])->save();
        Cache::clear();
        return $this->ok('存储驱动已更新', ['driver' => $driver]);
    }

    private function driverOptions(): string
    {
        return implode("\n", array_map(
            static fn (array $driver): string => $driver['name'] . ':' . $driver['label'],
            array_filter($this->drivers->all(), static fn (array $driver): bool => $driver['available'])
        ));
    }
}
