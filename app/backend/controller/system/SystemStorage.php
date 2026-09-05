<?php

declare(strict_types=1);

namespace app\backend\controller\system;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\common\model\Config;
use app\common\storage\StorageDriverRegistry;
use think\App;
use think\facade\Cache;
use think\Response;

/**
 * 附件存储驱动配置。
 */
final class SystemStorage extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    public function __construct(App $app, private readonly StorageDriverRegistry $drivers)
    {
        parent::__construct($app);
    }

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
        $config->save(['value' => $driver, 'extra' => $this->driverOptions()]);
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
