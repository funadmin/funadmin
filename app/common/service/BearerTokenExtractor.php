<?php

declare(strict_types=1);

namespace app\common\service;

use think\Request;

/**
 * 从 HTTP Authorization Header 提取 Bearer Token。
 */
class BearerTokenExtractor
{
    public function extract(Request $request): ?string
    {
        $authorization = trim((string) $request->header('Authorization', ''));
        if (!preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
