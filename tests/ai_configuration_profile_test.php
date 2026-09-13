<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\service\AiConfigurationProfileService as Profiles;
use app\console\ai\service\AiProfileSecret;
use Defuse\Crypto\Key;

function profileExpect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function profileReject(callable $operation): void {
    try { $operation(); } catch (InvalidArgumentException|RuntimeException $e) { return; }
    throw new LogicException('应拒绝非法配置');
}
profileExpect(class_exists(Profiles::class), '缺少档案服务');
$input = ['name'=>'工作', 'provider'=>'custom', 'protocol'=>'openai-chat', 'base_url'=>'https://example.com/v1', 'model'=>'unknown-model'];
$value = Profiles::validate($input);
profileExpect($value['reasoning_effort'] === null && $value['fallback_enabled'] === false, '默认不启用推理和备用');
profileExpect($value['context_window'] === null, '未知能力不得猜测上下文');
profileExpect(Profiles::validate($input + ['fallback_enabled'=>true, 'fallback_models'=>['b','a']])['fallback_models'] === ['b','a'], '备用顺序');
foreach ([['name'=>''], ['provider'=>[]], ['protocol'=>'bogus'], ['base_url'=>'https://user:pass@example.com'], ['base_url'=>'https://example.com?api_key=secret'], ['model'=>false], ['max_iterations'=>'3'], ['max_retries'=>-1], ['stream_usage'=>1], ['fallback_enabled'=>true], ['fallback_models'=>['a','a']], ['favorite_models'=>['x'=> 'a']], ['reasoning_effort'=>'guess'], ['context_window'=>100,'max_input_tokens'=>90,'max_output_tokens'=>20], ['connect_timeout'=>61,'request_timeout'=>60], ['admin_id'=>9], ['api_key'=>[]]] as $patch) {
    profileReject(fn () => Profiles::validate(array_replace($input, $patch)));
}
foreach ([['protocol'=>'anthropic-messages'], ['protocol'=>'openai-responses'], ['protocol'=>'gemini'], ['connect_timeout'=>31], ['request_timeout'=>301], ['max_retries'=>4], ['base_url'=>'http://example.com/v1'], ['base_url'=>'https://127.0.0.1/v1']] as $patch) {
    profileReject(fn () => Profiles::validate(array_replace($input, $patch)));
}
$secret = new AiProfileSecret(Key::createNewRandomKey()->saveToAsciiSafeString());
$cipher = $secret->seal('test-key', 7);
profileExpect(!str_contains($cipher, 'test-key') && $secret->open($cipher, 7) === 'test-key', '可信加密往返');
profileReject(fn () => $secret->open($cipher, 8));
profileReject(fn () => $secret->open($cipher . 'x', 7));
foreach (['', 'funadmin-local-change-me', str_repeat('x', 64)] as $key) profileReject(fn () => (new AiProfileSecret($key))->seal('test-key', 7));
echo "AI configuration profile validation/secret: PASS\n";
