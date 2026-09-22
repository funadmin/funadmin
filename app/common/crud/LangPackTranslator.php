<?php

declare(strict_types=1);

namespace app\common\crud;

use app\common\ai\provider\OpenAiCompatibleGateway;
use GuzzleHttp\Client;
use think\facade\Cache;

/**
 * CRUD 生成期的 AI 预翻译器：批量把 {key: 中文} 译为英文草稿。
 * 未配置供应商或调用失败一律返回空数组，由 ProductionTemplateContext 降级为 key 派生占位。
 */
final class LangPackTranslator
{
    /** @var callable|null 测试注入网关工厂，避免真实网络调用。 */
    private $gatewayFactory;

    public function __construct(?callable $gatewayFactory = null)
    {
        $this->gatewayFactory = $gatewayFactory;
    }

    /**
     * @param array<string,string> $zhMap key => 中文文案
     * @return array<string,string> key => 英文（仅含成功翻译的非空项）
     */
    public function translate(array $zhMap): array
    {
        if ($zhMap === []) return [];
        try {
            $config = (array) config('ai.provider', []);
        } catch (\Throwable) {
            return [];
        }
        foreach (['name', 'api_key', 'base_url', 'model'] as $required) {
            if ((string) ($config[$required] ?? '') === '') return [];
        }
        // preview 与 commit 各渲染一次，按输入哈希缓存避免同一批文案两次翻译漂移。
        $cacheKey = 'crud_lang_translate_' . hash('sha256', CrudDefinition::canonicalJson($zhMap));
        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) return $cached;
        } catch (\Throwable) {
        }
        try {
            $gateway = $this->gatewayFactory !== null
                ? ($this->gatewayFactory)($config)
                : new OpenAiCompatibleGateway(new Client(), $config);
            $reply = $gateway->chat([
                ['role' => 'system', 'content' => '你是软件界面翻译器。把用户给出的 JSON 对象（key => 中文文案）翻译为英文，原样返回 JSON 对象（key => English）。必须原样保留 {label}、{:target} 等占位符，只输出 JSON，不要解释。'],
                ['role' => 'user', 'content' => json_encode($zhMap, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            ]);
            $content = (string) ($reply['content'] ?? '');
            if (preg_match('/\{.*\}/s', $content, $match) !== 1) return [];
            $decoded = json_decode($match[0], true);
            if (!is_array($decoded)) return [];
            $translations = [];
            foreach (array_keys($zhMap) as $key) {
                $value = $decoded[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $translations[$key] = trim($value);
                }
            }
            try {
                Cache::set($cacheKey, $translations, 86400);
            } catch (\Throwable) {
            }
            return $translations;
        } catch (\Throwable) {
            return [];
        }
    }
}
