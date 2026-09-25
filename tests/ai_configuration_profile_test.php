<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\ai\service\AiConfigurationProfileService as Profiles;
use app\admin\ai\service\AiProfileSecret;
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
$capabilities = array_map(static fn ($model) => ['model'=>$model, 'reasoning_efforts'=>['low','medium','high','xhigh','max','ultra'], 'output_token_parameter'=>'max_completion_tokens', 'context_window'=>8000, 'max_output_tokens'=>1000], ['unknown-model','b','a']);
$declared = $input + ['model_capabilities'=>$capabilities, 'max_output_tokens'=>500];
profileExpect(Profiles::validate($declared + ['fallback_enabled'=>true, 'fallback_models'=>['b','a']])['fallback_models'] === ['b','a'], '备用顺序');
foreach (['low','medium','high','xhigh','max','ultra'] as $effort) profileExpect(Profiles::validate($declared + ['reasoning_effort'=>$effort])['reasoning_effort'] === $effort, '接受管理员明确声明的推理档位');
profileExpect(Profiles::validate($input + ['reasoning_effort'=>'default'])['reasoning_effort'] === null, 'default 规范为不发送');
foreach ([['reasoning_effort'=>'high'], ['fallback_enabled'=>true,'fallback_models'=>['b']], ['model_capabilities'=>[['model'=>'unknown-model','reasoning_efforts'=>['guess']]]], ['model_capabilities'=>array_merge($capabilities, [$capabilities[0]])], ['model_capabilities'=>[['model'=>'unknown-model','source'=>'official','reasoning_efforts'=>['high']]]]] as $patch) profileReject(fn () => Profiles::validate(array_replace($input, $patch)));
foreach (['xhigh','max','ultra'] as $effort) {
    profileReject(fn () => Profiles::validate($input + ['reasoning_effort'=>$effort]));
    $partial = $capabilities;
    $partial[1]['reasoning_efforts'] = ['high'];
    profileReject(fn () => Profiles::validate(array_replace($declared, ['model_capabilities'=>$partial, 'fallback_enabled'=>true, 'fallback_models'=>['b'], 'reasoning_effort'=>$effort])));
}
foreach ([['ultra','ultra'], ['MAX'], [null], ['default'], ['guess']] as $efforts) profileReject(fn () => Profiles::validate($input + ['model_capabilities'=>[['model'=>'unknown-model','reasoning_efforts'=>$efforts]]]));
$unknown = Profiles::capabilities($input, 'unknown-model');
profileExpect($unknown['source'] === 'unknown' && $unknown['reasoning_efforts'] === [] && $unknown['unknown_policy'] === 'reject', '未知能力默认拒绝');
$known = Profiles::capabilities($declared, 'b');
profileExpect($known['source'] === 'administrator' && $known['reasoning_efforts'] === ['low','medium','high','xhigh','max','ultra'], '响应说明声明来源，不冒充官方验证');
foreach ([['name'=>''], ['provider'=>[]], ['protocol'=>'bogus'], ['base_url'=>'https://user:pass@example.com'], ['base_url'=>'https://example.com?api_key=secret'], ['model'=>false], ['max_iterations'=>'3'], ['max_retries'=>-1], ['stream_usage'=>1], ['fallback_enabled'=>true], ['fallback_models'=>['a','a']], ['favorite_models'=>['x'=> 'a']], ['reasoning_effort'=>'guess'], ['context_window'=>100,'max_input_tokens'=>90,'max_output_tokens'=>20], ['connect_timeout'=>61,'request_timeout'=>60], ['admin_id'=>9], ['api_key'=>[]]] as $patch) {
    profileReject(fn () => Profiles::validate(array_replace($input, $patch)));
}
foreach (['anthropic-messages','openai-responses'] as $protocol) profileExpect(Profiles::validate(array_replace($input, ['protocol'=>$protocol,'max_output_tokens'=>100]))['protocol'] === $protocol, '接受已实现协议');
foreach ([['protocol'=>'gemini'], ['connect_timeout'=>31], ['request_timeout'=>301], ['max_retries'=>4], ['base_url'=>'http://example.com/v1'], ['base_url'=>'https://127.0.0.1/v1']] as $patch) {
    profileReject(fn () => Profiles::validate(array_replace($input, $patch)));
}
profileReject(fn () => Profiles::validate(array_replace($input, ['protocol'=>'anthropic-messages'])));
foreach (['anthropic-messages','openai-responses'] as $protocol) profileReject(fn () => Profiles::validate(array_replace($declared, ['protocol'=>$protocol,'reasoning_effort'=>'high'])));
$secret = new AiProfileSecret(Key::createNewRandomKey()->saveToAsciiSafeString());
$cipher = $secret->seal('test-key', 7);
profileExpect(!str_contains($cipher, 'test-key') && $secret->open($cipher, 7) === 'test-key', '可信加密往返');
profileReject(fn () => $secret->open($cipher, 8));
profileReject(fn () => $secret->open($cipher . 'x', 7));
foreach (['', 'funadmin-local-change-me', str_repeat('x', 64)] as $key) profileReject(fn () => (new AiProfileSecret($key))->seal('test-key', 7));
echo "AI configuration profile validation/secret: PASS\n";
