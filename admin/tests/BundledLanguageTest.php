<?php

declare(strict_types=1);
namespace Tests;
use app\middleware\LoadLangPackMiddleware;
use PHPUnit\Framework\TestCase;
use support\Request;
use support\Response;
use support\Translation;
final class BundledLanguageTest extends TestCase
{
    public function test_catalogues_preserve_all_legacy_keys_and_have_english_placeholder_parity(): void
    {
        $expected = json_decode((string) file_get_contents(__DIR__.'/fixtures/legacy-language-messages.json'), true);
        $catalogues = [];
        foreach (['zh-cn', 'en-us'] as $locale) {
            $catalogues[$locale] = require app_path("common/lang/$locale/messages.php");
        }
        ksort($expected);
        ksort($catalogues['zh-cn']);
        ksort($catalogues['en-us']);
        self::assertSame($expected, $catalogues['zh-cn']);
        self::assertSame(array_keys($catalogues['zh-cn']), array_keys($catalogues['en-us']));
        // 模块语言包（业务模块、插件各自的 lang/<语言>.php）与公共包同样要求中英键与占位符一致。
        $modules = array_merge(glob(app_path('admin/business/*/lang/zh-cn.php')) ?: [], glob(app_path('admin/plugins/*/lang/zh-cn.php')) ?: []);
        self::assertNotEmpty($modules);
        foreach ($modules as $zhFile) {
            $enFile = dirname($zhFile) . '/en-us.php';
            self::assertFileExists($enFile);
            foreach ((require $zhFile) as $group => $messages) {
                foreach ($messages as $key => $value) $catalogues['zh-cn']["$group.$key"] = $value;
            }
            foreach ((require $enFile) as $group => $messages) {
                foreach ($messages as $key => $value) $catalogues['en-us']["$group.$key"] = $value;
            }
        }
        self::assertSame(array_keys($catalogues['zh-cn']), array_keys($catalogues['en-us']));
        foreach ($catalogues['zh-cn'] as $key => $value) {
            preg_match_all('/%[a-z]|\{[a-z_]+\}/', $value, $zh);
            preg_match_all('/%[a-z]|\{[a-z_]+\}/', $catalogues['en-us'][$key], $en);
            self::assertSame($zh[0], $en[0], $key);
            self::assertDoesNotMatchRegularExpression('/[\x{4e00}-\x{9fff}]/u', $catalogues['en-us'][$key], $key);
        }
    }
    public function test_module_language_packs_live_with_their_module_and_are_loaded_automatically(): void
    {
        self::assertDirectoryDoesNotExist(app_path('admin/lang'), '模块消息跟随模块，公共消息只在 common/lang');
        $middleware = new LoadLangPackMiddleware();
        foreach (['zh-cn' => '记录仍被 fun_x.order_id 引用，无法删除', 'en-us' => 'The record is still referenced by fun_x.order_id and cannot be deleted'] as $locale => $expected) {
            $middleware->process(new Request("GET /?locale=$locale HTTP/1.1\r\n\r\n"), static fn () => new Response(204));
            self::assertSame($expected, trans('order.referenced', ['{target}' => 'fun_x.order_id']), $locale);
            self::assertNotSame('AuthName', trans('AuthName'), '公共包仍然可用');
        }
    }

    public function test_requests_do_not_accumulate_resources_and_nested_keys_translate(): void
    {
        $middleware = new LoadLangPackMiddleware();
        $resources = new \ReflectionProperty(\Symfony\Component\Translation\Translator::class, 'resources');
        $counts = [];
        foreach (['zh-cn','en-us','zh-cn'] as $locale) {
            $middleware->process(new Request("GET /?locale=$locale HTTP/1.1\r\n\r\n"), static fn()=>new Response(204));
            $translator = Translation::instance();
            $counts[] = $resources->getValue($translator);
            self::assertSame($locale === 'zh-cn' ? '权限名' : 'Permission name', $translator->trans('AuthName'));
            self::assertSame($locale === 'zh-cn' ? '首页' : 'Home', $translator->trans('Home'));
            self::assertSame($translator->trans('AuthName'), __('AuthName'));
            self::assertSame($translator->trans('AuthName'), lang('AuthName'));
        }
        self::assertSame($counts[0],$counts[1]);
        self::assertSame($counts[0],$counts[2]);
    }
    public function test_generated_packs_follow_translator_identity_and_custom_paths(): void
    {
        $path = sys_get_temp_dir().'/funadmin-language-'.bin2hex(random_bytes(6));
        mkdir($path.'/en-us', 0777, true);
        file_put_contents($path.'/en-us/entity.php', "<?php return ['entity'=>['label'=>'Custom label']];");
        try {
            foreach ([new \Symfony\Component\Translation\Translator('en-us'), new \Symfony\Component\Translation\Translator('en-us')] as $translator) {
                $translator->addLoader('phpfile', new \Symfony\Component\Translation\Loader\PhpFileLoader());
                \app\common\service\translation\GeneratedLanguagePacks::register($translator, [$path]);
                \app\common\service\translation\GeneratedLanguagePacks::register($translator, [$path]);
                self::assertSame('Custom label', $translator->trans('entity.label'));
                $resources = (new \ReflectionProperty($translator, 'resources'))->getValue($translator);
                // 重复注册不累积；模块语言包另外注册，这里只统计自定义路径的资源。
                self::assertCount(1, array_filter($resources['en-us'], static fn (array $resource): bool => str_starts_with((string) $resource[1], $path)));
            }
        } finally {
            unlink($path.'/en-us/entity.php');
            rmdir($path.'/en-us');
            rmdir($path);
        }
    }
}
