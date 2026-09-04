<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2019 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace app;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

/**
 * 应用异常处理类
 */
class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     *
     * @access public
     * @param  Throwable $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        // 使用内置的方式记录异常日志
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @access public
     * @param \think\Request   $request
     * @param Throwable $e
     * @return Response
     */
    public function render($request, Throwable $e): Response
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }
        if ($this->app->http->getName() !== 'api') {
            return parent::render($request, $e);
        }

        [$status, $message] = $this->apiError($e);
        return json([
            'code' => $status,
            'msg' => $message,
            'time' => $request->server('REQUEST_TIME', time()),
            'data' => [],
        ], $status);
    }

    /**
     * 将框架异常转换为稳定的 API 错误。
     *
     * @return array{0: int, 1: string}
     */
    private function apiError(Throwable $e): array
    {
        if ($e instanceof ValidateException) {
            return [400, $e->getMessage()];
        }
        if ($e instanceof ModelNotFoundException || $e instanceof DataNotFoundException) {
            return [404, 'Resource not found'];
        }
        if ($e instanceof HttpException) {
            $status = $e->getStatusCode();
            return [$status, $status >= 500 ? 'Internal server error' : ($e->getMessage() ?: 'Request failed')];
        }

        return [500, $this->app->isDebug() ? $e->getMessage() : 'Internal server error'];
    }
}
