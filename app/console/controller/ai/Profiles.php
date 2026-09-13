<?php

declare(strict_types=1);
namespace app\console\controller\ai;

use app\console\controller\base\AdminApiController;
use app\console\ai\repository\DatabaseAiProfileRepository;
use app\console\ai\service\AiConfigurationProfileService;
use app\console\ai\service\AiProfileSecret;
use app\console\authorization\service\AdminAuthorizationService;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\SystemLog;
use think\annotation\route\{Get, Post, Patch, Delete, Group, Pattern};
use think\facade\Session;
use think\Response;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

/** 独立档案 API；snake_case JSON 契约，不影响临时 settings/test 或任务运行。 */
#[Group('development/ai/profiles', ['complete_match'=>true])]
final class Profiles extends AdminApiController
{
    protected array $middleware = [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class];

    /** @profile 返回当前管理员档案数组。 */
    #[Get('')]
    public function index(): Response { return $this->run(fn ($service, $admin) => $service->list($admin)); }
    /** @profile 返回单个档案；跨管理员与不存在均为 404。 */
    #[Get(':id')]
    #[Pattern('id', '\d+')]
    public function read(int $id): Response { return $this->run(fn ($service, $admin) => $service->read($admin, $id)); }
    /** @profile 创建，必填 name/provider/protocol/base_url/model。 */
    #[Post('')]
    public function create(): Response { return $this->run(fn ($service, $admin) => $service->create($admin, $this->input())); }
    /** @profile 部分更新；api_key 缺省保留，空字符串清空，非空加密替换。 */
    #[Patch(':id')]
    #[Pattern('id', '\d+')]
    public function update(int $id): Response { return $this->run(fn ($service, $admin) => $service->update($admin, $id, $this->input())); }
    /** @profile 硬删除档案及密文；默认档案被删除后不自动选择其他档案。 */
    #[Delete(':id')]
    #[Pattern('id', '\d+')]
    public function delete(int $id): Response { return $this->run(fn ($service, $admin) => ['deleted'=>$service->delete($admin, $id)]); }
    /** @profile 返回默认档案，无默认时返回 null。 */
    #[Get('default')]
    public function defaultRead(): Response { return $this->run(fn ($service, $admin) => $service->default($admin)); }
    /** @profile 将指定档案设为唯一默认；返回档案，不改变任何 Job。 */
    #[Post(':id/default')]
    #[Pattern('id', '\d+')]
    public function defaultSet(int $id): Response { return $this->run(fn ($service, $admin) => $service->makeDefault($admin, $id)); }
    /** @profile 只接受新 name；复制配置，不复制密钥和默认状态。 */
    #[Post(':id/copy')]
    #[Pattern('id', '\d+')]
    public function copy(int $id): Response
    {
        return $this->run(function ($service, $admin) use ($id) {
            $input = $this->input();
            if (array_keys($input) !== ['name'] || !is_string($input['name'])) throw new InvalidArgumentException('复制只接受 name', 400);
            return $service->copy($admin, $id, $input['name']);
        });
    }

    /** @profile 以保存档案查询真实模型 ID；不返回密钥或推测能力。 */
    #[Post(':id/models')]
    #[Pattern('id', '\d+')]
    public function models(int $id): Response { return $this->run(fn ($service, $admin) => $service->models($admin, $id)); }

    private function input(): array
    {
        // 直接解析 JSON，避免框架过滤把 boolean/int 隐式转换；不接受表单或查询注入。
        try { $data = json_decode($this->request->getInput(), false, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new InvalidArgumentException('请求必须是 JSON 对象', 400); }
        if (!$data instanceof \stdClass) throw new InvalidArgumentException('请求必须是 JSON 对象', 400);
        return get_object_vars($data);
    }

    private function authorize(int $adminId, bool $configure): int
    {
        if ($adminId <= 0) throw new RuntimeException('未登录', 401);
        if (!$configure) throw new RuntimeException('没有 AI configure 权限', 403);
        return $adminId;
    }

    private function run(callable $operation): Response
    {
        try {
            $adminId = $this->authorize((int) Session::get('admin.id', 0), (new AdminAuthorizationService($this->request))->nodeAccess('development/ai/configure'));
            $service = new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret((string) config('oauth.encryption_key', '')));
            return $this->ok(data: $operation($service, $adminId))->header(['Cache-Control'=>'no-store']);
        } catch (Throwable $e) {
            $expected = $e instanceof InvalidArgumentException || $e instanceof RuntimeException;
            $code = $expected && in_array($e->getCode(), [400,401,403,404,409,503], true) ? $e->getCode() : 500;
            return $this->fail(msg: $code === 500 ? '档案操作失败' : $e->getMessage(), code: $code)->header(['Cache-Control'=>'no-store']);
        }
    }
}
