<?php

declare(strict_types=1);
namespace app\admin\ai\service;

use InvalidArgumentException;

/** 档案仅保存显式配置，不推断模型能力，也不执行供应商协议。 */
final class AiConfigurationProfileService
{
    public function __construct(
        private readonly \app\admin\ai\repository\DatabaseAiProfileRepository $repository,
        private readonly AiProfileSecret $secrets,
        private readonly ?\Closure $gatewayFactory = null
    ) {}

    public static function production(): self
    {
        return new self(new \app\admin\ai\repository\DatabaseAiProfileRepository(), new AiProfileSecret((string) config('oauth.encryption_key', '')));
    }

    /** 冻结经校验的配置，不读取密钥。 */
    public function snapshot(int $adminId, int $id, ?string $model = null, ?string $reasoningEffort = null): array
    {
        $row = $this->repository->find($adminId, $id);
        $config = self::validate(array_merge($row->configuration, ['name'=>$row->name], $model === null ? [] : ['model'=>$model], $reasoningEffort === null ? [] : ['reasoning_effort'=>$reasoningEffort]));
        if (!$config['enabled']) throw new \RuntimeException('档案已停用', 409);
        unset($config['api_key']);
        return ['profile_id'=>$id, 'configuration'=>$config, 'model'=>$config['model'], 'url_version'=>1];
    }

    /** 恢复只按引用读取当前凭据；当前档案配置不得覆盖冻结参数。 */
    public function resolveSnapshot(int $adminId, array $snapshot): array
    {
        if (!is_int($snapshot['profile_id'] ?? null) || $snapshot['profile_id'] <= 0 || !is_array($snapshot['configuration'] ?? null)) throw new InvalidArgumentException('档案快照无效', 400);
        $row = $this->repository->find($adminId, $snapshot['profile_id']);
        if (($row->configuration['enabled'] ?? true) !== true) throw new \RuntimeException('档案已停用', 409);
        if (array_key_exists('api_key', $snapshot['configuration'])) throw new InvalidArgumentException('快照不得包含密钥', 400);
        $config = self::validate($snapshot['configuration']);
        if (($snapshot['model'] ?? null) !== $config['model']) throw new InvalidArgumentException('模型快照不一致', 400);
        // 历史根域指向无版本端点，不能套用新规则后再注入当前密钥。
        $legacyBase = rtrim(trim($config['base_url']), '/');
        if ((!array_key_exists('url_version', $snapshot) && \app\common\ai\provider\OpenAiCompatibleGateway::normalizeBaseUrl($legacyBase) !== $legacyBase)
            || (array_key_exists('url_version', $snapshot) && $snapshot['url_version'] !== 1)) {
            throw new \RuntimeException('档案 URL 规则已变更，请创建新任务', 409);
        }
        // 当前凭据仅可用于完全相同的可信目标，目标变更必须在读取密文和解密前停止。
        foreach (['base_url', 'protocol', 'provider'] as $field) {
            if (($row->configuration[$field] ?? null) !== $config[$field]) throw new \RuntimeException('档案可信目标已变更，请创建新任务', 409);
        }
        $cipher = (string) $row->getAttr('secret_ciphertext');
        $config['api_key'] = $cipher === '' ? '' : $this->secrets->open($cipher, $adminId);
        return $config;
    }

    /** 使用保存配置和当前凭据；越权、已删除或历史未实现协议在请求前拒绝。 */
    public function models(int $adminId, int $id): array
    {
        $row = $this->repository->find($adminId, $id);
        $config = self::validate(array_merge($row->configuration, ['name'=>$row->name]));
        if (!$config['enabled']) throw new \RuntimeException('档案已停用', 409);
        $cipher = (string) $row->getAttr('secret_ciphertext');
        $config['api_key'] = $cipher === '' ? '' : $this->secrets->open($cipher, $adminId);
        $gateway = $this->gatewayFactory !== null
            ? ($this->gatewayFactory)($config)
            : new \app\common\ai\provider\OpenAiCompatibleGateway(new \GuzzleHttp\Client(), $config);
        return array_map(static fn ($model) => $model + ['capabilities'=>self::capabilities($config, $model['id'])], $gateway->models());
    }

    private function publicRecord(\app\admin\ai\model\AiConfigurationProfile $row): array
    {
        return array_merge($row->configuration, [
            'capabilities'=>self::capabilities($row->configuration, (string) ($row->configuration['model'] ?? '')),
            'runtime_capabilities'=>['reasoning_efforts'=>\app\common\ai\provider\AiModelCapabilities::REASONING_EFFORTS, 'default_omits_parameter'=>true, 'capability_source'=>'administrator', 'unknown_policy'=>'reject', 'fallback'=>true, 'stream_fallback'=>false, 'max_fallback_models'=>3, 'max_requests'=>12, 'max_reserved_seconds'=>300, 'private_image_input'=>true, 'max_http_body_bytes'=>\app\common\ai\provider\AiModelCapabilities::MAX_HTTP_BODY_BYTES],
            'id'=>(int) $row->id, 'name'=>$row->name, 'is_default'=>(bool) $row->is_default, 'has_api_key'=>(string) $row->getAttr('secret_ciphertext') !== '', 'created_at'=>$row->created_at, 'updated_at'=>$row->updated_at,
        ]);
    }

    public function list(int $adminId): array { return array_map(fn ($row) => $this->publicRecord($row), $this->repository->all($adminId)); }
    public function read(int $adminId, int $id): array { return $this->publicRecord($this->repository->find($adminId, $id)); }
    public function default(int $adminId): ?array
    {
        foreach ($this->list($adminId) as $row) if ($row['is_default']) return $row;
        return null;
    }

    private function attributes(int $adminId, array $data): array
    {
        $attributes = ['name'=>$data['name']];
        unset($data['name']);
        if (array_key_exists('api_key', $data)) {
            $attributes['secret_ciphertext'] = $data['api_key'] === '' ? null : $this->secrets->seal($data['api_key'], $adminId);
            unset($data['api_key']);
        }
        $attributes['configuration'] = $data;
        return $attributes;
    }

    public function create(int $adminId, array $input): array
    {
        if ($adminId <= 0) throw new \RuntimeException('未登录', 401);
        return $this->publicRecord($this->repository->create($adminId, $this->attributes($adminId, self::validate($input))));
    }

    /** PATCH 语义：未传密钥保留，空字符串清空；所有字段先合并校验再原子保存。 */
    public function update(int $adminId, int $id, array $input): array
    {
        return $this->repository->transaction(function () use ($adminId, $id, $input) {
            $row = $this->repository->find($adminId, $id, true);
            $data = self::validate(array_replace($row->configuration, ['name'=>$row->name], $input));
            foreach (['base_url','protocol','provider'] as $field) {
                if (($row->configuration[$field] ?? null) !== $data[$field] && !array_key_exists('api_key', $input) && (string) $row->getAttr('secret_ciphertext') !== '') {
                    throw new InvalidArgumentException('可信目标已变更，请明确提供新密钥或清空旧密钥', 400);
                }
            }
            $row->save($this->attributes($adminId, $data));
            return $this->publicRecord($row);
        });
    }

    public function copy(int $adminId, int $id, string $name): array
    {
        $row = $this->repository->find($adminId, $id);
        return $this->create($adminId, array_merge($row->configuration, ['name'=>$name]));
    }

    public function makeDefault(int $adminId, int $id): array { return $this->publicRecord($this->repository->makeDefault($adminId, $id)); }
    public function delete(int $adminId, int $id): bool
    {
        return $this->repository->transaction(function () use ($adminId, $id) {
            return $this->repository->find($adminId, $id, true)->delete();
        });
    }

    public static function capabilities(array $config, string $model): array
    {
        return \app\common\ai\provider\AiModelCapabilities::forModel($config, $model);
    }

    public static function validate(array $input): array
    {
        $defaults = ['model_capabilities'=>[], 'enabled'=>true, 'favorite_models'=>[], 'fallback_enabled'=>false, 'fallback_models'=>[], 'context_window'=>null, 'max_input_tokens'=>null, 'max_output_tokens'=>null, 'max_iterations'=>10, 'reasoning_effort'=>null, 'stream_usage'=>false, 'connect_timeout'=>5, 'request_timeout'=>60, 'max_retries'=>2];
        $allowed = array_merge(array_keys($defaults), ['name','provider','protocol','base_url','model','api_key']);
        if (array_diff(array_keys($input), $allowed)) throw new InvalidArgumentException('包含未知或只读字段', 400);
        $data = array_replace($defaults, $input);
        foreach (['name'=>100,'provider'=>64,'protocol'=>32,'base_url'=>2048,'model'=>200] as $field=>$max) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '' || strlen($data[$field]) > $max || preg_match('/[\x00-\x1f\x7f]/', $data[$field])) throw new InvalidArgumentException($field . ' 无效', 400);
            $data[$field] = trim($data[$field]);
        }
        if (!preg_match('/^[a-z0-9][a-z0-9._-]*$/', $data['provider'])) throw new InvalidArgumentException('provider 无效', 400);
        new \app\common\ai\provider\AiProtocol($data['protocol']);
        // 这里只校验地址，未验证类型的密钥等字段不得提前交给网关。
        new \app\common\ai\provider\OpenAiCompatibleGateway(new \GuzzleHttp\Client(), ['base_url'=>$data['base_url']]);
        $url = parse_url($data['base_url']);
        if (!filter_var($data['base_url'], FILTER_VALIDATE_URL) || !in_array($url['scheme'] ?? '', ['https','http'], true) || empty($url['host']) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) throw new InvalidArgumentException('base_url 必须是不含认证、查询和片段的 HTTP(S) 地址', 400);
        foreach (['enabled','fallback_enabled','stream_usage'] as $field) if (!is_bool($data[$field])) throw new InvalidArgumentException($field . ' 必须是布尔值', 400);
        foreach (['context_window'=>[1,10000000], 'max_input_tokens'=>[1,10000000], 'max_output_tokens'=>[1,10000000], 'max_iterations'=>[1,100], 'connect_timeout'=>[1,30], 'request_timeout'=>[1,300], 'max_retries'=>[0,3]] as $field=>[$min,$max]) {
            if ($data[$field] === null && in_array($field, ['context_window','max_input_tokens','max_output_tokens'], true)) continue;
            if (!is_int($data[$field]) || $data[$field] < $min || $data[$field] > $max) throw new InvalidArgumentException($field . ' 范围无效', 400);
        }
        foreach (['favorite_models','fallback_models'] as $field) {
            if (!is_array($data[$field]) || !array_is_list($data[$field]) || count($data[$field]) > 100) throw new InvalidArgumentException($field . ' 必须是有序列表', 400);
            foreach ($data[$field] as $model) if (!is_string($model) || trim($model) !== $model || $model === '' || strlen($model) > 200 || preg_match('/[\x00-\x1f\x7f]/', $model)) throw new InvalidArgumentException($field . ' 模型无效', 400);
            if (count(array_unique($data[$field])) !== count($data[$field])) throw new InvalidArgumentException($field . ' 不得重复', 400);
        }
        if ($data['fallback_enabled'] && !$data['fallback_models']) throw new InvalidArgumentException('启用备用时必须配置模型', 400);
        if (in_array($data['model'], $data['fallback_models'], true)) throw new InvalidArgumentException('备用模型不得包含主模型', 400);
        if ($data['reasoning_effort'] === 'default') $data['reasoning_effort'] = null;
        if ($data['protocol'] !== 'openai-chat' && $data['reasoning_effort'] !== null) throw new InvalidArgumentException('当前协议暂不支持显式推理模式', 400);
        if ($data['protocol'] === 'anthropic-messages' && $data['max_output_tokens'] === null) throw new InvalidArgumentException('Messages 必须明确设置输出 Token 预算', 400);
        $data['model_capabilities'] = \app\common\ai\provider\AiModelCapabilities::normalize($data['model_capabilities']);
        \app\common\ai\provider\AiModelCapabilities::validateSelection($data);
        if (!in_array($data['reasoning_effort'], [null, ...\app\common\ai\provider\AiModelCapabilities::REASONING_EFFORTS], true)) throw new InvalidArgumentException('reasoning_effort 无效', 400);
        if ($data['context_window'] !== null && ($data['max_input_tokens'] ?? 0) + ($data['max_output_tokens'] ?? 0) > $data['context_window']) throw new InvalidArgumentException('输入与输出预算超过上下文窗口', 400);
        if ($data['connect_timeout'] > $data['request_timeout']) throw new InvalidArgumentException('连接超时不得超过请求超时', 400);
        if (array_key_exists('api_key', $data) && (!is_string($data['api_key']) || strlen($data['api_key']) > 8192 || preg_match('/[\x00-\x20\x7f]/', $data['api_key']))) throw new InvalidArgumentException('api_key 无效', 400);
        return $data;
    }
}
