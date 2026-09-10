<?php

declare(strict_types=1);

namespace app\console\controller\legacy;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\Response;

/** 已退役写 API 的最小 tombstone，不承载旧业务能力。 */
#[Group('')]
final class LegacyWriteApi extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    #[Post('form/designer/:action')]
    #[Pattern('action', 'save|remove|status|validate|infer|preview|apply|preview-publish|publish|compile|import|export|rollback')]
    public function formDesignerWrite(string $action): Response
    {
        return $this->retired();
    }

    #[Post('form/designer/rollback/:id/:version')]
    #[Pattern('id', '\\d+')]
    #[Pattern('version', '\\d+')]
    public function formDesignerRollback(int $id, int $version): Response
    {
        return $this->retired();
    }

    #[Post('form/full-publish/:action')]
    #[Pattern('action', 'preview|publish')]
    public function formFullPublishWrite(string $action): Response
    {
        return $this->retired();
    }

    #[Post('form/full-publish/retry-resources/:id')]
    #[Pattern('id', '\\d+')]
    public function formFullPublishRetry(int $id): Response
    {
        return $this->retired();
    }

    #[Post('development/crud/:action')]
    #[Pattern('action', 'infer|preview|generate')]
    public function developmentCrudWrite(string $action): Response
    {
        return $this->retired();
    }

    #[Post('development/crud/definitions/validate')]
    public function developmentCrudValidate(): Response
    {
        return $this->retired();
    }

    #[Post('development/crud/generations/:id/apply-resources')]
    #[Pattern('id', '\\d+')]
    public function developmentCrudApplyResources(int $id): Response
    {
        return $this->retired();
    }

    private function retired(): Response
    {
        return $this->fail(
            '旧写 API 已下线，请使用统一业务开发 API',
            ['newEntry' => '/development/business'],
            code: 410
        );
    }
}
