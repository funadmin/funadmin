<?php

declare(strict_types=1);

namespace app\cms\service;

use app\cms\template\{RenderContext, TagRegistry, ThemeEngine};
use InvalidArgumentException;

/**
 * 前台表单渲染：表单定义只作为数据传入可信的文件模板（当前主题的 `partials/form` 或内置 `_forms/form`），从不被编译。
 * 同一页面多次渲染时，每次生成独立的 DOM 前缀 `uid`。验证码只由 cms-form.js 从受限流的接口获取，渲染时不签发。
 */
final class FormRenderer
{
    public const SCRIPT = '<script src="/plugin-assets/cms/public/cms-form.js" defer></script>';

    public function __construct(private ?FormService $forms = null, private ?FormGuard $guard = null) {}

    /** 浏览器可见的提交地址：公开站点经反向代理把 `/` 映射到内部 `/cms/`，与主题内其他链接一样不带 `/cms` 前缀。 */
    public static function action(string $code): string
    {
        return '/forms/' . rawurlencode($code);
    }

    /** 表单不存在或已停用时返回空串。 */
    public function render(string $code, RenderContext $context): string
    {
        $form = ($this->forms ??= new FormService())->resolve($context->siteId, $code, $context->locale);
        if ($form === null) return '';
        $uid = 'cms-form-' . bin2hex(random_bytes(4));
        $form['fields'] = array_map(fn(array $field): array => $this->field($field, $uid), $form['fields']);
        $variables = [
            'form' => $form,
            'action' => self::action($form['code']),
            'token' => ($this->guard ??= new FormGuard())->issue($form['site_id'], $form['id']),
            'uid' => $uid,
            // 渲染时不签发验证码：否则每次匿名 GET 都会生成图片并写缓存，绕过 /captcha 的按 IP 限流。
            // 只给出空占位，cms-form.js 初始化时从受限流的接口获取；无 JS 访客看到 <noscript> 提示。
            'captcha' => $form['settings']['captcha'] ? ['id' => '', 'image' => ''] : null,
            'ui' => FormMessages::for($context->locale),
            'locale' => $context->locale,
            'source' => is_string($context->page['path'] ?? null) ? $context->page['path'] : '',
        ];
        [$root, $template] = $this->view($context->page['theme'] ?? null);
        return (new ThemeEngine(new TagRegistry(), $root))->renderFile($template, $variables, $context);
    }

    /** 无 JS 提交的结果页（独立的最小 HTML）。 */
    public function result(string $siteId, string $locale, array $variables): string
    {
        return (new ThemeEngine(new TagRegistry(), self::builtin()))->renderFile('result', $variables, new RenderContext($siteId, $locale));
    }

    /** @return array{0:string,1:string} 模板根目录与模板名 */
    private function view(mixed $theme): array
    {
        if (is_string($theme) && $theme !== '') {
            try {
                $theme = Input::template($theme);
            } catch (InvalidArgumentException) {
                $theme = '';
            }
            $base = realpath(dirname(__DIR__) . '/view');
            $root = $theme === '' ? false : realpath(dirname(__DIR__) . '/view/' . $theme);
            if ($base && $root && str_starts_with($root, $base . DIRECTORY_SEPARATOR) && is_file($root . '/partials/form.blade.php')) return [$root, 'partials/form'];
        }
        return [self::builtin(), 'form'];
    }

    private static function builtin(): string
    {
        return dirname(__DIR__) . '/view/_forms';
    }

    private function field(array $field, string $uid): array
    {
        $field['id'] = $uid . '-' . $field['name'];
        if (in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) {
            $extensions = [];
            foreach ($field['accept'] as $item) array_push($extensions, ...(FormFieldCatalog::ACCEPT_CATEGORIES[$item] ?? [$item]));
            $field['accept_attr'] = implode(',', array_map(static fn(string $extension): string => '.' . $extension, array_unique($extensions)));
        }
        if ($field['type'] === 'hidden' && $field['from_query']) {
            $query = self::query($field['name']);
            if ($query !== '') $field['value'] = $query;
        }
        return $field;
    }

    /** 渲染时能拿到当前请求就在服务端读取同名查询参数；拿不到时由 cms-form.js 从地址栏补上。 */
    private static function query(string $name): string
    {
        $request = function_exists('request') ? request() : null;
        $value = $request instanceof \Webman\Http\Request ? $request->get($name) : null;
        if (!is_scalar($value)) return '';
        return mb_substr(FormValueValidator::clean((string)$value, false) ?? '', 0, FormFieldCatalog::TEXT_LIMITS['hidden']);
    }
}
