<?php

declare(strict_types=1);

namespace app\admin\authentication\service;

use app\admin\authentication\model\AdminAuthToken;
use app\admin\authentication\model\BrowserSession;
use app\admin\authentication\model\LoginAttempt;

/**
 * 过期认证数据清理（auth:prune）：只做软删除标记（deleted_at + deleted_token），不物理删除。
 *
 * 模型的软删除全局作用域保证只处理尚未删除的行；已软删除的行保持原样、永久保留。
 */
final class AuthPruneService
{
    /** 登录尝试计数窗口（秒），与登录限流一致。 */
    public const LOGIN_ATTEMPT_WINDOW = 900;

    /**
     * @return array{tokens:int, sessions:int, attempts:int} 本次新标记为已删除的行数。
     */
    public function prune(?int $now = null): array
    {
        $now ??= time();
        $expiry = date('Y-m-d H:i:s', $now);

        return [
            'tokens' => (int) AdminAuthToken::query()->where('expires_at', '<=', $expiry)->delete(),
            'sessions' => (int) BrowserSession::query()->where('expires_at', '<=', $expiry)->delete(),
            'attempts' => (int) LoginAttempt::query()->where('window_start', '<=', $now - self::LOGIN_ATTEMPT_WINDOW)->delete(),
        ];
    }
}
