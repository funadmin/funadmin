<?php

declare(strict_types=1);

namespace app\admin\plugin\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\plugin\service\DevPluginService;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Throwable;

/** 受 RBAC、CSRF 与审计中间件保护的插件开发 API。 */
#[RouteGroup('/admin/development/plugin')]
final class DevPlugin extends AdminApiController
{

    private readonly DevPluginService $plugins;

    public function __construct()
    {
        parent::__construct();
        $this->plugins = new DevPluginService(root_path());
    }

    #[Post('/create/preview')]
    public function previewCreate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->previewCreate($this->createInput()));
    }

    #[Post('/create')]
    public function create(): Response
    {
        return $this->execute(fn (): array => $this->plugins->create($this->createInput()), '插件骨架创建完成');
    }

    #[Post('/validate')]
    public function validate(): Response
    {
        return $this->execute(fn (): array => $this->plugins->validate($this->code()));
    }

    #[Post('/package')]
    public function package(): Response
    {
        return $this->execute(fn (): array => $this->plugins->package(
            $this->code(),
            trim((string) $this->request->post('output', ''))
        ), '插件打包完成');
    }

    #[Get('/package/{code:[a-z][a-z0-9]*}/download')]
    public function packageDownload(string $code): Response
    {
        try {
            $download = $this->plugins->packageDownload($code);
            return download($download['path'], $download['filename']);
        } catch (RuntimeException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 404);
        }
    }

    #[Get('/options')]
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
            'admin' => $this->boolean('admin', true),
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
