<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\common\model\Config;
use app\common\storage\StorageDriverRegistry;
use support\annotation\route\Get;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Cache;
use support\Response;

/**
 * 附件存储驱动配置。
 */
#[RouteGroup('/admin/system/storage')]
final class SystemStorage extends AdminApiController
{

    public function __construct(private readonly StorageDriverRegistry $drivers)
    {
        parent::__construct();
    }

    #[Get('')]
    public function index(): Response
    {
        $configured = strtolower(trim((string) syscfg('upload', 'upload_driver')));
        $active = $this->drivers->resolve($configured)->name();
        return $this->ok(data: [
            'driver' => $active,
            'fallback' => $configured !== '' && $configured !== $active,
            'drivers' => $this->drivers->all(),
        ]);
    }

    #[Put('')]
    public function update(): Response
    {
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
