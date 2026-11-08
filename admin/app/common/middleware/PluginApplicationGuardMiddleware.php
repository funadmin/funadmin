<?php

declare(strict_types=1);

namespace app\common\middleware;

use app\common\plugin\sdk\ActivationGate;
use app\common\plugin\sdk\ActivationUnavailableException;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\plugin\sdk\PluginNotActiveException;
use app\common\http\JsonEnvelope;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 通过 URL、控制器归属或显式路由组阻断不可用插件应用。 */
final class PluginApplicationGuardMiddleware implements MiddlewareInterface
{
    private const CORE_APPLICATIONS = ['app', 'admin', 'api', 'index', 'install', 'common'];

    public function __construct(
        private readonly ?PluginActivationReader $reader = null,
        private readonly ?string $pluginsPath = null,
        private readonly ?string $pluginCode = null
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $target = $this->target($request);
        if ($target === null) {
            return $next($request);
        }
        [$code, $application, $explicit] = $target;
        $reader = $this->reader ?? new PluginActivationReader(runtime_path('plugins/activation'));
        $bundle = $reader->readBundle();
        $snapshot = $bundle['activation'];
        // 随核心发布的业务模块没有独立插件注册记录；一旦被注册则遵守插件状态。
        $bundled = in_array($code, (array) config('plugins.bundled', []), true)
            && !isset($snapshot->plugins()[$code])
            && !$bundle['ownership']->owns($code, $application)
            && !$this->hasManifest($code);
        if ($bundled) {
            return $next($request);
        }
        if (!$snapshot->isTrusted()) {
            if ($explicit) {
                return JsonEnvelope::create('服务暂不可用', null, 503, 503);
            }
            $ownership = $bundle['ownership'];
            if (!$ownership->isTrusted() || !$ownership->owns($code, $application)) {
                return $next($request);
            }
            return JsonEnvelope::create('服务暂不可用', null, 503, 503);
        }
        if (!$explicit && !isset($snapshot->plugins()[$code])) {
            return $next($request);
        }
        try {
            (new ActivationGate($snapshot))->assertEnabled($code, $application);
        } catch (ActivationUnavailableException) {
            return JsonEnvelope::create('服务暂不可用', null, 503, 503);
        } catch (PluginNotActiveException) {
            return JsonEnvelope::create('页面不存在', null, 404, 404);
        }
        \app\common\plugin\sdk\PluginVendorAutoloader::ensure(rtrim($this->pluginsPath ?? base_path('plugins'), '/\\') . '/' . $code);
        return $next($request);
    }

    /** @return null|array{string, string, bool} */
    private function target(Request $request): ?array
    {
        if ($this->pluginCode !== null) {
            return [$this->pluginCode, 'admin', true];
        }
        // Core hook discovery is independent of plugins whose code matches its URL prefix.
        if ((string) $request->controller === \app\common\plugin\controller\FrontendHooks::class) {
            return null;
        }
        if (preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\/', (string) $request->controller, $owner) === 1) {
            return [$owner[1], 'admin', true];
        }
        if (preg_match('/^app\\\\([a-z][a-z0-9]*)\\\\controller\\\\/', (string) $request->controller, $owner) === 1
            && !in_array($owner[1], self::CORE_APPLICATIONS, true)) {
            return [$owner[1], 'app', $this->hasManifest($owner[1])];
        }
        $segments = array_values(array_filter(explode('/', trim($request->path(), '/')), static fn (string $segment): bool => $segment !== ''));
        if (($segments[0] ?? '') === 'admin' && in_array(($segments[1] ?? ''), ['plugin', 'plugins'], true)) {
            $code = (string) ($segments[2] ?? '');
            return preg_match('/^[a-z][a-z0-9]*$/', $code) === 1 ? [$code, 'admin', true] : null;
        }
        $code = (string) ($segments[0] ?? '');
        if ($code === '' || in_array($code, self::CORE_APPLICATIONS, true) || preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            return null;
        }
        return [$code, 'app', $this->hasManifest($code)];
    }

    private function hasManifest(string $code): bool
    {
        return is_file(rtrim($this->pluginsPath ?? base_path('plugins'), DIRECTORY_SEPARATOR) . '/' . $code . '/plugin.json');
    }
}
