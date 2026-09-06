<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AdminAuthorizationService;
use app\console\service\FormDataService;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\App;
use think\Response;
use Throwable;

/**
 * 表单数据运行态 Admin API（M3）：元数据驱动通用读写。
 */
#[Group('form/data')]
final class Data extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly FormDataService $data;
    private readonly AdminAuthorizationService $authorization;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->data = new FormDataService();
        $this->authorization = new AdminAuthorizationService();
    }

    #[Get('meta/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function meta(string $key): Response
    {
        return $this->execute(fn (): array => $this->data->meta($key));
    }

    #[Get('index/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function index(string $key): Response
    {
        return $this->execute(function () use ($key): array {
            $result = $this->data->listing(
                $key,
                $this->filters(),
                trim((string) $this->request->get('sort', '')),
                trim((string) $this->request->get('order', '')),
                $this->page(),
                $this->pageSize()
            );
            return $this->paginationData($result['list'], $result['total'], $this->page(), $this->pageSize());
        });
    }

    #[Get('export/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function export(string $key): Response
    {
        return $this->execute(fn (): array => ['list' => $this->data->export($key, $this->filters())]);
    }

    #[Get('detail/:key/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '\d+')]
    public function detail(string $key, int $id): Response
    {
        return $this->execute(fn (): array => $this->data->detail($key, $id));
    }

    #[Get('options/:key/:field')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('field', '[a-z][a-z0-9_]*')]
    public function options(string $key, string $field): Response
    {
        return $this->execute(fn (): array => ['options' => $this->data->options($key, $field)]);
    }

    #[Get('sub/:key/:relation/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('relation', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '\d+')]
    public function sub(string $key, string $relation, int $id): Response
    {
        return $this->execute(fn (): array => $this->data->sub($key, $relation, $id, $this->page(), $this->pageSize()));
    }

    #[Post('create/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function create(string $key): Response
    {
        return $this->execute(fn (): array => $this->data->create($key, $this->payload()), '新增成功');
    }

    #[Post('update/:key/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '\d+')]
    public function update(string $key, int $id): Response
    {
        return $this->execute(fn (): array => $this->data->update($key, $id, $this->payload()), '更新成功');
    }

    #[Post('remove/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function remove(string $key): Response
    {
        return $this->execute(fn (): array => $this->data->remove($key, (int) $this->request->post('id', 0)), '删除成功');
    }

    private function payload(): array
    {
        $payload = $this->request->post('data', []);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('data 必须为对象');
        }
        return $payload;
    }

    private function filters(): array
    {
        $filters = $this->request->get('filters', []);
        return is_array($filters) ? $filters : [];
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        $action = (string) ($this->request->action(true) ?: '');
        if ($action !== '' && !$this->authorization->nodeAccess('console/form.data:' . $action)) {
            return $this->fail(msg: '没有访问权限', code: 403);
        }
        try {
            return $this->ok($message, $operation());
        } catch (InvalidArgumentException $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
