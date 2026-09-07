<?php

declare(strict_types=1);

namespace app\console\controller\development;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\DevPluginService;
use InvalidArgumentException;
use RuntimeException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Post;
use think\App;
use think\Response;
use Throwable;

/** 受 RBAC、CSRF 与审计中间件保护的插件开发 API。 */
#[Group('development/plugin')]
final class DevPlugin extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly DevPluginService $plugins;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->plugins = new DevPluginService($app->getRootPath());
    }

    #[Post('create/preview')]
    public function previewCreate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->previewCreate($this->createInput()));
    }

    #[Post('create')]
    public function create(): Response
    {
        return $this->execute(fn (): array => $this->plugins->create($this->createInput()), '插件骨架创建完成');
    }

    #[Post('validate')]
    public function validate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->validate($this->code()));
    }

    #[Post('package')]
    public function package(): Response
    {
        return $this->execute(fn (): array => $this->plugins->package(
            $this->code(),
            trim((string) $this->request->post('output', ''))
        ), '插件打包完成');
    }

    #[Get('options')]
    public function options(): Response
    {
        return $this->execute(fn (): array => $this->plugins->options());
    }

    private function createInput(): array
    {
        return [
            'name' => trim((string) $this->request->post('name', '')),
            'title' => trim((string) $this->request->post('title', '')),
            'application' => $this->boolean('application', true),
            'console' => $this->boolean('console', true),
            'adminWeb' => $this->boolean('adminWeb', true),
        ];
    }

    private function code(): string
    {
        return trim((string) $this->request->post('code', ''));
    }

    private function boolean(string $name, bool $default): bool
    {
        $value = $this->request->post($name, $default);
        if (is_bool($value)) {
            return $value;
        }
        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            throw new InvalidArgumentException($name . ' 必须为布尔值');
        }
        return $normalized;
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (RuntimeException $exception) {
            $conflict = str_contains($exception->getMessage(), '已存在');
            return $this->fail(msg: $exception->getMessage(), code: $conflict ? 409 : 422);
        } catch (Throwable $exception) {
            trace('插件开发操作失败：' . $exception->getMessage(), 'error');
            return $this->fail(msg: '插件开发操作失败', code: 500);
        }
    }
}
