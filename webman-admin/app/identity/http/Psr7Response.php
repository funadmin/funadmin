<?php

declare(strict_types=1);

namespace app\identity\http;

use think\Cookie;
use think\response\Html;
use Throwable;

/**
 * 保留 PSR-7 多值响应头语义的 ThinkPHP 响应桥。
 */
final class Psr7Response extends Html
{
    public function __construct(Cookie $cookie, $data = '', int $code = 200)
    {
        parent::__construct($cookie, $data, $code);
        $this->header = [];
    }

    /**
     * 按字段追加响应头值，不对值执行逗号合并。
     */
    public function appendHeader(string $name, string $value): self
    {
        $this->header[$name] ??= [];
        $this->header[$name][] = $value;

        return $this;
    }

    /**
     * 获取响应头；单值字段保持 ThinkPHP 标量兼容，多值字段返回完整列表。
     */
    public function getHeader(string $name = '')
    {
        if ($name === '') {
            return $this->header;
        }

        $values = $this->header[$name] ?? null;
        if (!is_array($values) || count($values) !== 1) {
            return $values;
        }

        return $values[0];
    }

    /**
     * 逐条发送原始响应头，确保 Set-Cookie 等字段保持独立。
     */
    public function send(): void
    {
        try {
            $data = $this->getContent();
            if (!headers_sent()) {
                http_response_code($this->code);
                foreach ($this->header as $name => $values) {
                    foreach ($values as $index => $value) {
                        header($name . ':' . $value, $index === 0);
                    }
                }

                if ($this->cookie) {
                    $this->cookie->save();
                }
            }

            $this->sendData($data);
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        } catch (Throwable $exception) {
            \think\Container::getInstance()->log->error($exception->getMessage());
        }
    }
}
