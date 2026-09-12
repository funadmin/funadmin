<?php

declare(strict_types=1);

namespace app\console\development\http;

final class BusinessResponseSanitizer
{
    private const SECRET_KEYS = [
        'sensitive', 'confirmtoken', 'confirm_token', 'trustedbundle', 'trusted_bundle',
    ];

    public static function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return is_string($value) && self::isAbsolutePath($value) ? '[REDACTED]' : $value;
        }
        $clean = [];
        foreach ($value as $key => $item) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, self::SECRET_KEYS, true)) continue;
            if ($normalized === 'routepath' && is_string($item) && self::isWebRoute($item)) {
                $clean[$key] = $item;
                continue;
            }
            if (self::isPathKey($normalized) && is_string($item) && self::isAbsolutePath($item)) {
                $clean[$key] = '[REDACTED]';
                continue;
            }
            $clean[$key] = self::sanitize($item);
        }
        return $clean;
    }

    private static function isPathKey(string $key): bool
    {
        return $key === 'path' || in_array($key, [
            'absolutepath', 'absolute_path', 'filesystempath', 'filesystem_path',
            'base_storage_path', 'stage_path', 'backup_path',
        ], true);
    }

    private static function isWebRoute(string $value): bool
    {
        return preg_match('#^/[a-z][a-z0-9-]*(?:/[a-z][a-z0-9-]*)*$#', $value) === 1;
    }

    private static function isAbsolutePath(string $value): bool
    {
        return str_starts_with($value, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $value) === 1;
    }
}
