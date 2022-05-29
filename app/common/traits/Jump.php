<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\common\traits;
use think\exception\HttpResponseException;
use think\App;
use think\facade\Request;
use think\Response;
trait Jump
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;
    
    public function __construct()
    {
        $this->request = Request::instance();
    }
    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param  mixed $msg 提示信息
     * @param  string $url 跳转的URL地址
     * @param  mixed $data 返回的数据
     * @param  integer $wait 跳转等待时间
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', string $url = null, $data = '', int $wait = 3, array $header = [])
    {
        if (is_null($url) && isset($_SERVER["HTTP_REFERER"])) {
            $url = $_SERVER["HTTP_REFERER"];
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string)app()->route->buildUrl($url);
        }

        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];
        $header['__token__'] = $this->request->buildToken();
        $type = $this->getResponseType();
        // 把跳转模板的渲染下沉，这样在 response_send 行为里通过getData()获得的数据是一致性的格式
        if ('html' == strtolower($type)) {
            $type = 'view';
            $response = Response::create(app('config')->get('app.dispatch_success_tmpl'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param  mixed $msg 提示信息
     * @param  string $url 跳转的URL地址
     * @param  mixed $data 返回的数据
     * @param  integer $wait 跳转等待时间
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', string $url = null, $data = '', int $wait = 3, array $header = [])
    {
        if (is_null($url)) {
            $url = $this->request->isAjax() ? '' : 'javascript:history.back(-1);';
        } elseif ($url) {
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : (string)app()->route->buildUrl($url);
        }
        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];
        $type = $this->getResponseType();
        $header['__token__'] = $this->request->buildToken();
        if ('html' == strtolower($type)) {
            $type = 'view';
            $response = Response::create(app('config')->get('app.dispatch_error_tmpl'), $type)->assign($result)->header($header);
        } else {
            $response = Response::create($result, $type)->header($header);
        }

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param  mixed $data 要返回的数据
     * @param  integer $code 返回的code
     * @param  mixed $msg 提示信息
     * @param  string $type 返回数据格式
     * @param  array $header 发送的Header信息
     * @return void
     */
    protected function result($data, $code = 0, $msg = '', $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];
        $header['__token__'] = $this->request->buildToken();
        $type = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param  string $url 跳转的URL表达式
     * @param  integer $code http code
     * @param  array $with 隐式传参
     * @return void
     */
    protected function redirect($url, $code = 302, $with = [])
    {
        $response = Response::create($url, 'redirect');

        $response->code($code)->with($with);

        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        return $this->request->isJson() || $this->request->isAjax() ? 'json' : 'html';
    }
}
