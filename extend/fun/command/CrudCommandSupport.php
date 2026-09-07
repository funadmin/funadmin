<?php

declare(strict_types=1);

namespace fun\command;

use app\common\crud\CrudDefinition;
use InvalidArgumentException;
use RuntimeException;

/**
 * CRUD CLI 的版本化 Definition 与 token 安全读取工具。
 */
trait CrudCommandSupport
{
    private function loadDefinition(string $path): CrudDefinition
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            throw new InvalidArgumentException('CRUD Definition 文件不存在');
        }
        $data = json_decode((string) file_get_contents($realPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['schemaVersion'])) {
            throw new InvalidArgumentException('旧 CRUD 配置已弃用，请使用版本化 CRUD Definition');
        }
        return CrudDefinition::fromArray($data);
    }

    private function readToken(string $tokenFile): string
    {
        $tokenFile = trim($tokenFile);
        if ($tokenFile === '') {
            $token = stream_get_contents(STDIN);
        } else {
            clearstatcache(true, $tokenFile);
            $stat = @lstat($tokenFile);
            if ($stat === false || (($stat['mode'] & 0170000) !== 0100000) || ($stat['mode'] & 0777) !== 0600) {
                throw new RuntimeException('确认 token 文件必须是权限 0600 的普通文件');
            }
            $token = file_get_contents($tokenFile);
        }
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            throw new RuntimeException('确认 token 只能从 stdin 或权限 0600 的普通文件读取');
        }
        return $token;
    }

    private function writeToken(string $tokenFile, string $token): void
    {
        $tokenFile = trim($tokenFile);
        if ($tokenFile === '') {
            throw new RuntimeException('必须通过 --token-output 指定确认 token 输出文件');
        }
        if (file_exists($tokenFile) || is_link($tokenFile)) {
            throw new RuntimeException('确认 token 输出文件已存在');
        }
        $directory = dirname($tokenFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建确认 token 输出目录');
        }
        $oldMask = umask(0077);
        try {
            $handle = @fopen($tokenFile, 'x');
        } finally {
            umask($oldMask);
        }
        if ($handle === false) {
            throw new RuntimeException('无法创建确认 token 输出文件');
        }
        try {
            if (!chmod($tokenFile, 0600) || fwrite($handle, $token . "\n") === false || !fflush($handle)) {
                throw new RuntimeException('无法安全写入确认 token 输出文件');
            }
        } catch (\Throwable $exception) {
            @unlink($tokenFile);
            throw $exception;
        } finally {
            fclose($handle);
        }
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
