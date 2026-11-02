<?php

declare(strict_types=1);

namespace app\admin\system\controller;

use app\admin\controller\base\AdminApiController;
use app\admin\system\service\UpgradeService;
use InvalidArgumentException;
use RuntimeException;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;

/** Admin Web 系统升级 API。 */
#[RouteGroup('/admin/system/upgrade')]
final class SystemUpgrade extends AdminApiController
{

    #[Get('/status')]
    public function status(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->status());
    }

    #[Get('/check')]
    public function check(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->check(), '检查完成');
    }

    #[Post('/execute')]
    public function executeUpgrade(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->execute((array) $this->request->post()), '升级完成');
    }

    #[Post('/upload')]
    public function upload(): Response
    {
        $file = $this->request->file('file');
        if (!$file || !$file->isValid() || strtolower($file->getOriginalExtension()) !== 'zip' || $file->getSize() < 1 || $file->getSize() > 104857600) {
            return $this->fail(msg: '请选择 100MB 以内的有效 ZIP 升级包', code: 422);
        }
        $mime = strtolower((string) $file->getMime());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return $this->fail(msg: '升级包 MIME 类型无效', code: 422);
        }
        return $this->execute(fn () => UpgradeService::instance()->upload(
            $file->getPathname(),
            (string) $this->request->post('operationToken', '')
        ), '升级完成');
    }

    #[Post('/{id:\d+}/restore')]
    public function restore(int $id): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->restore(
            $id,
            (string) $this->request->post('operationToken', '')
        ), '恢复完成');
    }

    #[Post('/recover-stale')]
    public function recoverStale(): Response
    {
        return $this->execute(fn () => UpgradeService::instance()->recoverStale(), '陈旧升级任务处理完成');
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $code = $exception->getCode() === 409 ? 409 : 422;
            return $this->fail(msg: $exception->getMessage(), code: $code);
        } catch (\Throwable $exception) {
            return $this->fail(msg: '系统升级操作失败', code: 500);
        }
    }
}
