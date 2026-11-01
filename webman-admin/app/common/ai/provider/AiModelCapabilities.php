<?php

declare(strict_types=1);
namespace app\common\ai\provider;

use InvalidArgumentException;

/** 能力仅来自管理员逐模型声明，不使用名称、供应商标签或目录元数据推断。 */
final class AiModelCapabilities
{
    public const REASONING_EFFORTS = ['low','medium','high','xhigh','max','ultra'];
    public const IMAGE_TOKENS = 32768;
    public const MAX_HTTP_BODY_BYTES = 20 * 1024 * 1024;
    public const IMAGE_DEFAULTS = ['image_input'=>false, 'image_tokens'=>self::IMAGE_TOKENS, 'max_images'=>4, 'image_mime_types'=>['image/png','image/jpeg','image/webp']];

    public static function normalize(mixed $entries): array
    {
        if (!is_array($entries) || !array_is_list($entries) || count($entries) > 100) throw new InvalidArgumentException('model_capabilities 必须是有序声明列表', 400);
        $seen = [];
        foreach ($entries as &$entry) {
            if ($entry instanceof \stdClass) $entry = get_object_vars($entry);
            if (!is_array($entry) || array_diff(array_keys($entry), ['model','reasoning_efforts','output_token_parameter','context_window','max_output_tokens','image_input','image_tokens','max_images','image_mime_types'])) throw new InvalidArgumentException('模型能力声明字段无效', 400);
            $model = $entry['model'] ?? null;
            if (!is_string($model) || trim($model) !== $model || $model === '' || strlen($model) > 200 || preg_match('/[\x00-\x1f\x7f]/', $model) || isset($seen[$model])) throw new InvalidArgumentException('模型能力标识无效或重复', 400);
            $seen[$model] = true;
            $entry += ['reasoning_efforts'=>[], 'output_token_parameter'=>'max_tokens', 'context_window'=>null, 'max_output_tokens'=>null];
            $entry += self::IMAGE_DEFAULTS;
            if (!is_bool($entry['image_input']) || $entry['image_tokens'] !== self::IMAGE_TOKENS) throw new InvalidArgumentException('图片能力必须显式布尔声明，图片预算固定为 32768', 400);
            if (!is_int($entry['max_images']) || $entry['max_images'] < 1 || $entry['max_images'] > 4) throw new InvalidArgumentException('图片数量上限必须为 1 至 4', 400);
            $mimes = $entry['image_mime_types'];
            if (!is_array($mimes) || !array_is_list($mimes) || !$mimes || count($mimes) > 3) throw new InvalidArgumentException('图片 MIME 声明无效', 400);
            foreach ($mimes as $mime) if (!in_array($mime, self::IMAGE_DEFAULTS['image_mime_types'], true)) throw new InvalidArgumentException('图片 MIME 不支持', 400);
            if (count(array_unique($mimes)) !== count($mimes)) throw new InvalidArgumentException('图片 MIME 重复', 400);
            $efforts = $entry['reasoning_efforts'];
            if (!is_array($efforts) || !array_is_list($efforts) || count($efforts) > count(self::REASONING_EFFORTS)) throw new InvalidArgumentException('推理能力必须是档位列表', 400);
            foreach ($efforts as $effort) if (!in_array($effort, self::REASONING_EFFORTS, true)) throw new InvalidArgumentException('推理档位无效', 400);
            if (count(array_unique($efforts)) !== count($efforts)) throw new InvalidArgumentException('推理档位不得重复', 400);
            if (!in_array($entry['output_token_parameter'], ['max_tokens','max_completion_tokens'], true)) throw new InvalidArgumentException('输出上限协议字段无效', 400);
            foreach (['context_window','max_output_tokens'] as $field) if ($entry[$field] !== null && (!is_int($entry[$field]) || $entry[$field] < 1 || $entry[$field] > 10000000)) throw new InvalidArgumentException('模型预算能力无效', 400);
        }
        unset($entry);
        return $entries;
    }

    public static function forModel(array $config, string $model): array
    {
        foreach (self::normalize($config['model_capabilities'] ?? []) as $entry) {
            if ($entry['model'] === $model) return $entry + ['source'=>'administrator', 'unknown_policy'=>'reject'];
        }
        return ['model'=>$model, 'reasoning_efforts'=>[], 'output_token_parameter'=>'max_tokens', 'context_window'=>null, 'max_output_tokens'=>null, 'source'=>'unknown', 'unknown_policy'=>'reject'] + self::IMAGE_DEFAULTS;
    }

    /** 冻结与恢复都执行；不允许跳过不兼容备选或降低 effort、截断消息。 */
    public static function validateSelection(array $config): void
    {
        $effort = $config['reasoning_effort'] ?? null;
        if ($effort === 'default') $effort = null;
        if (!in_array($effort, [null, ...self::REASONING_EFFORTS], true)) throw new InvalidArgumentException('reasoning_effort 无效', 400);
        $fallback = $config['fallback_enabled'] ?? false;
        if (!is_bool($fallback)) throw new InvalidArgumentException('fallback_enabled 必须是布尔值', 400);
        $models = $fallback ? ($config['fallback_models'] ?? []) : [];
        if (!is_array($models) || !array_is_list($models) || ($fallback && (count($models) < 1 || count($models) > 3))) throw new InvalidArgumentException('备用模型数量必须为 1 至 3', 400);
        $primary = (string) ($config['model'] ?? '');
        $seen = [$primary];
        foreach ($models as $model) {
            if (!is_string($model) || $model === '' || trim($model) !== $model || strlen($model) > 200 || preg_match('/[\x00-\x1f\x7f]/', $model) || in_array($model, $seen, true)) throw new InvalidArgumentException('备用模型无效或重复', 400);
            $seen[] = $model;
        }
        foreach ($seen as $model) {
            $cap = self::forModel($config, $model);
            if ($effort !== null && !in_array($effort, $cap['reasoning_efforts'], true)) throw new InvalidArgumentException('当前模型的 reasoning_effort 能力未验证或不匹配', 400);
            if ($fallback && ($cap['context_window'] === null || $cap['max_output_tokens'] === null || ($config['max_output_tokens'] ?? null) === null)) throw new InvalidArgumentException('备用要求所有候选声明上下文、输出能力和明确输出预算', 400);
            $output = $config['max_output_tokens'] ?? null;
            if ($output !== null && (($cap['max_output_tokens'] !== null && $output > $cap['max_output_tokens']) || ($cap['context_window'] !== null && $output + ($config['max_input_tokens'] ?? 0) > $cap['context_window']))) throw new InvalidArgumentException('模型能力与任务预算不匹配', 400);
        }
    }
}
