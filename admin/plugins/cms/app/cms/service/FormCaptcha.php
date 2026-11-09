<?php

declare(strict_types=1);

namespace app\cms\service;

use support\Cache;
use Webman\Captcha\CaptchaBuilder;
use Webman\Captcha\PhraseBuilder;
use Webman\Captcha\PhraseBuilderInterface;

/** 无状态验证码：答案存缓存 300 秒，以签名的验证码 ID 关联；校验一次即作废，比较不区分大小写。 */
final class FormCaptcha
{
    public const TTL = 300;
    /** 去掉易混淆的 i、l、o、0、1。 */
    private const CHARSET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private readonly string $secret;
    private readonly PhraseBuilderInterface $phrases;

    public function __construct(?string $secret = null, ?PhraseBuilderInterface $phrases = null)
    {
        $this->secret = FormGuard::secret($secret);
        $this->phrases = $phrases ?? new PhraseBuilder(5, self::CHARSET);
    }

    /** @return array{id:string,image:string} `image` 为 data URI。 */
    public function issue(): array
    {
        $builder = new CaptchaBuilder(null, $this->phrases);
        $builder->build();
        $random = bin2hex(random_bytes(16));
        $id = $random . '.' . $this->sign($random);
        Cache::set(self::key($id), strtolower((string)$builder->getPhrase()), self::TTL);
        return ['id' => $id, 'image' => $builder->inline()];
    }

    public function verify(string $id, string $answer): bool
    {
        $parts = explode('.', $id);
        if (count($parts) !== 2 || !preg_match('/^[0-9a-f]{32}$/D', $parts[0]) || !hash_equals($this->sign($parts[0]), $parts[1])) return false;
        $key = self::key($id);
        $expected = Cache::get($key);
        Cache::delete($key);
        $answer = strtolower(trim($answer));
        return is_string($expected) && $expected !== '' && $answer !== '' && hash_equals($expected, $answer);
    }

    private function sign(string $random): string
    {
        return hash_hmac('sha256', 'captcha|' . $random, $this->secret);
    }

    private static function key(string $id): string
    {
        return 'cms_form_captcha_' . hash('sha256', $id);
    }
}
