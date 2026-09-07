<?php

declare(strict_types=1);

namespace app\common\middleware;

use Closure;
use fun\plugins\ActivationGate;
use fun\plugins\ActivationUnavailableException;
use fun\plugins\PluginActivationReader;
use fun\plugins\PluginNotActiveException;
use think\exception\HttpException;

/** 在 MultiApp 解析前阻断不可用插件应用。 */
final class PluginApplicationGuard
{
    private const CORE_APPLICATIONS = ['app', 'console', 'api', 'index', 'install', 'common'];

    public function __construct(private readonly ?PluginActivationReader $reader = null)
    {
    }

    public function handle($request, Closure $next)
    {
        $target = $this->target((string) $request->pathinfo());
        if ($target === null) {
            return $next($request);
        }
        [$code, $application, $explicit] = $target;
        $reader = $this->reader ?? new PluginActivationReader(runtime_path('plugins/activation'));
        $bundle = $reader->readBundle();
        $snapshot = $bundle['activation'];
        if (!$snapshot->isTrusted()) {
            if ($explicit) {
                throw new HttpException(503, '服务暂不可用');
            }
            $ownership = $bundle['ownership'];
            if (!$ownership->isTrusted() || !$ownership->owns($code, $application)) {
                return $next($request);
            }
            throw new HttpException(503, '服务暂不可用');
        }
        if (!$explicit && !isset($snapshot->plugins()[$code])) {
            return $next($request);
        }
        try {
            (new ActivationGate($snapshot))->assertEnabled($code, $application);
        } catch (ActivationUnavailableException) {
            throw new HttpException(503, '服务暂不可用');
        } catch (PluginNotActiveException) {
            throw new HttpException(404, '页面不存在');
        }
        return $next($request);
    }

    /** @return null|array{string, string, bool} */
    private function target(string $path): ?array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $segment): bool => $segment !== ''));
        if (($segments[0] ?? '') === 'console' && ($segments[1] ?? '') === 'plugin') {
            $code = (string) ($segments[2] ?? '');
            return preg_match('/^[a-z][a-z0-9]*$/', $code) === 1 ? [$code, 'console', true] : null;
        }
        $code = (string) ($segments[0] ?? '');
        if ($code === '' || in_array($code, self::CORE_APPLICATIONS, true) || preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            return null;
        }
        return [$code, 'app', false];
    }
}
