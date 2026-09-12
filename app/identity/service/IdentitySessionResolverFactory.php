<?php

declare(strict_types=1);

namespace app\identity\service;

use Closure;
use RuntimeException;
use think\facade\Log;
use think\Request;
use Throwable;

final class IdentitySessionResolverFactory
{
    public static function make(): IdentitySessionResolverInterface
    {
        $configured = trim((string) config('oauth.identity_session_resolver', NativeIdentitySessionResolver::class));
        $class = $configured !== '' ? $configured : NativeIdentitySessionResolver::class;

        if (!class_exists($class)) {
            self::fail('class_not_found', hash('sha256', $class));
        }

        try {
            $resolver = app()->make($class);
        } catch (Throwable) {
            self::fail('construction_failed', hash('sha256', $class));
        }

        if ($resolver instanceof IdentitySessionResolverInterface) {
            return $resolver;
        }
        if (is_callable($resolver)) {
            return new CallableIdentitySessionResolver(Closure::fromCallable($resolver));
        }

        self::fail('contract_mismatch', hash('sha256', $class));
    }

    private static function fail(string $reason, string $classHash): never
    {
        Log::error('identity.session_resolver.configuration_error', [
            'reason' => $reason,
            'class_hash' => $classHash,
        ]);
        throw new RuntimeException('Identity session resolver configuration is invalid');
    }
}

final class CallableIdentitySessionResolver implements IdentitySessionResolverInterface
{
    private Closure $resolver;

    public function __construct(Closure $resolver)
    {
        $this->resolver = $resolver;
    }

    public function resolve(Request $request): ?array
    {
        $identity = ($this->resolver)($request);
        if (is_array($identity) || $identity === null) {
            return $identity;
        }

        throw new RuntimeException('Identity session resolver returned an invalid result');
    }
}
