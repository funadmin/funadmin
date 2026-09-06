<?php

declare(strict_types=1);

namespace app\console\controller\development;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\service\AdminAuthorizationService;
use app\console\service\DevCrudService;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\App;
use think\Response;
use Throwable;

/**
 * 开发工具 CRUD Workbench Admin API。
 */
#[Group('development/crud')]
final class DevCrud extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly DevCrudService $crud;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $connections = config('crud.connections', []);
        $this->crud = new DevCrudService(
            $app->getRootPath(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : []
        );
    }

    #[Get('connections')]
    public function connections(): Response
    {
        return $this->execute(fn (): array => $this->crud->connections());
    }

    #[Get('tables')]
    public function tables(): Response
    {
        return $this->execute(fn (): array => $this->crud->tables($this->connection()));
    }

    #[Get('tables/:table/schema')]
    #[Pattern('table', '[a-z_][a-z0-9_]*')]
    public function tableSchema(string $table): Response
    {
        return $this->execute(fn (): array => $this->crud->inspect($this->connection(), $table));
    }

    #[Post('infer')]
    public function infer(): Response
    {
        return $this->execute(fn (): array => $this->crud->infer(
            $this->connection(),
            trim((string) $this->request->post('table', ''))
        ));
    }

    #[Post('definitions/validate')]
    public function validateDefinition(): Response
    {
        return $this->execute(fn (): array => $this->crud->validate($this->definition()));
    }

    #[Post('preview')]
    public function preview(): Response
    {
        $authorization = new AdminAuthorizationService();
        $canGenerate = $authorization->nodeAccess('console/devcrud/generate');
        return $this->execute(fn (): array => $this->crud->preview(
            $this->definition(),
            $canGenerate,
            $canGenerate
        ));
    }

    #[Post('generate')]
    public function generate(): Response
    {
        $allowOverwrite = $this->request->post('allowOverwrite', []);
        if (!is_array($allowOverwrite)) {
            return $this->fail(msg: 'allowOverwrite 必须为路径数组', code: 422);
        }
        $authorization = new AdminAuthorizationService();
        return $this->execute(fn (): array => $this->crud->generate(
            $this->definition(),
            trim((string) $this->request->post('confirmToken', '')),
            array_values(array_filter($allowOverwrite, 'is_string')),
            $authorization->nodeAccess('development/crud/overwrite'),
            (string) (session('admin.username') ?: session('admin.id') ?: 'admin-web')
        ), 'CRUD 生成完成');
    }

    #[Get('generations/:id')]
    #[Pattern('id', '\\d+')]
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
