<?php

declare(strict_types=1);

namespace app\admin\controller\plugin\market;

use app\admin\controller\base\AdminApiController;
use app\admin\middleware\CheckAdminApiCsrf;
use app\admin\middleware\CheckPluginPermission;
use app\admin\middleware\SystemLog;
use app\admin\service\plugin\market\MarketAdminService;
use think\annotation\route\Delete;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\annotation\route\Put;
use think\Response;

#[Group('plugin/market/plugin', ['complete_match' => true])]
final class PluginController extends AdminApiController
{
    protected array $middleware = [
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:plugin:view']], 'options' => ['only' => ['index', 'overview', 'versions']]],
        ['middleware' => [CheckPluginPermission::class, ['market', 'market:plugin:manage']], 'options' => ['except' => ['index', 'overview', 'versions']]],
        CheckAdminApiCsrf::class,
        SystemLog::class,
    ];

    #[Get('overview')]
    public function overview(): Response
    {
        return $this->ok(data: (new MarketAdminService())->overview());
    }

    #[Get('')]
    public function index(): Response
    {
        return $this->ok(data: (new MarketAdminService())->plugins((string) $this->request->get('keyword', ''), $this->page(), $this->pageSize()));
    }

    #[Put(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->updatePlugin($id, (array) $this->request->put()), '插件信息已保存');
    }

    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->deletePlugin($id), '插件已删除');
    }

    #[Get(':id/versions')]
    #[Pattern('id', '\d+')]
    public function versions(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->versions($id));
    }

    #[Post('upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->fail('请上传插件 ZIP 包', code: 422);
        }
        if (strtolower($file->getOriginalExtension()) !== 'zip') {
            return $this->fail('只支持 .zip 插件包', code: 422);
        }
        return $this->attempt(
            fn () => (new MarketAdminService())->upload($file->getPathname(), (string) $this->request->post('changelog', '')),
            '已上传并签名，版本处于草稿状态'
        );
    }

    #[Put('version/:id')]
    #[Pattern('id', '\d+')]
    public function updateVersion(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->updateVersion($id, (array) $this->request->put()), '更新日志已保存');
    }

    #[Post('version/:id/publish')]
    #[Pattern('id', '\d+')]
    public function publish(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->publishVersion($id), '版本已发布');
    }

    #[Post('version/:id/withdraw')]
    #[Pattern('id', '\d+')]
    public function withdraw(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->withdrawVersion($id), '版本已撤回');
    }

    #[Post('version/:id/republish')]
    #[Pattern('id', '\d+')]
    public function republish(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->republishVersion($id), '版本已重新发布');
    }

    #[Delete('version/:id')]
    #[Pattern('id', '\d+')]
    public function deleteVersion(int $id): Response
    {
        return $this->attempt(fn () => (new MarketAdminService())->deleteVersion($id), '版本已删除');
    }

    #[Get('version/:id/package')]
    #[Pattern('id', '\d+')]
    public function package(int $id): Response
    {
        try {
            $package = (new MarketAdminService())->versionPackage($id);
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 404);
        }
        return download($package['path'], $package['name']);
    }

    private function attempt(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage(), code: 422);
        }
    }
}
