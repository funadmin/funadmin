<?php

declare(strict_types=1);

namespace app\console\controller\development;

use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
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

    #[Get('options')]
    public function options(): Response
    {
        return $this->execute(fn (): array => $this->crud->options());
    }

    #[Post('infer')]
    public function infer(): Response
    {
        $connection = $this->connection();
        $table = trim((string) $this->request->post('table', ''));
        $targetType = trim((string) $this->request->post('targetType', 'core'));
        if ($targetType === 'core') {
            return $this->execute(fn (): array => $this->crud->infer($connection, $table));
        }
        if ($targetType !== 'plugin') {
            return $this->fail(msg: 'targetType 必须为 core 或 plugin', code: 422);
        }
        return $this->execute(fn (): array => $this->crud->inferPlugin(
            $connection,
            $table,
            trim((string) $this->request->post('plugin', '')),
            trim((string) $this->request->post('entity', '')),
            trim((string) $this->request->post('scope', ''))
        ));
    }

    #[Post('definitions/validate')]
    public function validateDefinition(): Response
    {
        return $this->retired();
    }

    #[Post('preview')]
    public function preview(): Response
    {
        return $this->retired();
    }

    #[Post('generate')]
    public function generate(): Response
    {
        return $this->retired();
    }

    #[Post('generations/:id/apply-resources')]
    #[Pattern('id', '\\d+')]
    public function applyResources(int $id): Response
    {
        return $this->retired();
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

    private function retired(): Response
    {
        return $this->fail('旧 CRUD 写 API 已下线，请使用统一业务开发 API', ['newEntry' => '/development/business'], 410);
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
