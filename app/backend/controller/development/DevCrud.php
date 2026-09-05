<?php

declare(strict_types=1);

namespace app\backend\controller\development;

use app\backend\controller\base\AdminApiController;
use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\middleware\SystemLog;
use app\backend\service\AuthService;
use app\backend\service\DevCrudService;
use InvalidArgumentException;
use think\App;
use think\Response;
use Throwable;

/**
 * 开发工具 CRUD Workbench Admin API。
 */
final class DevCrud extends AdminApiController
{
    protected $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private DevCrudService $crud;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $connections = config('crud.connections', []);
        $this->crud = new DevCrudService(
            $app->getRootPath(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : []
        );
    }

    public function connections(): Response
    {
        return $this->execute(fn (): array => $this->crud->connections());
    }

    public function tables(): Response
    {
        return $this->execute(fn (): array => $this->crud->tables($this->connection()));
    }

    public function tableSchema(string $table): Response
    {
        return $this->execute(fn (): array => $this->crud->inspect($this->connection(), $table));
    }

    public function infer(): Response
    {
        return $this->execute(fn (): array => $this->crud->infer(
            $this->connection(),
            trim((string) $this->request->post('table', ''))
        ));
    }

    public function validateDefinition(): Response
    {
        return $this->execute(fn (): array => $this->crud->validate($this->definition()));
    }

    public function preview(): Response
    {
        $auth = AuthService::instance();
        $canGenerate = (bool) $auth->nodeAccess('development/crud/generate');
        return $this->execute(fn (): array => $this->crud->preview(
            $this->definition(),
            $canGenerate,
            $canGenerate
        ));
    }

    public function generate(): Response
    {
        $allowOverwrite = $this->request->post('allowOverwrite', []);
        if (!is_array($allowOverwrite)) {
            return $this->fail(msg: 'allowOverwrite 必须为路径数组', code: 422);
        }
        return $this->execute(fn (): array => $this->crud->generate(
            $this->definition(),
            trim((string) $this->request->post('confirmToken', '')),
            array_values(array_filter($allowOverwrite, 'is_string')),
            (bool) AuthService::instance()->nodeAccess('development/crud/overwrite'),
            (string) (session('admin.username') ?: session('admin.id') ?: 'admin-web')
        ), 'CRUD 生成完成');
    }

    public function generationDetail(int $id): Response
    {
        $record = $this->crud->generation($id);
        return $record === null
            ? $this->fail(msg: '生成记录不存在', code: 404)
            : $this->ok(data: $record);
    }

    private function connection(): string
    {
        return trim((string) $this->request->param('connection', ''));
    }

    private function definition(): array
    {
        $definition = $this->request->post('definition', []);
        if (!is_array($definition)) {
            throw new InvalidArgumentException('definition 必须为对象');
        }
        return $definition;
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
