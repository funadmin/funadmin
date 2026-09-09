<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\common\form\validation\FormAsyncValidationException;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\common\form\dataSource\FormDataSourceRegistry;
use app\common\form\validation\FormAsyncValidatorRegistry;
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
        $this->authorization = new AdminAuthorizationService();
        $dataSources = config('form.data_sources', []);
        $validators = config('form.validators', []);
        $this->data = new FormDataService(
            new FormAsyncValidatorRegistry(is_array($validators) ? $validators : []),
            FormDataSourceRegistry::core(is_array($dataSources) ? $dataSources : []),
            fn (string $permission): bool => $this->authorization->nodeAccess($permission)
        );
    }

    #[Get('meta/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function meta(string $key): Response
    {
        $etag = '';
        $response = $this->execute(function () use ($key, &$etag): array {
            $meta = $this->data->meta($key);
            $etag = (string) $meta['etag'];
            return $meta;
        });
        return $etag === '' ? $response : $response->header(['ETag' => $etag]);
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
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function detail(string $key, int|string $id): Response
    {
        return $this->execute(fn (): array => $this->data->detail($key, $id));
    }

    #[Get('options/:key/:field')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('field', '[a-z][a-z0-9_]*')]
    public function options(string $key, string $field): Response
    {
        return $this->execute(fn (): array => $this->data->paginateOptions(
            $this->data->options($key, $field, $this->request->get()),
            trim((string) $this->request->get('keyword', '')),
            $this->page(),
            $this->pageSize()
        ));
    }

    #[Post('validate/:key/:field')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('field', '[a-z][a-z0-9_]*')]
    public function validateAsync(string $key, string $field): Response
    {
        $values = $this->request->post('values', []);
        $params = $this->request->post('params', []);
        if (!is_array($values) || !is_array($params)) {
            throw new InvalidArgumentException('values 和 params 必须为对象');
        }
        return $this->execute(fn (): array => $this->data->validateAsync(
            $key,
            $field,
            trim((string) $this->request->post('validator', '')),
            $this->request->post('value'),
            $values,
            $params
        ));
    }

    #[Get('sub/:key/:relation/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('relation', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function sub(string $key, string $relation, int|string $id): Response
    {
        return $this->execute(fn (): array => $this->data->sub($key, $relation, $id, $this->page(), $this->pageSize()));
    }

    #[Post('create/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function create(string $key): Response
    {
        $payload = $this->payload();
        $this->redactRequestPayload($key, $payload);
        return $this->execute(fn (): array => $this->data->create($key, $payload, $this->include(), $this->schemaHash()), '新增成功');
    }

    #[Post('update/:key/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function update(string $key, int|string $id): Response
    {
        $payload = $this->payload();
        $this->redactRequestPayload($key, $payload);
        return $this->execute(fn (): array => $this->data->update($key, $id, $payload, $this->include(), $this->schemaHash()), '更新成功');
    }

    #[Post('remove/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function remove(string $key): Response
    {
        $id = $this->request->post('id', '');
        return $this->execute(fn (): array => $this->data->remove($key, is_int($id) ? $id : trim((string) $id)), '删除成功');
    }

    private function payload(): array
    {
        $payload = $this->request->post('data', []);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('data 必须为对象');
        }
        return $payload;
    }

    private function redactRequestPayload(string $key, array $payload): void
    {
        $post = $this->request->post();
        $post['data'] = $this->data->redactRequestPayload($key, $payload);
        $this->request->withPost($post);
    }

    private function include(): array
    {
        $include = $this->request->post('include', []);
        return is_array($include) ? array_values(array_filter(array_map('strval', $include))) : [];
    }

    private function schemaHash(): string
    {
        return trim((string) $this->request->post('schemaHash', ''));
    }

    private function filters(): array
    {
        $filters = $this->request->get('filters', []);
        return is_array($filters) ? $filters : [];
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        $action = (string) ($this->request->action(true) ?: '');
        $permissionAction = $action === 'validateasync' ? 'options' : $action;
        if ($permissionAction !== '' && !$this->authorization->nodeAccess('console/form.data:' . $permissionAction)) {
            return $this->fail(msg: '没有访问权限', code: 403);
        }
        try {
            return $this->ok($message, $operation());
        } catch (FormAsyncValidationException $exception) {
            return $this->fail(
                msg: $exception->errorCode(),
                data: ['fieldErrors' => $exception->fieldErrors()],
                code: 422
            );
        } catch (InvalidArgumentException $exception) {
            if ($exception->getMessage() === 'FORM_SCHEMA_CONFLICT') {
                return $this->fail(msg: '表单发布版本已更新，请刷新后重试', data: ['code' => 'FORM_SCHEMA_CONFLICT'], code: 409);
            }
            return $this->fail(msg: $exception->getMessage(), code: 422);
        } catch (Throwable $exception) {
            return $this->fail(msg: $exception->getMessage(), code: 500);
        }
    }
}
