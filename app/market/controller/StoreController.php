<?php

declare(strict_types=1);

namespace app\market\controller;

use app\BaseController;
use app\market\service\MarketAccountService;
use app\market\service\MarketSettings;
use think\facade\Session;
use think\facade\View;
use think\Response;

/** 前台页面基类：会话、CSRF、公共模板变量与安全响应头。 */
abstract class StoreController extends BaseController
{
    protected ?array $member = null;

    protected function initialize(): void
    {
        parent::initialize();
        $this->member = MarketAccountService::current();
    }

    protected function render(string $template, array $vars = [], int $status = 200): Response
    {
        $base = MarketSettings::publicUrl();
        View::assign([
            'siteName' => (string) (syscfg('site', 'site_name') ?: 'FunAdmin'),
            'siteCopyright' => (string) (syscfg('site', 'site_copyright') ?: ''),
            'siteIcp' => (string) (syscfg('site', 'site_icp') ?: ''),
            'base' => $base,
            'basePath' => $this->basePath(),
            'assets' => '/plugin-assets/market/public',
            'member' => $this->member,
            'csrf' => self::csrfToken(),
            'flash' => (string) Session::pull('market_flash', ''),
            'flashError' => (string) Session::pull('market_flash_error', ''),
            'title' => '',
        ]);
        return $this->secure(response(View::fetch($template, $vars), $status));
    }

    protected function secure(Response $response): Response
    {
        return $response->header([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
        ]);
    }

    public static function csrfToken(): string
    {
        $token = (string) Session::get('market_csrf', '');
        if (strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set('market_csrf', $token);
        }
        return $token;
    }

    protected function checkCsrf(): bool
    {
        $submitted = (string) ($this->request->post('_csrf') ?: $this->request->header('x-csrf-token', ''));
        return $submitted !== '' && hash_equals(self::csrfToken(), $submitted);
    }

    protected function flash(string $message, bool $error = false): void
    {
        Session::set($error ? 'market_flash_error' : 'market_flash', $message);
    }

    protected function to(string $path): Response
    {
        return redirect($this->basePath() . $path);
    }

    /** 登录后跳转只接受站内 /market 相对路径，避免开放重定向。 */
    protected function safeNext(string $next): string
    {
        $base = $this->basePath();
        return (str_starts_with($next, $base . '/') || $next === $base) && !str_contains($next, '//') && !str_contains($next, '\\') ? $next : $base . '/user';
    }

    protected function requireMember(): ?Response
    {
        if ($this->member !== null) {
            return null;
        }
        return redirect($this->basePath() . '/login?next=' . rawurlencode($this->request->url()));
    }

    protected function basePath(): string
    {
        return '/' . MarketSettings::CODE;
    }

    protected function errorPage(string $message, int $status = 404): Response
    {
        return $this->render('store/error', ['title' => '提示', 'message' => $message], $status);
    }
}
