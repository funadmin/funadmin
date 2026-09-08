<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\common\form\observability\FormObservability;
use app\common\form\schema\FormSchemaException;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AdminAuthorizationService;
use app\console\service\FormDesignerService;
use app\console\service\FormPublishService;
use app\console\service\FormSchemaRepository;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\App;
use think\Response;
use Throwable;

/**
 * 表单管理 Admin API：定义 CRUD/校验/推断/DDL 预览与应用。
 */
#[Group('form/designer')]
final class Designer extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly FormDesignerService $forms;

    private readonly FormPublishService $publisher;

    private readonly FormSchemaRepository $schemas;

    private readonly FormObservability $observability;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->forms = new FormDesignerService($app->getRootPath());
        $connections = config('crud.connections', []);
        $this->publisher = new FormPublishService(
            $this->forms,
            $app->getRootPath(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : []
        );
        $this->schemas = new FormSchemaRepository();
        $this->observability = new FormObservability();
    }

    #[Get('index')]
    public function index(): Response
    {
        return $this->execute(function (): array {
            $result = $this->forms->listing(
                $this->page(),
                $this->pageSize(),
                trim((string) $this->request->get('keyword', '')),
                $this->request->get('status', null)
            );
            return $this->paginationData($result['list'], $result['total'], $this->page(), $this->pageSize());
        });
    }

    #[Get('detail/:id')]
    #[Pattern('id', '\d+')]
    public function detail(int $id): Response
    {
        return $this->execute(fn (): array => $this->forms->detail($id));
    }

    #[Post('save')]
    public function save(): Response
    {
        return $this->execute(fn (): array => $this->forms->save($this->payload()), '表单保存成功');
    }

    #[Post('remove')]
    public function remove(): Response
    {
        return $this->execute(fn (): array => $this->forms->remove((int) $this->request->post('id', 0)), '表单删除成功');
    }

    #[Post('status')]
    public function status(): Response
    {
        return $this->execute(fn (): array => $this->forms->setStatus(
            (int) $this->request->post('id', 0),
            $this->binaryStatus($this->request->post('status'))
        ), '状态更新成功');
    }

    #[Post('validate')]
    public function validate(): Response
    {
        return $this->execute(fn (): array => $this->forms->validateDefinition($this->payload()));
    }

    #[Post('infer')]
    public function infer(): Response
    {
        return $this->execute(fn (): array => ['fields' => $this->forms->inferFields(
            trim((string) $this->request->post('connection', 'mysql')),
            trim((string) $this->request->post('table', ''))
        )]);
    }

    #[Post('preview')]
    public function preview(): Response
    {
        return $this->execute(fn (): array => $this->forms->previewMigration($this->payload()));
    }

    #[Post('apply')]
    public function apply(): Response
    {
        return $this->execute(fn (): array => $this->forms->applyMigration($this->payload()), '迁移应用成功');
    }

    #[Post('preview-publish')]
    public function previewPublish(): Response
    {
        $authorization = new AdminAuthorizationService();
        $canGenerate = $authorization->nodeAccess('console/form.designer/publish');
        return $this->execute(fn (): array => $this->publisher->preview($this->payload(), $canGenerate));
    }

    #[Post('publish')]
    public function publish(): Response
    {
        $allowOverwrite = $this->request->post('allowOverwrite', []);
        if (!is_array($allowOverwrite)) {
            return $this->fail(msg: 'allowOverwrite 必须为路径数组', code: 422);
        }
        $authorization = new AdminAuthorizationService();
        return $this->execute(fn (): array => $this->publisher->publish(
            $this->payload(),
            trim((string) $this->request->post('confirmToken', '')),
            array_values(array_filter($allowOverwrite, 'is_string')),
            $authorization->nodeAccess('form/publish/overwrite'),
            $authorization->nodeAccess('form/publish/apply-resources'),
            (string) (session('admin.username') ?: session('admin.id') ?: 'admin-web')
        ), '表单全栈发布完成');
    }

    #[Post('compile')]
    public function compile(): Response
    {
        return $this->execute(fn (): array => $this->schemas->compilePayload($this->payload()));
    }

    #[Post('import')]
    public function import(): Response
    {
        return $this->execute(function (): array {
            $compiled = $this->schemas->import((string) $this->request->post('document', ''));
            return [
                'document' => $compiled->document(),
                'hash' => $compiled->hash(),
                'projection' => $compiled->fieldProjection(),
            ];
        }, '导入成功');
    }

    #[Post('export')]
    public function export(): Response
    {
        return $this->execute(fn (): array => ['document' => $this->schemas->export($this->payload())]);
    }

    #[Get('versions/:id')]
    #[Pattern('id', '\d+')]
    public function versions(int $id): Response
    {
        return $this->execute(fn (): array => ['list' => $this->schemas->versions($id)]);
    }

    #[Get('version/:id/:version')]
    #[Pattern('id', '\d+')]
    #[Pattern('version', '\d+')]
    public function version(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->schemas->findVersion($id, $version)->toArray());
    }

    #[Get('diff/:id')]
    #[Pattern('id', '\d+')]
    public function diff(int $id): Response
    {
        return $this->execute(fn (): array => $this->schemas->diff(
            $id,
            (int) $this->request->get('fromVersion', 0),
            (int) $this->request->get('toVersion', 0)
        ));
    }

    #[Post('rollback/:id/:version')]
    #[Pattern('id', '\d+')]
    #[Pattern('version', '\d+')]
    public function rollback(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->schemas->rollback(
            $id,
            $version,
            (string) (session('admin.username') ?: session('admin.id') ?: 'admin-web'),
            trim((string) $this->request->post('summary', ''))
        )->toArray(), '回滚版本已创建');
    }

    #[Get('component-catalog')]
    public function componentCatalog(): Response
    {
        return $this->execute(fn (): array => $this->schemas->componentCatalog());
    }

    #[Get('publish-status/:id')]
    #[Pattern('id', '\d+')]
    public function publishStatus(int $id): Response
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
        $authorization = new AdminAuthorizationService();
        if (!$authorization->nodeAccess('console/form.designer/publish')
            || !$authorization->nodeAccess('form/publish/overwrite')
            || !$authorization->nodeAccess('form/publish/apply-resources')) {
            return $this->fail(msg: '缺少资源重试权限', code: 403);
        }
        return $this->execute(fn (): array => $this->publisher->retryResources($id), '菜单与权限应用完成');
    }

    private function payload(): array
    {
        $payload = $this->request->post('definition', []);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('definition 必须为对象');
        }
        return $payload;
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            $action = (string) $this->request->action();
            if (in_array($action, ['compile', 'import', 'export', 'versions', 'version', 'diff', 'rollback', 'componentCatalog'], true)) {
                $definition = $this->request->post('definition', []);
                $formKey = is_array($definition) ? (string) ($definition['key'] ?? $definition['form_key'] ?? '') : '';
                $schemaHash = is_array($definition) ? (string) ($definition['schemaHash'] ?? '') : '';
                $requestId = trim((string) $this->request->header('X-Request-ID', ''));
                if ($requestId === '' || strlen($requestId) > 64) {
                    $requestId = bin2hex(random_bytes(16));
                }
                $data = $this->observability->measure([
                    'formKey' => $formKey,
                    'schemaHash' => $schemaHash,
                    'nodeId' => '',
                    'action' => $action,
                    'dataSource' => '',
                    'requestId' => $requestId,
                ], $operation);
                return $this->ok($message, $data);
            }
            return $this->ok($message, $operation());
        } catch (FormSchemaException $exception) {
            return $this->fail(
                msg: $exception->getMessage(),
                data: ['code' => $exception->errorCode(), 'path' => $exception->schemaPath()],
                code: 422
            );
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
