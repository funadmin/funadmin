<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\common\form\validation\FormSchemaDataValidator;

function validationExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$fixturePath = __DIR__ . '/form-schema-v2-validation.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$validator = new FormSchemaDataValidator();

validationExpect(($fixture['protocolVersion'] ?? null) === 2, '共享验证 fixtures 必须声明协议版本 2');
validationExpect(count(array_unique($fixture['rules'] ?? [])) === 20, '共享验证 fixtures 必须覆盖 20 类规则');

foreach ($fixture['cases'] as $case) {
    $errors = $validator->validateField(
        (string) $case['field'],
        $case['values'][$case['field']] ?? null,
        $case['rules'],
        $case['values']
    );
    $actualValid = $errors === [];
    validationExpect($actualValid === $case['valid'], $case['name'] . '：有效性不符合共享协议');
    if ($actualValid) {
        continue;
    }
    validationExpect(array_column($errors, 'rule') === $case['failedRules'], $case['name'] . '：失败规则或顺序不正确');
    validationExpect(($errors[0]['field'] ?? null) === ($case['path'] ?? $case['field']), $case['name'] . '：错误字段路径不正确');
    if (isset($case['message'])) {
        validationExpect(($errors[0]['message'] ?? null) === $case['message'], $case['name'] . '：自定义消息未保留');
    }
    if (isset($case['severities'])) {
        validationExpect(array_column($errors, 'severity') === $case['severities'], $case['name'] . '：严重级别未保留');
    }
}

$documentErrors = $validator->validate([
    'email' => 'invalid',
    'tags' => 'not-an-array',
], [
    'email' => [['type' => 'format', 'value' => 'email']],
    'tags' => [['type' => 'array']],
]);
validationExpect(array_column($documentErrors, 'field') === ['email', 'tags'], '整表验证必须按字段规则顺序返回错误');

foreach ($fixture['unsafePatterns'] as $pattern) {
    validationExpect(!FormSchemaDataValidator::isPatternSafe($pattern), '兼容子集必须直接拒绝正则：' . $pattern);
    $errors = $validator->validateField('value', 'aaaaaaaaaaaaaaaaaaaaaaaa!', [['type' => 'pattern', 'value' => $pattern]], ['value' => 'aaaaaaaaaaaaaaaaaaaaaaaa!']);
    validationExpect(($errors[0]['rule'] ?? null) === 'pattern', '不安全或不兼容正则必须快速拒绝：' . $pattern);
}

echo "form schema v2 validation backend tests: PASS\n";
