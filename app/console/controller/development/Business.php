<?php

declare(strict_types=1);

namespace app\console\controller\development;

use app\common\form\schema\FormSchemaException;
use app\console\controller\base\AdminApiController;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\authorization\service\AdminAuthorizationService;
use app\console\development\http\BusinessApiErrorMapper;
use app\console\development\service\BusinessDevelopmentService;
use InvalidArgumentException;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;
use think\App;
use think\facade\Log;
use think\Response;
use Throwable;

/** 统一业务开发 Admin API。 */
#[Group('development/business')]
final class Business extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    private readonly BusinessDevelopmentService $business;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $connections = config('crud.connections', []);
        $this->business = BusinessDevelopmentService::production(
            $app->getRootPath(),
            is_array($connections) ? array_values(array_filter($connections, 'is_string')) : []
        );
    }

    #[Get('modules')]
    public function modules(): Response
    {
        return $this->execute(function (): array {
            [$page, $pageSize] = BusinessDevelopmentService::pagination((int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20));
            return $this->business->modules($page, $pageSize, trim((string) $this->request->get('keyword', '')), trim((string) $this->request->get('status', '')), trim((string) $this->request->get('origin', '')));
        });
    }

    #[Get('modules/:id')]
    #[Pattern('id', '\d+')]
    public function module(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->module($id));
    }

    #[Post('modules/visual')]
    public function createVisual(): Response
    {
        return $this->execute(fn (): array => $this->business->createVisual($this->input(), $this->actor()), '业务模块草稿创建成功');
    }

    #[Post('modules/from-database/inspect')]
    public function inspectDatabase(): Response
    {
        return $this->execute(fn (): array => $this->business->inspectDatabase(trim((string) $this->request->post('connection', 'mysql')), trim((string) $this->request->post('table', ''))));
    }

    #[Post('modules/from-database')]
    public function createFromDatabase(): Response
    {
        return $this->execute(fn (): array => $this->business->createFromDatabase($this->input(), $this->actor()), '数据库业务模块创建成功');
    }

    #[Post('modules/:id/schema/validate')]
    #[Pattern('id', '\d+')]
    public function validateSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->validateSchema($id, $this->schema()));
    }

    #[Post('modules/:id/schema/save')]
    #[Pattern('id', '\d+')]
    public function saveSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->saveSchema($id, $this->schema(), trim((string) $this->request->post('expectedSchemaHash', '')), $this->actor(), mb_substr(trim((string) $this->request->post('summary', '')), 0, 255)), 'Schema 版本保存成功');
    }

    #[Post('modules/:id/schema/compile')]
    #[Pattern('id', '\d+')]
    public function compileSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->compileSchema($id, $this->schema()));
    }

    #[Post('modules/:id/schema/export')]
    #[Pattern('id', '\d+')]
    public function exportSchema(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->exportSchema($id, $this->schema()));
    }

    #[Get('modules/:id/schema/versions')]
    #[Pattern('id', '\d+')]
    public function schemaVersions(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->schemaVersions($id));
    }

    #[Get('modules/:id/schema/versions/:version')]
    #[Pattern('id', '\d+')]
    #[Pattern('version', '\d+')]
    public function schemaVersion(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->business->schemaVersion($id, $version));
    }

    #[Get('modules/:id/schema/diff')]
    #[Pattern('id', '\d+')]
    public function schemaDiff(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->schemaDiff(
            $id,
            (int) $this->request->get('fromVersion', 0),
            (int) $this->request->get('toVersion', 0)
        ));
    }

    #[Post('modules/:id/schema/versions/:version/rollback')]
    #[Pattern('id', '\d+')]
    #[Pattern('version', '\d+')]
    public function rollbackSchema(int $id, int $version): Response
    {
        return $this->execute(fn (): array => $this->business->rollbackSchema($id, $version, trim((string) $this->request->post('expectedSchemaHash', '')), $this->actor(), mb_substr(trim((string) $this->request->post('summary', '')), 0, 255)), '回滚版本已创建');
    }

    #[Get('database/tables')]
    public function databaseTables(): Response
    {
        return $this->execute(fn (): array => $this->business->databaseTables($this->connection()));
    }

    #[Get('database/tables/:table/schema')]
    #[Pattern('table', '[a-z_][a-z0-9_]*')]
    public function databaseTableSchema(string $table): Response
    {
        return $this->execute(fn (): array => $this->business->databaseTableSchema($this->connection(), $table));
    }

    #[Post('modules/:id/publish/preview')]
    #[Pattern('id', '\d+')]
    public function previewPublish(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->previewPublish($id, $this->input()));
    }

    #[Post('modules/:id/publish')]
    #[Pattern('id', '\d+')]
    public function publish(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->publish($id, $this->input(), $this->actor()), '动态发布完成');
    }

    #[Get('modules/:id/runtime-meta')]
    #[Pattern('id', '\d+')]
    public function runtimeMeta(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->runtimeMeta($id));
    }

    #[Post('modules/:id/formal-generation/preview')]
    #[Pattern('id', '\d+')]
    public function previewFormalGeneration(int $id): Response
    {
        $canGenerate = (new AdminAuthorizationService())->nodeAccess('development/business/generate');
        return $this->execute(fn (): array => $this->business->previewFormalGeneration($id, $canGenerate, ($nonce = trim((string) $this->request->post('nonce', ''))) === '' ? null : $nonce, $this->input()));
    }

    #[Post('modules/:id/formal-generation')]
    #[Pattern('id', '\d+')]
    public function formalGeneration(int $id): Response
    {
        $authorization = new AdminAuthorizationService();
        if (!$authorization->nodeAccess('development/business/generate')) {
            return $this->fail(msg: '缺少正式生成权限', code: 403);
        }
        if (!$authorization->nodeAccess('development/business/apply-resources')) {
            return $this->fail(msg: '缺少 resource apply 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->formalGeneration($id, (int) $this->request->post('generationId', 0), trim((string) $this->request->post('confirmToken', '')), $this->input()), '正式生成完成');
    }

    #[Get('generations')]
    public function generations(): Response
    {
        return $this->execute(function (): array {
            [$page, $pageSize] = BusinessDevelopmentService::pagination((int) $this->request->get('page', 1), (int) $this->request->get('pageSize', 20));
            return $this->business->generations($page, $pageSize, (int) $this->request->get('moduleId', 0), trim((string) $this->request->get('status', '')));
        });
    }

    #[Get('generations/:id')]
    #[Pattern('id', '\d+')]
    public function generation(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->generation($id));
    }

    #[Post('generations/:id/recover')]
    #[Pattern('id', '\d+')]
    public function recoverGeneration(int $id): Response
    {
        if (!(new AdminAuthorizationService())->nodeAccess('development/business/recover')) {
            return $this->fail(msg: '缺少 generation recover 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->recoverGeneration(
            $id,
            trim((string) $this->request->post('expectedRecoveryStatus', '')),
            $this->actor()
        ), '生成恢复完成');
    }

    #[Post('generations/:id/retry-resources')]
    #[Pattern('id', '\d+')]
    public function retryResources(int $id): Response
    {
        if (!(new AdminAuthorizationService())->nodeAccess('development/business/apply-resources')) {
            return $this->fail(msg: '缺少 resource apply 专用权限', code: 403);
        }
        return $this->execute(fn (): array => $this->business->retryResources($id));
    }

    #[Post('modules/:id/baselines/adopt-resolved')]
    #[Pattern('id', '\d+')]
    public function adoptResolvedBaseline(int $id): Response
    {
        return $this->execute(fn (): array => $this->business->adoptResolvedBaseline($id, (int) $this->request->post('generationId', 0), trim((string) $this->request->post('path', '')), trim((string) $this->request->post('localHash', '')), trim((string) $this->request->post('remoteHash', '')), $this->actor()), '已采纳解析后的 Remote baseline');
    }

    #[Get('field-capabilities')]
    public function fieldCapabilities(): Response
    {
        return $this->execute(fn (): array => $this->business->fieldCapabilities());
    }

    private function input(): array
    {
        $input = $this->request->post();
        if (!is_array($input)) throw new InvalidArgumentException('请求体必须为对象');
        return $input;
    }

    private function schema(): array
    {
        $schema = $this->request->post('schema', $this->request->post('schema_document', []));
        if (!is_array($schema) || array_is_list($schema)) throw new InvalidArgumentException('schema 必须为对象');
        return $schema;
    }

    private function connection(): string
    {
        return trim((string) $this->request->get('connection', 'mysql'));
    }

    private function actor(): string
    {
        return mb_substr((string) (session('admin.username') ?: session('admin.id') ?: 'admin-web'), 0, 100);
    }

    private function execute(callable $operation, string $message = '操作成功'): Response
    {
        try {
            return $this->ok($message, $operation());
        } catch (Throwable $exception) {
            $mapped = BusinessApiErrorMapper::map($exception, (string) $this->request->header('X-Request-ID', ''));
            Log::error('business.development.error', [
                'requestId' => $mapped['error']['requestId'],
                'exception' => $exception,
            ]);
            return $this->fail($mapped['message'], ['error' => $mapped['error']], $mapped['httpStatus']);
        }
    }
}
