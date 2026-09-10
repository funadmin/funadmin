<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\common\form\observability\FormObservability;
use app\common\form\schema\FormSchemaException;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
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
        $this->schemas = new FormSchemaRepository();
        $this->forms = new FormDesignerService($app->getRootPath(), $this->schemas);
        $this->publisher = new FormPublishService($this->forms, $this->schemas);
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
        return $this->retired();
    }

    #[Post('remove')]
    public function remove(): Response
    {
        return $this->retired();
    }

    #[Post('status')]
    public function status(): Response
    {
        return $this->retired();
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
        return $this->retired();
    }

    #[Post('preview-publish')]
    public function previewPublish(): Response
    {
        return $this->execute(fn (): array => $this->publisher->previewDynamic($this->payload()));
    }

    #[Post('publish')]
    public function publish(): Response
    {
        return $this->retired();
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
        return $this->retired();
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

    private function retired(): Response
    {
        return $this->fail('旧表单写 API 已下线，请使用统一业务开发 API', ['newEntry' => '/development/business'], 410);
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
            if ($exception->getMessage() === 'FORM_SCHEMA_CONFLICT') {
                return $this->fail(msg: '表单 Schema 已变化，请刷新后重试', data: ['code' => 'FORM_SCHEMA_CONFLICT'], code: 409);
            }
            if ($exception->getMessage() === 'FORM_DEPENDENCY_CONFLICT') {
                return $this->fail(msg: '表单运行依赖已变化，请重新预览', data: ['code' => 'FORM_DEPENDENCY_CONFLICT'], code: 409);
            }
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
