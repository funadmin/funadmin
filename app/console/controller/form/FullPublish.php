<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\common\form\observability\FormObservability;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AdminAuthorizationService;
use app\console\service\FormDesignerService;
use app\console\service\FormFullPublishService;
use app\console\service\FormSchemaRepository;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\App;
use think\Response;
use Throwable;

/** 表单完整发布 API：静态全栈生成、冲突保护及资源重试。 */
#[Group('form/full-publish')]
final class FullPublish extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly FormFullPublishService $publisher;
    private readonly FormObservability $observability;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $schemas = new FormSchemaRepository();
        $forms = new FormDesignerService($app->getRootPath(), $schemas);
        $connections = config('crud.connections', []);
        $this->publisher = new FormFullPublishService(
            $forms,
            $app->getRootPath(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : [],
            schemas: $schemas
        );
        $this->observability = new FormObservability();
    }

    #[Post('preview')]
    public function preview(): Response
    {
        $authorization = new AdminAuthorizationService();
        return $this->execute(fn (): array => $this->publisher->preview(
            $this->payload(),
            $authorization->nodeAccess('console/form.full-publish/publish')
        ));
    }

    #[Post('publish')]
    public function publish(): Response
    {
        $overwrite = $this->request->post('allowOverwrite', []);
        if (!is_array($overwrite)) return $this->fail(msg: 'allowOverwrite 必须为路径数组', code: 422);
        $authorization = new AdminAuthorizationService();
        return $this->execute(fn (): array => $this->publisher->publish(
            $this->payload(),
            trim((string) $this->request->post('confirmToken', '')),
            trim((string) $this->request->post('operationKey', '')),
            array_values(array_filter($overwrite, 'is_string')),
            $authorization->nodeAccess('form/publish/overwrite'),
            $authorization->nodeAccess('form/publish/apply-resources'),
            (string) (session('admin.username') ?: session('admin.id') ?: 'admin-web')
        ), '表单全栈发布完成');
    }

    #[Get('status/:id')]
    #[Pattern('id', '\d+')]
    public function status(int $id): Response
    {
        return $this->execute(fn (): array => $this->publisher->status($id));
    }

    #[Get('generation/:id')]
    #[Pattern('id', '\d+')]
    public function generation(int $id): Response
    {
        $status = $this->publisher->status($id);
        $generationId = (int) ($status['generationId'] ?? 0);
        if ($generationId < 1) return $this->fail(msg: '表单没有生成记录', code: 404);
        $record = $this->publisher->generation($generationId);
        return $record === null ? $this->fail(msg: '生成记录不存在', code: 404) : $this->ok(data: $record);
    }

    #[Post('retry-resources/:id')]
    #[Pattern('id', '\d+')]
    public function retryResources(int $id): Response
    {
        if (!(new AdminAuthorizationService())->nodeAccess('form/publish/apply-resources')) {
            return $this->fail(msg: '没有应用菜单与权限资源的权限', code: 403);
        }
        return $this->execute(fn (): array => $this->publisher->retryResources($id), '菜单与权限应用完成');
    }

    private function payload(): array
    {
        $formId = (int) $this->request->post('formId', 0);
        if ($formId < 1) throw new InvalidArgumentException('formId 必须为正整数');
        return [
            'formId' => $formId,
            'generationId' => (int) $this->request->post('generationId', 0),
            'schemaHash' => trim((string) $this->request->post('schemaHash', '')),
        ];
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            $action = (string) $this->request->action();
            $formId = (int) $this->request->post('formId', 0);
            return $this->ok($message, $this->observability->measure([
                'formKey' => $formId > 0 ? 'form:' . $formId : '',
                'schemaHash' => trim((string) $this->request->post('schemaHash', '')),
                'nodeId' => '',
                'action' => 'full-publish.' . $action,
                'dataSource' => '',
                'requestId' => trim((string) $this->request->header('X-Request-ID', '')),
            ], $operation));
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
