<?php

declare(strict_types=1);

namespace app\market\service;

/**
 * 版本约束匹配。语义与客户端安装时的 DependencyValidator::matches() 逐条相同（包括不补齐 8.0 这类版本），
 * 保证市场判定为兼容的版本在客户端同样能通过 requires 检查。
 */
final class VersionConstraint
{
    public static function matches(string $version, string $constraint): bool
    {
        foreach (preg_split('/\s*,\s*|\s+/', trim($constraint)) ?: [] as $part) {
            if ($part === '') {
                continue;
            }
            if (str_starts_with($part, '^')) {
                $minimum = substr($part, 1);
                $major = (int) explode('.', $minimum)[0];
                if (version_compare($version, $minimum, '<') || version_compare($version, ($major + 1) . '.0.0', '>=')) {
                    return false;
                }
                continue;
            }
            if (!preg_match('/^(>=|<=|>|<|=)?(.+)$/', $part, $matches)) {
                return false;
            }
            if (!version_compare($version, $matches[2], $matches[1] ?: '=')) {
                return false;
            }
        }
        return true;
    }
}
