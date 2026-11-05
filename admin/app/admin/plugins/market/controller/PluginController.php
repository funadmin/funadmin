<?php

declare(strict_types=1);

namespace app\admin\plugins\market\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\plugin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\plugins\market\service\MarketAdminService;
use support\annotation\route\Delete;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use support\Response;

#[RouteGroup('/admin/plugin/market/plugin')]
final class PluginController extends AdminApiController
{
    protected array $middleware = [
        CheckPluginPermission::class,
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];
    #[Get('/overview')]
    public function overview(): Response { return $this->ok(data: (new MarketAdminService())->overview()); }
    #[Get('/')]
    public function index(): Response { return $this->ok(data: (new MarketAdminService())->plugins((string) $this->request->get('keyword', ''), $this->page(), $this->pageSize())); }
    #[Put('/{id:\\d+}')]
    public function update(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->updatePlugin($id, (array) $this->request->post()), '插件信息已保存'); }
    #[Delete('/{id:\\d+}')]
    public function delete(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->deletePlugin($id), '插件已删除'); }
    #[Get('/{id:\\d+}/versions')]
    public function versions(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->versions($id)); }
    #[Post('/upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) return $this->fail('请上传插件 ZIP 包', code: 422);
        if (strtolower($file->getUploadExtension()) !== 'zip') return $this->fail('只支持 .zip 插件包', code: 422);
        return $this->attempt(fn () => (new MarketAdminService())->upload($file->getPathname(), (string) $this->request->post('changelog', '')), '已上传并签名，版本处于草稿状态');
    }
    #[Put('/version/{id:\\d+}')]
    public function updateVersion(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->updateVersion($id, (array) $this->request->post()), '更新日志已保存'); }
    #[Post('/version/{id:\\d+}/publish')]
    public function publish(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->publishVersion($id), '版本已发布'); }
    #[Post('/version/{id:\\d+}/withdraw')]
    public function withdraw(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->withdrawVersion($id), '版本已撤回'); }
    #[Post('/version/{id:\\d+}/republish')]
    public function republish(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->republishVersion($id), '版本已重新发布'); }
    #[Delete('/version/{id:\\d+}')]
    public function deleteVersion(int $id): Response { return $this->attempt(fn () => (new MarketAdminService())->deleteVersion($id), '版本已删除'); }
    #[Get('/version/{id:\\d+}/package')]
    public function package(int $id): Response
    {
        try { $package = (new MarketAdminService())->versionPackage($id); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 404); }
        return response()->withFile($package['path'])->withHeader('Content-Disposition', 'attachment; filename="' . $package['name'] . '"');
    }
    private function attempt(callable $operation, string $message = '操作成功'): Response
    {
        try { return $this->ok($message, $operation()); } catch (\RuntimeException $exception) { return $this->fail($exception->getMessage(), code: 422); }
    }
}
