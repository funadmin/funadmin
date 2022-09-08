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
        $method = strtoupper($method);
        $protocol = substr($url, 0, 5);
        $query_string = is_array($params) ? http_build_query($params) : $params;

        $ch = curl_init();
        $defaults = [];
        if ('GET' == $method) {
            $geturl = $query_string ? $url . (stripos($url, "?") !== false ? "&" : "?") . $query_string : $url;
            $defaults[CURLOPT_URL] = $geturl;
        } else {
            $defaults[CURLOPT_URL] = $url;
            if ($method == 'POST') {
                $defaults[CURLOPT_POST] = 1;
            } else {
                $defaults[CURLOPT_CUSTOMREQUEST] = $method;
            }
            $defaults[CURLOPT_POSTFIELDS] = $query_string;
        }
        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);

        }
        $defaults[CURLOPT_HEADER] = false;
        $defaults[CURLOPT_USERAGENT] = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.98 Safari/537.36";
        $defaults[CURLOPT_FOLLOWLOCATION] = true;
        $defaults[CURLOPT_RETURNTRANSFER] = true;
        $defaults[CURLOPT_CONNECTTIMEOUT] = 3;
        $defaults[CURLOPT_TIMEOUT] = 3;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header ?: array('Expect:'));
        if ('https' == $protocol) {
            $defaults[CURLOPT_SSL_VERIFYPEER] = false;
            $defaults[CURLOPT_SSL_VERIFYHOST] = false;
        }
        curl_setopt_array($ch, empty($options) ? $defaults: (array)$options+$defaults);
        $ret = curl_exec($ch);
        $err = curl_error($ch);

        if (false === $ret || !empty($err)) {
            $errno = curl_errno($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);
            return [
                'ret' => false,
                'errno' => $errno,
                'msg' => $err,
                'info' => $info,
            ];
        }
        curl_close($ch);
        return [
            'ret' => true,
            'msg' => $ret,
        ];
    }

    /**
     * 异步发送一个请求
     * @param string $url 请求的链接
     * @param mixed $params 请求的参数
     * @param string $method 请求的方法
     * @return boolean TRUE
     */
    public static function sendAsyncRequest($url, $params = [], $method = 'POST')
    {
        $method = strtoupper($method);
        $method = $method == 'POST' ? 'POST' : 'GET';
        //构造传递的参数
        if (is_array($params)) {
            $post_params = [];
            foreach ($params as $k => &$v) {
                if (is_array($v)) {
                    $v = implode(',', $v);
                }
                $post_params[] = $k . '=' . urlencode($v);
            }
            $post_string = implode('&', $post_params);
        } else {
            $post_string = $params;
        }
        $parts = parse_url($url);
        //构造查询的参数
        if ($method == 'GET' && $post_string) {
            $parts['query'] = isset($parts['query']) ? $parts['query'] . '&' . $post_string : $post_string;
            $post_string = '';
        }
        $parts['query'] = isset($parts['query']) && $parts['query'] ? '?' . $parts['query'] : '';
        //发送socket请求,获得连接句柄
        $fp = fsockopen($parts['host'], isset($parts['port']) ? $parts['port'] : 80, $errno, $errstr, 3);
        if (!$fp) {
            return false;
        }
        //设置超时时间
        stream_set_timeout($fp, 3);
        $out = "{$method} {$parts['path']}{$parts['query']} HTTP/1.1\r\n";
        $out .= "Host: {$parts['host']}\r\n";
        $out .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $out .= "Content-Length: " . strlen($post_string) . "\r\n";
        $out .= "Connection: Close\r\n\r\n";
        if ($post_string !== '') {
            $out .= $post_string;
        }
        fwrite($fp, $out);
        //echo fread($fp, 1024);
        fclose($fp);
        return true;
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
    public static function download($url, $savePath,$saveName, $method = 'GET', $params = '', $header = [], $cookies = [], $timeout = 3600)
    {
        if(!is_dir($savePath)){
            FileHelper::mkdirs($savePath);
        }
        @touch($saveName);
        $fp = fopen($savePath.$savePath, 'wb');
        $protocol = substr($url, 0, 5);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if ('https' == $protocol) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, FALSE); //需要response body
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header ?: ['Expect:']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_BUFFERSIZE, 64000);
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }
        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        }
        $res = curl_exec($ch);
        $curlInfo = curl_getinfo($ch);
        if (curl_errno($ch) || $curlInfo['http_code'] != 200) {
            curl_error($ch);
            @unlink($savePath.$savePath);
            return false;
        } else {
            curl_close($ch);
        }
        fclose($fp);
        return $savePath.$savePath;
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
}
