<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/26
 */

namespace fun\helper;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client;
class HttpHelper
{

    /**
     * 发送一个POST请求
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @param array $options 扩展参数
     * @return mixed|string
     */
    public static function post($url, $params = [], $header = [], $options = [], $cookies = [])
    {
        $req = self::sendRequest($url, $params, 'POST', $header, $options, $cookies);
        return $req['ret'] ? $req['msg'] : '';
    }

    /**
     * 发送一个GET请求
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @param array $options 扩展参数
     * @return mixed|string
     */
    public static function get($url, $params = [], $header = [], $options = [], $cookies = [])
    {
        $req = self::sendRequest($url, $params, 'GET', $header, $options, $cookies);
        return $req['ret'] ? $req['msg'] : '';
    }

    /**
     * CURL发送Request请求,含POST和REQUEST
     * @param string $url 请求的链接
     * @param mixed $params 传递的参数
     * @param string $method 请求的方法
     * @param mixed $options CURL的参数
     * @return array
     */
    public static function sendRequest($url, $params = [], $method = 'POST', $header = [], $options = [], $cookies = [])
    {
        try {
            $client = self::getClient($options, $header,$cookies);
            $response = $client->request($method, $url, strtoupper($method)=='GET' ? ['query' => $params] : ['form_params'=>$params])->getBody()->getContents();
            if (!empty($response)) {
                return ['ret' => true, 'msg' => $response];
            }
        } catch (\Throwable $e) {
            return ['ret' => false, 'msg' => $e->getMessage()];
        }
        return ['ret' => false, 'msg' => $response];
       
    }

    /**
     * 异步发送一个请求
     * @param $url
     * @param $params
     * @param $method
     * @param $header
     * @param $options
     * @param $cookies
     * @return mixed|string
     */
    public static function sendAsyncRequest($url, $params = [], $method = 'POST', $header = [], $options = [], $cookies = [])
    {
        try {
            $client = self::getClient($options, $header,$cookies);
            $promise  = $client->requestAsync($method, $url, strtoupper($method)=='GET' ? ['query' => $params] : ['form_params'=>$params])->getBody()->getContents();
            $promise->then(function ($response) {
                if ($response->getStatusCode() == 200) {
                    return ['ret' => true, 'msg' => $response];
                }
            });
        } catch (\Throwable $e) {
            return ['ret' => false, 'msg' => $e->getMessage()];
        }
        return ['ret' => false, 'msg' => $response];;
    }

    /**
     * 发送文件到客户端
     * @param string $file
     * @param bool $delaftersend
     * @param bool $exitaftersend
     */
    public static function sendToBrowser($file, $delaftersend = true, $exitaftersend = true)
    {
        if (file_exists($file) && is_readable($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment;filename = ' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check = 0, pre-check = 0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            if ($delaftersend) {
                unlink($file);
            }
            if ($exitaftersend) {
                exit;
            }
        }
    }

    /**
     * 下载并保存
     * @param $url
     * @param $savePath
     * @param string $params
     * @param array $header
     * @param int $timeout
     * @return false|mixed
     */
    public static function download($url, $filename = "", $timeout = 60)
    {
        if (empty($filename)) {
            $filename = public_path() . 'temp' . DS . pathinfo($url, PATHINFO_BASENAME);
        }
        $path = dirname($filename);
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            return false;
        }
        $client = self::getClient();
        $file = fopen($filename, 'w+');
        $httpClient = new Client();
        $response = $httpClient->get($url, [RequestOptions::SINK => $file]);
        if ($response->getStatusCode() === 200) {
          return $filename;
        }
        return  false;
    }

    /** php 接收流文件
     * @param String $file 接收后保存的文件名
     * @return boolean
     */
    public static function receiveStreamFile($receiveFile)
    {
        $streamData = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
        if (empty($streamData)) {
            $streamData = file_get_contents('php://input');
        }
        if ($streamData != '') {
            $ret = file_put_contents($receiveFile, $streamData, true);
        } else {
            $ret = false;
        }
        return $ret;
    }

    /** php 发送流文件
     * @param String $url 接收的路径
     * @param String $file 要发送的文件
     * @return boolean
     */
    public static function sendStreamFile($url, $file)
    {
        if (file_exists($file)) {
            $opts = array(
                'http' => array(
                    'method' => 'POST',
                    'header' => 'content-type:application/x-www-form-urlencoded',
                    'content' => file_get_contents($file)
                )
            );
            $context = stream_context_create($opts);
            $response = file_get_contents($url, false, $context);
            $ret = json_decode($response, true);
            return $ret['success'];
        } else {
            return false;
        }
    }


    /**
     * 获取访问客户端
     * @param array $options
     * @param array $header
     * @return mixed
     */
    private static function getClient(array $options = [], array $header = [], array $cookies= [])
    {
        if (empty($options)) {
            $options = [
                'timeout'         => 60,
                'connect_timeout' => 60,
                'verify'          => false,
                'http_errors'     => false,
                'headers'         => [
                    'X-REQUESTED-WITH' => 'XMLHttpRequest',
                    'Referer'          => dirname(request()->url()),
                ]
            ];
        }

        if (!empty($header)) {
            $options['headers'] = array_merge($options['headers'], $header);
        }

        if (!empty($cookies)) {
            $options['cookies'] = $cookies;
        }

        return new Client($options);
    }
}
