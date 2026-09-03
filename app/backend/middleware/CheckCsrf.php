<?php
/**
 * 后台请求 CSRF 校验。
 */

namespace app\backend\middleware;

use app\common\traits\Jump;
use think\facade\Session;

class CheckCsrf
{
    use Jump;

    public function handle($request, \Closure $next)
    {
        $this->request = $request;
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $sessionToken = (string) Session::get('__token__', '');
            $headerToken = (string) $request->header('X-CSRF-TOKEN', '');
            $formToken = (string) $request->param('__token__', '');
            $validHeader = $headerToken !== '' && hash_equals($sessionToken, $headerToken);
            $validForm = $formToken !== '' && hash_equals($sessionToken, $formToken);
            if ($sessionToken === '' || (!$validHeader && !$validForm)) {
                $this->error(lang('Token verify error'));
            }
        }

        return $next($request);
    }
}
