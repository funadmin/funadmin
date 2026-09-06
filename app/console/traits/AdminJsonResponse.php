<?php

declare(strict_types=1);

namespace app\backend\traits;

use app\common\traits\JsonResponse;
use think\facade\Session;

/**
 * 管理后台 JSON 响应协议。
 */
trait AdminJsonResponse
{
    use JsonResponse;

    protected function responseHeaders(): array
    {
        return ['X-CSRF-TOKEN' => (string) Session::get('__token__', '')];
    }
}
