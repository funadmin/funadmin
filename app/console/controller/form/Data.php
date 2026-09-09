<?php

declare(strict_types=1);

namespace app\console\controller\form;

use app\common\form\action\FormActionRegistry;
use app\common\form\observability\FormObservability;
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
use think\facade\Cache;
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
    private readonly FormActionRegistry $actions;
    private readonly FormObservability $observability;

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
        $actionDefinitions = is_array(config('form.actions', [])) ? config('form.actions', []) : [];
        $actionDefinitions = array_map(static fn (array $definition): array => [
            ...$definition,
            'timeoutMs' => $definition['timeoutMs'] ?? config('form.action_timeout_ms', 3000),
            'maxChain' => $definition['maxChain'] ?? config('form.action_max_chain', 5),
        ], $actionDefinitions);
        $this->actions = new FormActionRegistry($actionDefinitions, static function (string $scope): bool {
            $cacheKey = 'form_action_idempotency:' . $scope;
            $nonce = bin2hex(random_bytes(16));
            return Cache::remember(
                $cacheKey,
                static fn (): string => $nonce,
                max(1, (int) config('form.idempotency_ttl', 86400))
            ) === $nonce;
        });
        $this->observability = new FormObservability();
    }

    #[Get('meta/:key')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    public function meta(string $key): Response
    {
        $etag = '';
        $response = $this->execute(function () use ($key, &$etag): array {
            $meta = $this->observe($key, 'meta', fn (): array => $this->data->meta($key));
            $etag = (string) $meta['etag'];
            return $meta;
        });
        if ($etag !== '' && trim((string) $this->request->header('If-None-Match', '')) === $etag) {
            return response('', 304)->header(['ETag' => $etag])->code(304);
        }
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
        return $this->execute(fn (): array => $this->observe($key, 'detail', fn (): array => $this->data->detail($key, $id)));
    }

    #[Get('options/:key/:field')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('field', '[a-z][a-z0-9_]*')]
    public function options(string $key, string $field): Response
    {
        return $this->execute(fn (): array => $this->observe(
            $key,
            'options',
            fn (): array => $this->data->paginateOptions(
                $this->data->options($key, $field, $this->request->get()),
                trim((string) $this->request->get('keyword', '')),
                $this->page(),
                $this->pageSize()
            ),
            nodeId: $field,
            dataSource: 'options'
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
        return $this->execute(fn (): array => $this->observe(
            $key,
            'validate',
            fn (): array => $this->data->validateAsync(
                $key,
                $field,
                trim((string) $this->request->post('validator', '')),
                $this->request->post('value'),
                $values,
                $params
            ),
            nodeId: $field
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
        return $this->execute(fn (): array => $this->observe(
            $key,
            'create',
            fn (): array => $this->data->create($key, $payload, $this->include(), $this->schemaHash()),
            $this->schemaHash()
        ), '新增成功');
    }

    #[Post('update/:key/:id')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('id', '[A-Za-z0-9_-]+')]
    public function update(string $key, int|string $id): Response
    {
        $payload = $this->payload();
        $this->redactRequestPayload($key, $payload);
        return $this->execute(fn (): array => $this->observe(
            $key,
            'update',
            fn (): array => $this->data->update($key, $id, $payload, $this->include(), $this->schemaHash()),
            $this->schemaHash()
        ), '更新成功');
    }

    #[Post('action/:key/:action')]
    #[Pattern('key', '[a-z][a-z0-9_]*')]
    #[Pattern('action', '[a-z][a-z0-9._-]*')]
    public function action(string $key, string $action): Response
    {
        $parameters = $this->request->post('parameters', []);
        if (!is_array($parameters)) {
            throw new InvalidArgumentException('parameters 必须为对象');
        }
        $idempotencyKey = trim((string) $this->request->header('Idempotency-Key', ''));
        $chainDepth = max(1, (int) $this->request->header('X-Form-Action-Depth', 1));
        return $this->execute(fn (): array => $this->observe($key, 'action', function () use (
            $key,
            $action,
            $parameters,
            $idempotencyKey,
            $chainDepth
        ): array {
            $result = $this->data->executeAction($key, $action, $parameters, $idempotencyKey, $chainDepth, $this->actions);
            return ['result' => $result['result']];
        }, actionKey: $action), '执行成功');
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

    private function observe(
        string $formKey,
        string $action,
        callable $operation,
        string $schemaHash = '',
        string $nodeId = '',
        string $dataSource = '',
        string $actionKey = ''
    ): mixed {
        $runtime = $this->data->runtimeContext($formKey, $nodeId, $dataSource, $actionKey);
        return $this->observability->measure([
            'formKey' => $formKey,
            'schemaHash' => $schemaHash !== '' ? $schemaHash : $runtime['schemaHash'],
            'nodeId' => $runtime['nodeId'],
            'action' => $action,
            'dataSource' => $runtime['dataSource'],
            'requestId' => trim((string) $this->request->header('X-Request-ID', '')),
        ], $operation);
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
