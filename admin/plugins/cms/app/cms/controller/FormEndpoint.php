<?php

declare(strict_types=1);

namespace app\cms\controller;

use OpenApi\Annotations as OA;

use app\cms\service\{FormCaptcha, FormFileStorage, FormGuard, FormMessages, FormRenderer, FormService, FormSubmissionService, FormFieldCatalog, FormValidationException, SiteService};
use app\common\service\authentication\BearerTokenExtractor;
use app\common\service\member\MemberApiSessionService;
use InvalidArgumentException;
use support\annotation\route\{Get, Post, RouteGroup};
use support\{Log, Request, Response};
use Throwable;
use Webman\Http\UploadFile;
use Webman\Limiter\Limiter;

/**
 * 公开表单接口：提交、刷新令牌、获取验证码。站点只由请求域名确定；表单代码只在该站点内查找。
 * 请求头 `Accept: application/json` 时返回 JSON，否则渲染无 JS 结果页。
 */
#[RouteGroup('/cms/forms')]
final class FormEndpoint
{
    private const CAPTCHA_LIMIT = 30;
    private const CAPTCHA_WINDOW = 600;
    private const NO_STORE = ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache', 'X-Content-Type-Options' => 'nosniff', 'Vary' => 'Accept, Authorization'];

    private readonly FormService $forms;
    private readonly FormGuard $guard;
    private readonly FormCaptcha $captcha;
    private readonly FormSubmissionService $submissions;

    public function __construct(?FormSubmissionService $submissions = null, ?FormGuard $guard = null, ?FormCaptcha $captcha = null, ?FormService $forms = null)
    {
        $this->forms = $forms ?? new FormService();
        $this->guard = $guard ?? new FormGuard();
        $this->captcha = $captcha ?? new FormCaptcha();
        $this->submissions = $submissions ?? new FormSubmissionService($this->forms, new FormFileStorage(), $this->guard, $this->captcha);
    }

    /**
     * 提交自定义表单；Accept: application/json 时返回 JSON，否则渲染结果页。
     *
     * @OA\Post(
     *     path="/cms/forms/{code}",
     *     operationId="cms_controller_FormEndpoint_submit_post",
     *     tags={"CMS 公开表单"},
     *     summary="提交自定义表单；Accept: application/json 时返回 JSON，否则渲染结果页。",
     *     description="站点由请求域名确定，表单代码只在该站点内查找；语言取 _locale/locale 参数（须为已启用语言），否则用站点默认语言。 携带有效会员 Bearer 令牌时记录提交会员（可选）。成功时 JSON 的 code=0。令牌无效或过期返回 419，频率限制返回 429，字段校验失败返回 422。",
     *     security={},
     *     @OA\Parameter(name="code", in="path", required=true, description="表单代码。", @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"_token"},
     *                 description="其余字段为表单定义的字段名；文件字段按表单配置上传（字段名或 字段名[]）。",
     *                 additionalProperties=true,
     *                 @OA\Property(property="_token", type="string", description="由 /cms/forms/{code}/token 获取的提交令牌。"),
     *                 @OA\Property(property="_locale", type="string", description="语言代码。"),
     *                 @OA\Property(property="_captcha_id", type="string", description="验证码 ID（表单启用验证码时必填）。"),
     *                 @OA\Property(property="_captcha", type="string", description="验证码答案。"),
     *                 @OA\Property(property="_source", type="string", description="来源页面地址，只接受站内地址。")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="提交成功：JSON 为 {code:0, data:{message}}，否则为 HTML 结果页。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope"), @OA\MediaType(mediaType="text/html", @OA\Schema(type="string"))),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=419, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=422, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=500, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/{code}')]
    public function submit(Request $request, string $code): Response
    {
        $json = $request->acceptJson();
        $back = $this->source($request);
        [$site, $locale, $form] = $this->resolve($request, $code);
        $ui = FormMessages::for($locale);
        if ($form === null) return $this->failure($json, $site, $locale, 404, ['_form' => 'unavailable'], [], $back);
        $labels = array_column($form['fields'], 'label', 'name') + ['captcha' => $ui['captcha']];
        try {
            [$uploads, $uploadErrors] = $this->uploads($request, $form['fields']);
            if ($uploadErrors) throw new FormValidationException($uploadErrors);
            $post = $request->post();
            $result = $this->submissions->submit($site, $locale, $code, is_array($post) ? $post : [], $uploads, [
                'ip' => $this->ip($request),
                'user_agent' => (string)$request->header('user-agent', ''),
                'source_url' => $back,
                'member_id' => $this->member($request),
                'token' => self::text($request->post('_token')),
                'captcha_id' => self::text($request->post('_captcha_id')),
                'captcha' => self::text($request->post('_captcha')),
            ]);
        } catch (FormValidationException $e) {
            $formError = $e->errors['_form'] ?? '';
            // 防刷拒绝（令牌、过快、限流、验证码）记审计日志，便于发现攻击；普通字段校验错误不记，且从不记录提交内容。
            $reason = $formError !== '' ? $formError : (($e->errors['captcha'] ?? '') === 'captcha_invalid' ? 'captcha_invalid' : '');
            if (in_array($reason, ['token_invalid', 'token_expired', 'too_fast', 'too_many_requests', 'captcha_invalid'], true)) {
                Log::warning('cms.form.rejected', ['reason' => $reason, 'site_id' => (string)$site['id'], 'form' => $code, 'source' => FormGuard::bucket($this->ip($request))]);
            }
            $status = $e->getCode() === 429 ? 429 : (in_array($formError, ['token_invalid', 'token_expired'], true) ? 419 : 422);
            return $this->failure($json, $site, $locale, $status, $e->errors, $labels, $back);
        } catch (InvalidArgumentException $e) {
            if ($e->getCode() === 404) return $this->failure($json, $site, $locale, 404, ['_form' => 'unavailable'], [], $back);
            return $this->failure($json, $site, $locale, 422, ['_form' => 'invalid'], [], $back);
        } catch (Throwable $e) {
            self::logFailure('cms.form.submit_failed', $e, ['site_id' => (string)$site['id'], 'form' => $code]);
            return $this->failure($json, $site, $locale, 500, ['_form' => 'network'], [], $back);
        }
        $message = $result['message'] !== '' ? $result['message'] : $ui['success'];
        if ($json) return $this->json(200, ['code' => 0, 'msg' => 'success', 'data' => ['message' => $message]]);
        return $this->page(200, (string)$site['id'], $locale, ['ok' => true, 'message' => $message, 'problems' => [], 'back' => $back]);
    }

    /**
     * 获取表单提交令牌（data.token）。
     *
     * @OA\Post(
     *     path="/cms/forms/{code}/token",
     *     operationId="cms_controller_FormEndpoint_token_post",
     *     tags={"CMS 公开表单"},
     *     summary="获取表单提交令牌（data.token）。",
     *     description="站点由请求域名确定，表单代码只在该站点内查找；语言取 _locale/locale 参数（须为已启用语言），否则用站点默认语言。",
     *     security={},
     *     @OA\Parameter(name="code", in="path", required=true, description="表单代码。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="_locale", in="query", required=false, description="语言代码。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Post('/{code}/token')]
    public function token(Request $request, string $code): Response
    {
        [, $locale, $form] = $this->resolve($request, $code);
        if ($form === null) return $this->unavailable($locale);
        return $this->json(200, ['code' => 0, 'msg' => 'success', 'data' => ['token' => $this->guard->issue($form['site_id'], $form['id'])]]);
    }

    /**
     * 获取表单验证码；同一来源 10 分钟最多 30 次。
     *
     * @OA\Get(
     *     path="/cms/forms/{code}/captcha",
     *     operationId="cms_controller_FormEndpoint_captcha_get",
     *     tags={"CMS 公开表单"},
     *     summary="获取表单验证码；同一来源 10 分钟最多 30 次。",
     *     description="站点由请求域名确定，表单代码只在该站点内查找；语言取 _locale/locale 参数（须为已启用语言），否则用站点默认语言。",
     *     security={},
     *     @OA\Parameter(name="code", in="path", required=true, description="表单代码。", @OA\Schema(type="string")),
     *     @OA\Parameter(name="_locale", in="query", required=false, description="语言代码。", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="成功。", @OA\JsonContent(ref="#/components/schemas/PluginEnvelope")),
     *     @OA\Response(response=404, ref="#/components/responses/PluginError"),
     *     @OA\Response(response=429, ref="#/components/responses/PluginError"),
     *     @OA\Response(response="default", ref="#/components/responses/PluginError")
     * )
     */
    #[Get('/{code}/captcha')]
    public function captcha(Request $request, string $code): Response
    {
        [$site, $locale, $form] = $this->resolve($request, $code);
        if ($form === null || !$form['settings']['captcha']) return $this->unavailable($locale);
        if (!Limiter::attempt('cms_form_captcha:' . $site['id'] . ':' . FormGuard::bucket($this->ip($request)), self::CAPTCHA_LIMIT, self::CAPTCHA_WINDOW)) {
            $text = FormMessages::for($locale)['errors']['too_many_requests'];
            return $this->json(429, ['code' => 429, 'msg' => $text, 'data' => ['errors' => ['_form' => $text]]]);
        }
        return $this->json(200, ['code' => 0, 'msg' => 'success', 'data' => $this->captcha->issue()]);
    }

    /**
     * 站点由域名确定；语言取请求中的 `_locale`/`locale`（须为站点已启用的语言），否则用站点默认语言。
     *
     * @return array{0:?array,1:string,2:?array}
     */
    private function resolve(Request $request, string $code): array
    {
        $requested = strtolower(self::text($request->post('_locale') ?? $request->get('_locale') ?? $request->get('locale')));
        $site = (new SiteService())->byHost((string)$request->host());
        if ($site === null) return [null, $requested, null];
        $enabled = array_column((new SiteService())->locales($site['id'], true), 'locale');
        $locale = in_array($requested, $enabled, true) ? $requested : (string)$site['default_locale'];
        return [$site, $locale, $this->forms->resolve($site['id'], $code, $locale)];
    }

    /**
     * 把 `$request->file()` 转成 FormSubmissionService 需要的“字段名 → 文件列表”；同时支持 `name[]` 与单文件。
     * 只映射表单中的文件字段，其他键一律忽略；每个字段最多收 max_files + 1 个，校验器仍能报告 too_many_files。
     * 未选择文件的空输入被跳过；其他上传错误转换为字段错误。
     *
     * @param list<array<string,mixed>> $fields 已解析表单的字段
     * @return array{0:array<string,list<array{tmp:string,name:string,size:int}>>,1:array<string,string>}
     */
    private function uploads(Request $request, array $fields): array
    {
        $limits = [];
        foreach ($fields as $field) {
            if (in_array($field['type'], FormFieldCatalog::FILE_TYPES, true)) $limits[$field['name']] = min((int)($field['max_files'] ?? 1), FormFieldCatalog::MAX_FILES) + 1;
        }
        $uploads = [];
        $errors = [];
        foreach ($request->file() as $name => $files) {
            if (!is_string($name) || !isset($limits[$name])) continue;
            $seen = 0;
            foreach (is_array($files) ? $files : [$files] as $file) {
                if (!$file instanceof UploadFile) continue;
                $error = (int)$file->getUploadErrorCode();
                // Workerman 对浏览器提交的空文件输入（filename="" 且无内容）仍标记为 UPLOAD_ERR_OK，这里一并按“未选择文件”跳过。
                if ($error === UPLOAD_ERR_NO_FILE || ($error === UPLOAD_ERR_OK && (string)$file->getUploadName() === '' && (int)$file->getSize() === 0)) continue;
                if (++$seen > $limits[$name]) break;
                if ($error !== UPLOAD_ERR_OK) {
                    $errors[$name] = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'file_too_large' : 'invalid_file_type';
                    continue;
                }
                $uploads[$name][] = ['tmp' => $file->getPathname(), 'name' => (string)$file->getUploadName(), 'size' => (int)$file->getSize()];
            }
        }
        return [$uploads, $errors];
    }

    /**
     * 来源页面与结果页返回链接：只接受站内相对路径，或与当前域名一致的 http(s) 地址（不含反斜杠、控制字符与用户信息）。
     * 结果一律重建为站内相对的“路径 + 查询”，返回链接和保存的来源都不会指向外站。
     */
    private function source(Request $request): string
    {
        $host = strtolower((string)$request->host(true));
        foreach ([$request->post('_source'), $request->header('referer')] as $candidate) {
            $url = trim(self::text($candidate));
            if ($url === '' || strlen($url) > 2048 || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) continue;
            if (str_starts_with($url, '/')) {
                if (!str_starts_with($url, '//')) return $url;
                continue;
            }
            $parts = parse_url($url);
            if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) continue;
            if (isset($parts['user']) || isset($parts['pass']) || strtolower($parts['host'] ?? '') !== $host) continue;
            $authority = substr($url, strlen($parts['scheme']) + 3);
            $authority = substr($authority, 0, strcspn($authority, '/?#'));
            if (str_contains($authority, '@')) continue;
            $path = $parts['path'] ?? '/';
            if (!str_starts_with($path, '/') || str_starts_with($path, '//')) continue;
            return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        return '';
    }

    /**
     * 访客地址（用于限流与记录）。不使用 getRealIp()：它信任 X-Forwarded-For 的第一段，而那一段由客户端任意填写。
     * - 直连地址不是内网/回环地址：直接使用它，忽略所有转发头；
     * - 直连地址是内网/回环（视为受信任的反向代理）：有 X-Forwarded-For 时取其最后一段（即该代理追加的那一段），没有 XFF 时才用 X-Real-IP；
     * - 头部缺失或无效时回落到直连地址，仍无效则为 0.0.0.0。
     */
    private function ip(Request $request): string
    {
        $remote = trim((string)$request->getRemoteIp(), '[]');
        $remote = filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
        if (!Request::isIntranetIp($remote)) return $remote;
        // 有 X-Forwarded-For 时只取最后一段：$proxy_add_x_forwarded_for 追加的就是 nginx 自己看到的地址；
        // 只设置 XFF 的旧配置会原样透传访客伪造的 X-Real-IP，所以 X-Real-IP 只在没有 XFF 时使用。
        $forwarded = trim(self::text($request->header('x-forwarded-for')));
        if ($forwarded !== '') {
            $hops = explode(',', $forwarded);
            $last = trim((string)end($hops));
            return filter_var($last, FILTER_VALIDATE_IP) ? $last : $remote;
        }
        $real = trim(self::text($request->header('x-real-ip')));
        return filter_var($real, FILTER_VALIDATE_IP) ? $real : $remote;
    }

    /**
     * 记录意外异常：只记异常类型、消息、位置与不含参数的调用栈，从不记录访客提交的值。
     * 数据库异常的消息里带有 SQL 绑定值，因此只记录不含绑定值的 SQL。
     */
    private static function logFailure(string $message, Throwable $e, array $context): void
    {
        $text = $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : $e->getMessage();
        $trace = array_map(static fn(array $frame): string => ($frame['file'] ?? '?') . ':' . ($frame['line'] ?? 0) . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''), array_slice($e->getTrace(), 0, 15));
        Log::error($message, $context + ['exception' => get_class($e), 'message' => mb_substr($text, 0, 500), 'code' => $e->getCode(), 'file' => $e->getFile() . ':' . $e->getLine(), 'trace' => $trace]);
    }

    /** 会员只通过现有的 Bearer 令牌识别；无效或缺失时不记录。 */
    private function member(Request $request): ?string
    {
        $token = (new BearerTokenExtractor())->extract($request);
        if ($token === null) return null;
        try {
            $id = (new MemberApiSessionService())->authenticate($token)['id'] ?? null;
        } catch (Throwable $e) {
            self::logFailure('cms.form.member_lookup_failed', $e, []);
            return null;
        }
        return $id === null ? null : (string)$id;
    }

    /** @param array<string,string> $errors 字段名 → 错误键（`_form` 为表单级） @param array<string,string> $labels */
    private function failure(bool $json, ?array $site, string $locale, int $status, array $errors, array $labels, string $back): Response
    {
        $ui = FormMessages::for($locale);
        $texts = [];
        foreach ($errors as $field => $key) $texts[$field] = in_array($key, ['unavailable', 'network'], true) ? $ui[$key] : FormMessages::error($ui, (string)$key);
        $message = $texts['_form'] ?? reset($texts) ?: $ui['errors']['invalid'];
        if ($json) return $this->json($status, ['code' => $status, 'msg' => $message, 'data' => ['errors' => $texts]]);
        $problems = [];
        foreach ($texts as $field => $text) $problems[] = ['label' => $field === '_form' ? '' : (string)($labels[$field] ?? ''), 'text' => $text];
        return $this->page($status, (string)($site['id'] ?? ''), $locale, ['ok' => false, 'message' => $message, 'problems' => $problems, 'back' => $back]);
    }

    private function unavailable(string $locale): Response
    {
        $text = FormMessages::for($locale)['unavailable'];
        return $this->json(404, ['code' => 404, 'msg' => $text, 'data' => ['errors' => ['_form' => $text]]]);
    }

    private function page(int $status, string $siteId, string $locale, array $variables): Response
    {
        $variables += ['locale' => $locale, 'ui' => FormMessages::for($locale)];
        if ($variables['back'] === '') $variables['back'] = '/';
        $html = (new FormRenderer())->result($siteId, $locale, $variables);
        return new Response($status, ['Content-Type' => 'text/html; charset=utf-8'] + self::NO_STORE, $html);
    }

    private function json(int $status, array $body): Response
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8'] + self::NO_STORE, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
