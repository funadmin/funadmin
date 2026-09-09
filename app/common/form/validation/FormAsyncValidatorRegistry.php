<?php

declare(strict_types=1);

namespace app\common\form\validation;

/**
 * 服务端异步验证器注册表，仅执行受信任的注册键。
 */
final class FormAsyncValidatorRegistry
{
    /** @param array<string, callable|array{handler: callable, capabilityVersion?: int|string}> $validators */
    public function __construct(private readonly array $validators = [])
    {
    }

    /** 返回可安全参与依赖检查与哈希计算的验证器元数据。 */
    public function definitions(): array
    {
        return array_map(static fn (mixed $definition): array => [
            'capabilityVersion' => (string) (is_array($definition) ? ($definition['capabilityVersion'] ?? '1') : '1'),
        ], $this->validators);
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $options
     */
    public function validate(string $key, mixed $value, array $values, array $options = []): ?string
    {
        $definition = $this->validators[$key] ?? null;
        $validator = is_array($definition) ? ($definition['handler'] ?? null) : $definition;
        if (!is_callable($validator)) {
            throw new FormAsyncValidationException('FORM_ASYNC_VALIDATOR_NOT_REGISTERED');
        }
        $result = $validator($value, $values, $options);
        if ($result === true || $result === null) {
            return null;
        }
        return is_string($result) && $result !== '' ? $result : '字段校验失败';
    }
}
