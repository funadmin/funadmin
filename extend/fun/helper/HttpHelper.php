<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/26
 */

namespace fun\helper;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Cookie\CookieJarInterface;
class HttpHelper
{

    /**
     * 发送一个POST请求
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @param array $header 请求头
     * @param array $options 扩展参数
     * @param array $cookies Cookie参数
     * @return array 返回请求结果
     */
    public static function post($url, $params = [], $header = [], $options = [], $cookies = [])
    {
        return self::sendRequest($url, $params, 'POST', $header, $options, $cookies);
    }

    /**
     * 发送一个GET请求
     * @param string $url 请求URL
     * @param array $params 请求参数
     * @param array $header 请求头
     * @param array $options 扩展参数
     * @param array $cookies Cookie参数
     * @return array 返回请求结果
     */
    public static function get($url, $params = [], $header = [], $options = [], $cookies = [])
    {
        return self::sendRequest($url, $params, 'GET', $header, $options, $cookies);
    }

    /**
     * CURL发送Request请求,含POST和REQUEST
     * @param string $url 请求的链接
     * @param mixed $params 传递的参数
     * @param string $method 请求的方法
     * @param array $header 请求头
     * @param mixed $options CURL的参数
     * @param array $cookies Cookies数组，格式可以是：
     *        ['name1' => 'value1', 'name2' => 'value2'] 
     *        或包含完整配置的数组:
     *        ['name1' => ['Name' => 'name1', 'Value' => 'value1', 'Domain' => 'example.com']]
     * @return array
     * 
     * @example
     * // 基本使用
     * HttpHelper::sendRequest('https://example.com', ['param' => 'value']);
     * 
     * // 带Cookies的GET请求
     * HttpHelper::sendRequest('https://example.com', [], 'GET', [], [], ['token' => '12345']);
     * 
     * // 带详细Cookie设置
     * $cookies = [
     *     'token' => [
     *         'Name' => 'token',
     *         'Value' => '12345',
     *         'Domain' => 'example.com',
     *         'Secure' => true,
     *         'HttpOnly' => true,
     *         'Expires' => time() + 3600
     *     ]
     * ];
     * HttpHelper::sendRequest('https://example.com', [], 'GET', [], [], $cookies);
     */
    public static function sendRequest($url, $params = [], $method = 'POST', $header = [], $options = [], $cookies = [])
    {
        if (empty($url)) {
            return ['ret' => false, 'msg' => 'URL cannot be empty', 'data' => []];
        }

        $method = strtoupper($method);
        $requestOptions = [];
        
        // Handle different request types
        if ($method === 'GET') {
            $requestOptions['query'] = $params;
        } elseif (isset($header['Content-Type']) && strpos($header['Content-Type'], 'application/json') !== false) {
            $requestOptions['json'] = $params;
        } else {
            $requestOptions['form_params'] = $params;
        }

        try {
            $client = self::getClient($options, $header, $cookies);
            $response = $client->request($method, $url, $requestOptions);
            $statusCode = $response->getStatusCode();
            $contents = $response->getBody()->getContents();
            // 默认情况下只返回响应结果，不直接判断为失败
            // 上层应用可以根据status_code自行判断
            // Check for successful status codes (2xx)
            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'ret' => true, 
                    'msg' => $contents,
                    'data' => $contents,
                    'status_code' => $statusCode
                ];
            } else {
                return [
                    'ret' => false, 
                    'msg' => "HTTP Error: $statusCode", 
                    'data' => $contents,
                    'status_code' => $statusCode
                ];
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return [
                'ret' => false, 
                'msg' => 'Connection error: ' . $e->getMessage(),
                'data' => [],
                'exception' => get_class($e)
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->hasResponse() ? $e->getResponse() : null;
            $statusCode = $response ? $response->getStatusCode() : 0;
            $contents = $response ? $response->getBody()->getContents() : '';

            return [
                'ret' => false, 
                'msg' => $e->getMessage(),
                'data' => $contents,
                'status_code' => $statusCode,
                'exception' => get_class($e)
            ];
        } catch (\Throwable $e) {
            return [
                'ret' => false, 
                'msg' => $e->getMessage(),
                'data' => [],
                'exception' => get_class($e)
            ];
        }
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
        if (empty($url)) {
            return ['ret' => false, 'msg' => 'URL cannot be empty', 'data' => []];
        }

        $method = strtoupper($method);
        $requestOptions = [];
        
        // Handle different request types
        if ($method === 'GET') {
            $requestOptions['query'] = $params;
        } elseif (isset($header['Content-Type']) && strpos($header['Content-Type'], 'application/json') !== false) {
            $requestOptions['json'] = $params;
        } else {
            $requestOptions['form_params'] = $params;
        }

        try {
            $client = self::getClient($options, $header, $cookies);
            $promise = $client->requestAsync($method, $url, $requestOptions);
            
            $promise->then(
                function ($response) {
                    $statusCode = $response->getStatusCode();
                    $contents = $response->getBody()->getContents();
                    
                    return [
                        'ret' => true,
                        'msg' => $contents,
                        'data' => $contents,
                        'status_code' => $statusCode
                    ];
                },
                function ($exception) {
                    if ($exception instanceof \GuzzleHttp\Exception\RequestException && $exception->hasResponse()) {
                        $response = $exception->getResponse();
                        $statusCode = $response->getStatusCode();
                        $contents = $response->getBody()->getContents();
                        
                        return [
                            'ret' => false, 
                            'msg' => $exception->getMessage(),
                            'data' => $contents,
                            'status_code' => $statusCode,
                            'exception' => get_class($exception)
                        ];
                    }
                    
                    return [
                        'ret' => false, 
                        'msg' => $exception->getMessage(),
                        'data' => [],
                        'exception' => get_class($exception)
                    ];
                }
            );
            
            return $promise;
        } catch (\Throwable $e) {
            return [
                'ret' => false, 
                'msg' => $e->getMessage(),
                'data' => [],
                'exception' => get_class($e)
            ];
        }
    }

    /**
     * 发送文件到客户端浏览器
     * @param string $file 文件路径
     * @param bool $delaftersend 发送后是否删除文件
     * @param bool $exitaftersend 发送后是否退出脚本
     * @return array 发送结果，包含成功状态和消息
     */
    public static function sendToBrowser($file, $delaftersend = true, $exitaftersend = true)
    {
        if (!file_exists($file) || !is_readable($file)) {
            return ['ret' => false, 'msg' => '文件不存在或不可读: ' . $file];
        }
        
        try {
            $filename = basename($file);
            $filesize = filesize($file);
            $mimeType = mime_content_type($file) ?: 'application/octet-stream';
            
            // 清除之前的输出缓冲
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // 设置正确的头信息
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . $filesize);
            
            // 发送文件
            readfile($file);
            
            // 发送后删除文件
            if ($delaftersend && is_writable($file)) {
                @unlink($file);
            }
            
            // 发送后退出脚本
            if ($exitaftersend) {
                exit;
            }
            
            return ['ret' => true, 'msg' => '文件发送成功'];
        } catch (\Throwable $e) {
            return ['ret' => false, 'msg' => '发送文件错误: ' . $e->getMessage()];
        }
    }

    /**
     * 发送一个POST请求，参数为JSON格式
     * @param string $url 请求URL
     * @param array $data 请求数据，将被转为JSON
     * @param array $header 请求头
     * @param array $options 扩展参数
     * @param array $cookies Cookie参数
     * @return array 返回请求结果
     */
    public static function postJson($url, $data = [], $header = [], $options = [], $cookies = [])
    {
        $header = array_merge(['Content-Type' => 'application/json'], $header);
        return self::sendRequest($url, $data, 'POST', $header, $options, $cookies);
    }

    /**
     * 发送一个PUT请求，参数为JSON格式
     * @param string $url 请求URL
     * @param array $data 请求数据，将被转为JSON
     * @param array $header 请求头
     * @param array $options 扩展参数
     * @param array $cookies Cookie参数
     * @return array 返回请求结果
     */
    public static function putJson($url, $data = [], $header = [], $options = [], $cookies = [])
    {
        $header = array_merge(['Content-Type' => 'application/json'], $header);
        return self::sendRequest($url, $data, 'PUT', $header, $options, $cookies);
    }

    /**
     * 下载并保存文件
     * @param string $url 要下载的文件URL
     * @param string $filename 保存的文件名，为空时自动生成
     * @param int $timeout 超时时间(秒)
     * @param array $header 请求头
     * @param array $options 请求选项
     * @return array 下载结果，包含成功状态、消息和文件路径
     */
    public static function download($url, $filename = "", $timeout = 60, $header = [], $options = [])
    {
        if (empty($url)) {
            return ['ret' => false, 'msg' => '下载URL不能为空', 'data' => null];
        }
        
        // 生成默认文件名
        if (empty($filename)) {
            $tempDir = public_path() . 'temp' . DIRECTORY_SEPARATOR;
            if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
                return ['ret' => false, 'msg' => '无法创建临时目录', 'data' => null];
            }
            $filename = $tempDir . pathinfo($url, PATHINFO_BASENAME);
        }
        
        // 创建目录
        $path = dirname($filename);
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            return ['ret' => false, 'msg' => '无法创建目录: ' . $path, 'data' => null];
        }
        
        // 设置下载选项
        $downloadOptions = array_merge([
            'timeout' => $timeout,
            'verify' => false,
            'headers' => $header,
            'sink' => $filename
        ], $options);
        
        try {
            $client = new Client();
            $response = $client->get($url, $downloadOptions);
            
            $statusCode = $response->getStatusCode();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'ret' => true, 
                    'msg' => '文件下载成功', 
                    'data' => [
                        'filename' => $filename,
                        'size' => filesize($filename),
                        'mime' => $response->getHeaderLine('Content-Type')
                    ]
                ];
            } else {
                return [
                    'ret' => false, 
                    'msg' => "HTTP错误: $statusCode", 
                    'data' => null
                ];
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return [
                'ret' => false, 
                'msg' => '连接错误: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return [
                'ret' => false, 
                'msg' => '请求错误: ' . $e->getMessage(),
                'data' => null
            ];
        } catch (\Throwable $e) {
            return [
                'ret' => false, 
                'msg' => '下载错误: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * 接收流文件
     * @param string $receiveFile 接收后保存的文件名
     * @return array 接收结果，包含成功状态、消息和文件信息
     */
    public static function receiveStreamFile($receiveFile)
    {
        if (empty($receiveFile)) {
            return ['ret' => false, 'msg' => '目标文件路径不能为空', 'data' => null];
        }
        
        // 确保目录存在
        $path = dirname($receiveFile);
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            return ['ret' => false, 'msg' => '无法创建目录: ' . $path, 'data' => null];
        }
        
        try {
            // 读取输入流
            $streamData = file_get_contents('php://input');
            
            if (empty($streamData)) {
                return ['ret' => false, 'msg' => '接收到的数据为空', 'data' => null];
            }
            
            // 写入文件
            $bytes = file_put_contents($receiveFile, $streamData, LOCK_EX);
            
            if ($bytes === false) {
                return ['ret' => false, 'msg' => '写入文件失败', 'data' => null];
            }
            
            return [
                'ret' => true, 
                'msg' => '文件接收成功', 
                'data' => [
                    'filename' => $receiveFile,
                    'size' => $bytes
                ]
            ];
        } catch (\Throwable $e) {
            return ['ret' => false, 'msg' => '接收流文件错误: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * 发送流文件
     * @param string $url 接收的URL路径
     * @param string $file 要发送的文件路径
     * @param array $headers 请求头
     * @return array 发送结果，包含成功状态、消息和响应数据
     */
    public static function sendStreamFile($url, $file, $headers = [])
    {
        if (empty($url)) {
            return ['ret' => false, 'msg' => '目标URL不能为空', 'data' => null];
        }
        
        if (empty($file) || !file_exists($file)) {
            return ['ret' => false, 'msg' => '文件不存在: ' . $file, 'data' => null];
        }
        
        try {
            // 准备文件内容
            $fileContent = file_get_contents($file);
            
            // 设置默认请求头
            $defaultHeaders = [
                'Content-Type' => 'application/octet-stream',
                'Content-Length' => filesize($file),
                'Content-Disposition' => 'attachment; filename="' . basename($file) . '"'
            ];
            
            // 合并请求头
            $headers = array_merge($defaultHeaders, $headers);
            
            // 使用GuzzleHttp发送请求
            $client = new Client();
            $response = $client->post($url, [
                'headers' => $headers,
                'body' => $fileContent,
                'verify' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            $contents = $response->getBody()->getContents();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'ret' => true, 
                    'msg' => '文件发送成功',
                    'data' => [
                        'response' => $contents,
                        'status_code' => $statusCode
                    ]
                ];
            } else {
                return [
                    'ret' => false, 
                    'msg' => "HTTP错误: $statusCode", 
                    'data' => $contents
                ];
            }
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            return ['ret' => false, 'msg' => '连接错误: ' . $e->getMessage(), 'data' => null];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return ['ret' => false, 'msg' => '请求错误: ' . $e->getMessage(), 'data' => null];
        } catch (\Throwable $e) {
            return ['ret' => false, 'msg' => '发送流文件错误: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * 获取访问客户端
     * @param array $options
     * @param array $header
     * @param array $cookies
     * @return \GuzzleHttp\Client
     */
    private static function getClient(array $options = [], array $header = [], array $cookies = [])
    {
        $defaultOptions = [
            'timeout'         => 30,            // Default timeout reduced to 30 seconds
            'connect_timeout' => 10,            // Faster connection timeout for better UX
            'verify'          => false,         // Disable SSL verification
            'http_errors'     => false,         // Don't throw exceptions for 4xx/5xx responses
            'allow_redirects' => true,          // Follow redirects by default
            'headers'         => [
                'User-Agent'       => 'FunAdmin/PHP-HTTP-Client',
                'X-REQUESTED-WITH' => 'XMLHttpRequest',
                'Accept'           => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Encoding'  => 'gzip, deflate',
                'Accept-Language'  => 'zh-CN,en-US;q=0.8',
                'Referer'          => dirname(request()->url()),
            ]
        ];

        // Merge user options with defaults, letting user options override defaults
        $options = array_merge($defaultOptions, $options);

        // Merge additional headers
        if (!empty($header)) {
            $options['headers'] = array_merge($options['headers'], $header);
        }

        // 正确处理Cookies - 使用GuzzleHttp的CookieJar
        if (!empty($cookies)) {
            // 检查cookies是否已经是CookieJar对象
            if (!($cookies instanceof CookieJarInterface)) {
                // 使用辅助方法转换为CookieJar对象
                $cookies = self::createCookieJar($cookies);
            }
            
            $options['cookies'] = $cookies;
        }

        return new Client($options);
    }

    /**
     * 创建Guzzle Cookie容器
     * @param array $cookies Cookie数组
     * @param string $domain 可选，Cookie所属域名，默认从当前请求中获取
     * @return \GuzzleHttp\Cookie\CookieJarInterface
     */
    public static function createCookieJar(array $cookies, $domain = null)
    {
        $cookieJar = new CookieJar();
        
        if (empty($domain)) {
            $domain = parse_url(request()->url(), PHP_URL_HOST);
        }
        
        foreach ($cookies as $name => $value) {
            if (is_array($value)) {
                // 如果cookie是一个数组，包含详细设置
                $cookieConfig = array_merge([
                    'Domain'  => $domain,
                    'Path'    => '/',
                    'Secure'  => false,
                    'HttpOnly' => false
                ], $value);
                
                // 确保Name和Value存在
                if (!isset($cookieConfig['Name']) && !is_numeric($name)) {
                    $cookieConfig['Name'] = $name;
                }
                
                $cookieJar->setCookie(new SetCookie($cookieConfig));
            } else {
                // 简单的键值形式
                $cookieJar->setCookie(new SetCookie([
                    'Name'    => $name,
                    'Value'   => $value,
                    'Domain'  => $domain,
                    'Path'    => '/',
                    'Secure'  => false,
                    'HttpOnly' => false
                ]));
            }
        }
        
        return $cookieJar;
    }
}
