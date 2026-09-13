<?php

declare(strict_types=1);
namespace app\console\ai\repository {
    // 仅替换数据库边界；不初始化应用、不连接真实数据库。
    final class DatabaseAiProfileRepository {
        public array $rows = [];
        public function find(int $adminId, int $id, bool $lock = false): object {
            $row = $this->rows[$id] ?? null;
            if (!$row || $row->admin_id !== $adminId) throw new \RuntimeException('档案不存在', 404);
            return $row;
        }
    }
}
namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';
    use app\console\ai\repository\DatabaseAiProfileRepository;
    use app\console\ai\service\AiConfigurationProfileService;
    use app\console\ai\service\AiProfileSecret;
    use app\common\ai\provider\OpenAiCompatibleGateway;
    use GuzzleHttp\Client;
    use GuzzleHttp\Handler\MockHandler;
    use GuzzleHttp\HandlerStack;
    use GuzzleHttp\Middleware;
    use GuzzleHttp\Psr7\Response;
    function catalogExpect(bool $ok, string $message): void { if (!$ok) throw new \LogicException($message); }
    $repository = new DatabaseAiProfileRepository();
    $secret = new AiProfileSecret(\Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString());
    $repository->rows[2] = new class {
        public int $admin_id = 7;
        public string $name = '测试';
        public array $configuration = ['provider'=>'custom','protocol'=>'openai-chat','base_url'=>'https://example.com/v1','model'=>'test'];
        public string $cipher;
        public int $secretReads = 0;
        public function getAttr(string $key): string { $this->secretReads++; return $this->cipher; }
    };
    $repository->rows[2]->cipher = $secret->seal('current-secret', 7);
    $history = [];
    $factory = static function (array $config) use (&$history): OpenAiCompatibleGateway {
        $stack = HandlerStack::create(new MockHandler([new Response(200, [], '{"data":[{"id":"test"}]}')]));
        $stack->push(Middleware::history($history));
        return new OpenAiCompatibleGateway(new Client(['handler'=>$stack]), $config, static fn () => ['93.184.216.34']);
    };
    $service = new AiConfigurationProfileService($repository, $secret, $factory);
    catalogExpect(method_exists($service, 'models'), '保存档案尚无模型目录服务');
    $catalog = $service->models(7, 2);
    catalogExpect($catalog[0]['id'] === 'test' && ($catalog[0]['capabilities']['source'] ?? null) === 'unknown', '目录响应包含未知能力而非猜测');
    catalogExpect($history[0]['request']->getHeaderLine('Authorization') === 'Bearer current-secret', '读取当前加密凭据');
    foreach ([[8,2],[7,99]] as [$admin,$id]) {
        try { $service->models($admin, $id); throw new \LogicException('应拒绝越权或删除档案'); }
        catch (\RuntimeException $e) { catalogExpect($e->getCode() === 404, '所有权边界'); }
    }
    catalogExpect(method_exists($service, 'snapshot'), '缺少无密钥运行快照');
    $snapshot = $service->snapshot(7, 2, 'chosen');
    catalogExpect($snapshot['profile_id'] === 2 && $snapshot['model'] === 'chosen' && !str_contains(json_encode($snapshot), 'secret'), '快照只冻结非敏感配置');
    $original = $repository->rows[2]->configuration;
    foreach ([
        ['base_url'=>'https://changed.example.com/v1'],
        ['base_url'=>'https://example.com/v2'],
        ['base_url'=>'https://example.com:8443/v1'],
        ['base_url'=>'https://example.com/v1/'],
        ['protocol'=>'anthropic-messages'],
        ['provider'=>'other'],
        ['base_url'=>null],
        ['protocol'=>null],
        ['provider'=>null],
    ] as $patch) {
        $repository->rows[2]->configuration = array_replace($original, $patch);
        $repository->rows[2]->cipher = $secret->seal('new-target-key', 7);
        $reads = $repository->rows[2]->secretReads;
        $requests = count($history);
        try {
            $config = $service->resolveSnapshot(7, $snapshot);
            $factory($config)->models();
            throw new \LogicException('可信目标变更必须以 409 拒绝，禁止旧地址使用新 key');
        } catch (\RuntimeException $e) {
            catalogExpect($e->getCode() === 409, '可信目标不一致必须返回 409');
        }
        catalogExpect($repository->rows[2]->secretReads === $reads, '目标不一致不得读取密文或解密');
        catalogExpect(count($history) === $requests, '目标不一致不得发送请求');
    }
    $repository->rows[2]->configuration = $original;
    $repository->rows[2]->cipher = $secret->seal('rotated-key', 7);
    $runtime = $service->resolveSnapshot(7, $snapshot);
    catalogExpect($runtime['base_url'] === 'https://example.com/v1' && $runtime['model'] === 'chosen' && $runtime['api_key'] === 'rotated-key', '同一可信目标允许 key 轮换，仍保留冻结模型');
    $repository->rows[2]->configuration['enabled'] = false;
    try { $service->resolveSnapshot(7, $snapshot); throw new \LogicException('停用必须 fail closed'); } catch (\RuntimeException) {}
    try { $service->models(7, 2); throw new \LogicException('停用档案不得获取目录'); } catch (\RuntimeException) {}
    $repository->rows[2]->configuration['enabled'] = true;
    foreach (['reasoning_effort'=>'high', 'fallback_enabled'=>true] as $field=>$value) {
        $blocked = $snapshot;
        $blocked['configuration'][$field] = $value;
        $blocked['configuration']['fallback_models'] = ['backup'];
        try { $service->resolveSnapshot(7, $blocked); throw new \LogicException('不支持能力必须在恢复工具前拒绝'); } catch (\InvalidArgumentException) {}
    }
    $repository->rows[2]->configuration = array_replace($repository->rows[2]->configuration, ['model'=>'test','max_output_tokens'=>100,'fallback_enabled'=>true,'fallback_models'=>['backup'],'reasoning_effort'=>'high','model_capabilities'=>array_map(static fn ($model) => ['model'=>$model,'reasoning_efforts'=>['high'],'context_window'=>8000,'max_output_tokens'=>500], ['test','backup'])]);
    $frozen = $service->snapshot(7, 2);
    $repository->rows[2]->configuration['fallback_models'] = ['edited'];
    $repository->rows[2]->configuration['model_capabilities'] = [];
    $resolved = $service->resolveSnapshot(7, $frozen);
    catalogExpect($resolved['fallback_models'] === ['backup'] && $resolved['model_capabilities'][1]['reasoning_efforts'] === ['high'], '候选与能力必须冻结，不读取后续编辑');
    $repository->rows[2]->configuration['protocol'] = 'anthropic-messages';
    try { $service->models(7, 2); throw new \LogicException('历史未实现协议必须拒绝'); }
    catch (\InvalidArgumentException) {}
    catalogExpect(count($history) === 1, '越权和未实现协议不得发送请求');
    $method = new \ReflectionMethod(\app\console\controller\ai\Profiles::class, 'models');
    $route = $method->getAttributes(\think\annotation\route\Post::class)[0]->newInstance();
    catalogExpect($route->rule === ':id/models', '目录 API 使用 POST id/models');
    $migration = dirname(__DIR__) . '/database/migrations/119_ai_profile_catalog_permission.sql';
    catalogExpect(is_file($migration), '模型目录需要新增权限迁移，不得修改 118');
    $sql = file_get_contents($migration);
    catalogExpect(str_contains($sql, 'console/ai.profiles') && str_contains($sql, "'models'") && str_contains($sql, "'configure'"), '仅向 configure 授权目录 action');
    $row = (new \ReflectionClass(\app\console\ai\model\AiConfigurationProfile::class))->newInstanceWithoutConstructor();
    $weak = new \ReflectionProperty(\think\Model::class, 'weakMap');
    $map = $weak->getValue() ?? new \WeakMap();
    $map[$row] = ['mapping'=>[], 'get'=>[], 'withAttr'=>[], 'data'=>['id'=>2,'name'=>'能力','configuration'=>$frozen['configuration'],'is_default'=>false,'secret_ciphertext'=>'','created_at'=>null,'updated_at'=>null]];
    $weak->setValue(null, $map);
    $public = (new \ReflectionMethod($service, 'publicRecord'))->invoke($service, $row);
    catalogExpect(($public['capabilities']['source'] ?? null) === 'administrator' && ($public['runtime_capabilities']['fallback'] ?? false) === true, '档案读写响应提供能力选择与来源');
    catalogExpect(!str_contains(json_encode($public), 'rotated-key'), '能力响应不包含凭据');
    catalogExpect($public['capabilities']['image_input'] === false && $public['capabilities']['image_tokens'] === 32768, '真实模型能力响应默认拒绝图片');
    catalogExpect(($public['runtime_capabilities']['max_http_body_bytes'] ?? null) === 20971520, '真实响应暴露 HTTP 体积上限');
    echo "AI saved profile catalog: PASS\n";
}
