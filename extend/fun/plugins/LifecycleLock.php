<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/**
 * 基于 flock 的单插件生命周期互斥锁。
 */
final class LifecycleLock
{
    public function __construct(private readonly string $directory)
    {
    }

    public function acquire(string $code): LockHandle
    {
        if (!preg_match('/^[a-z][a-z0-9]*$/', $code)) {
            throw new RuntimeException('插件标识格式错误');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('无法创建插件锁目录');
        }
        $stream = fopen($this->directory . DIRECTORY_SEPARATOR . $code . '.lock', 'c+');
        if ($stream === false) {
            throw new RuntimeException('无法打开插件生命周期锁');
        }
        if (!flock($stream, LOCK_EX | LOCK_NB)) {
            fclose($stream);
            throw new RuntimeException('插件正在执行生命周期操作：' . $code);
        }
        return new LockHandle($stream);
    }
}

final class LockHandle
{
    private bool $released = false;

    public function __construct(private mixed $stream)
    {
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }
        flock($this->stream, LOCK_UN);
        fclose($this->stream);
        $this->released = true;
    }

    public function __destruct()
    {
        $this->release();
    }
}
