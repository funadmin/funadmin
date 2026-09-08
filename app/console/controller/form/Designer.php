<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AdminAuthorizationService;
use app\console\service\FormDesignerService;
use app\console\service\FormPublishService;
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

    #[Get('publish-status/:id')]
    #[Pattern('id', '\d+')]
    public function publishStatus(int $id): Response
    {
        return $this->execute(fn (): array => $this->publisher->status($id));
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
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
