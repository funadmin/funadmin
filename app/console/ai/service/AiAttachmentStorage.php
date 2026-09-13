<?php

declare(strict_types=1);
namespace app\console\ai\service;

use InvalidArgumentException;
use RuntimeException;

/** 附件仅为私有数据；随机无扩展路径，不进入沙箱、公开磁盘或源码执行链。 */
final class AiAttachmentStorage
{
    public const IMAGE_BYTES = 5242880;
    public const TEXT_BYTES = 131072;
    private const TEXT_EXTENSIONS = ['txt','md','csv','json','yaml','yml','xml','html','css','scss','js','jsx','ts','tsx','vue','php','py','rb','go','rs','java','c','h','cpp','hpp','cs','sql','sh','bash','zsh','toml','ini','conf','log','diff','patch','kt','swift'];

    public function __construct(private readonly string $root) {}

    public function put(string $name, string $bytes): array
    {
        $this->validateName($name);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $imageMime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp'][$extension] ?? null;
        $size = strlen($bytes);
        if ($size < 1 || $size > ($imageMime === null ? self::TEXT_BYTES : self::IMAGE_BYTES)) throw new InvalidArgumentException('附件为空或超过大小上限', 413);
        $width = $height = null;
        if ($imageMime !== null) {
            if (!function_exists('imagecreatefromstring')) throw new RuntimeException('图片解码服务不可用', 503);
            $info = @getimagesizefromstring($bytes);
            if (!$info || $info['mime'] !== $imageMime || $info[0] < 1 || $info[1] < 1 || $info[0] > 8192 || $info[1] > 8192 || $info[0] * $info[1] > 16000000) throw new InvalidArgumentException('图片格式或像素上限无效', 400);
            // PNG/WebP 动画拒绝，不把多帧输入悄悄降为首帧。
            if (($extension === 'webp' && (str_contains($bytes, 'ANIM') || str_contains($bytes, 'ANMF') || (substr($bytes, 12, 4) === 'VP8X' && (ord($bytes[20] ?? "\0") & 2))))
                || ($extension === 'png' && str_contains($bytes, 'acTL'))) throw new InvalidArgumentException('只支持静态图片', 400);
            $image = @imagecreatefromstring($bytes);
            if ($image === false) throw new InvalidArgumentException('图片无法解码', 400);
            try {
                $width = imagesx($image); $height = imagesy($image);
                imagesavealpha($image, true);
                ob_start();
                try {
                    $ok = match ($imageMime) { 'image/png'=>imagepng($image), 'image/jpeg'=>imagejpeg($image, null, 90), 'image/webp'=>imagewebp($image, null, 90) };
                    $bytes = (string) ob_get_contents();
                } finally { ob_end_clean(); }
                if (!$ok || strlen($bytes) > self::IMAGE_BYTES || $bytes === '') throw new InvalidArgumentException('重编码图片超过大小上限', 413);
            } finally { imagedestroy($image); }
        } else {
            if (!in_array($extension, self::TEXT_EXTENSIONS, true)) throw new InvalidArgumentException('不支持的附件类型', 400);
            if (!mb_check_encoding($bytes, 'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $bytes)
                || preg_match('/^(%PDF-|PK\x03\x04|MZ|\x7fELF)/', $bytes)) throw new InvalidArgumentException('附件必须为 UTF-8 文本或代码', 400);
        }
        $this->secureRoot();
        $relative = bin2hex(random_bytes(32));
        $path = $this->root . '/' . $relative;
        $mask = umask(0077);
        try { $handle = @fopen($path, 'x+b'); } finally { umask($mask); }
        if ($handle === false) throw new RuntimeException('附件存储失败', 503);
        try {
            if (!chmod($path, 0600) || fwrite($handle, $bytes) !== strlen($bytes) || !fflush($handle)) throw new RuntimeException('附件存储失败', 503);
        } finally { fclose($handle); }
        return ['kind'=>$imageMime === null ? 'text' : 'image', 'name'=>$name, 'mime'=>$imageMime ?? 'text/plain', 'size'=>strlen($bytes), 'width'=>$width, 'height'=>$height, 'sha256'=>hash('sha256', $bytes), 'storage_path'=>$relative];
    }

    /** 读取同一文件描述符并校验 hash；不返回未经校验的流。 */
    public function read(array $record): string
    {
        $relative = $record['storage_path'] ?? '';
        if (!is_string($relative) || preg_match('/^[a-f0-9]{64}$/D', $relative) !== 1) throw new RuntimeException('附件路径无效', 409);
        $this->secureRoot();
        $path = $this->root . '/' . $relative;
        clearstatcache(true, $path);
        $before = @lstat($path);
        if (!$before || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0777) !== 0600 || $before['nlink'] !== 1) throw new RuntimeException('附件文件不可用', 404);
        $handle = @fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('附件文件不可用', 404);
        try {
            $stat = fstat($handle);
            if (!$stat || $stat['ino'] !== $before['ino'] || $stat['dev'] !== $before['dev'] || $stat['size'] > self::IMAGE_BYTES) throw new RuntimeException('附件文件已变更', 409);
            $bytes = stream_get_contents($handle, self::IMAGE_BYTES + 1);
        } finally { fclose($handle); }
        if (!is_string($bytes) || strlen($bytes) !== (int) ($record['size'] ?? -1) || !hash_equals((string) ($record['sha256'] ?? ''), hash('sha256', $bytes))) throw new RuntimeException('附件 sha256 校验失败', 409);
        return $bytes;
    }

    /** 上传登记与清理共用目录锁，避免未提交上传被当作孤儿。 */
    public function synchronized(callable $operation): mixed
    {
        $this->secureRoot();
        $path = $this->root . '/.attachment-lock';
        $handle = $this->controlFile($path);
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('附件锁失败', 503);
            return $operation();
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    /**
     * 非递归、文件名持久游标；绑定或不安全的候选也推进进度，避免前缀饥饿。
     * 每轮处理 B=max(1,min(1000,limit)) 项，候选内存 O(B)，目录选取 O(N log B)。
     * 选取必须完整读取 N 个目录项，不使用目录 offset/seek，也不承诺扫描时间上限。
     * 全目录遍历和回调均持有原目录锁；大目录或慢回调会延长上传等待时间。
     */
    public function cleanupExpired(int $now, callable $removeIfUnbound, int $limit = 100): int
    {
        return $this->synchronized(function () use ($now, $removeIfUnbound, $limit): int {
            $removed = 0;
            $cursor = $this->controlFile($this->root . '/.attachment-cursor');
            $directory = null;
            try {
                $rootStat = stat($this->root);
                $identity = $rootStat['dev'] . ':' . $rootStat['ino'];
                $state = json_decode((string) stream_get_contents($cursor, 1024), true);
                $after = is_array($state) && ($state['identity'] ?? null) === $identity
                    && is_string($state['after'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $state['after']) === 1
                    ? $state['after'] : '';
                $budget = max(1, min(1000, $limit));
                $directory = @opendir($this->root);
                if ($directory === false) throw new RuntimeException('附件目录不可用', 503);
                $candidates = new \SplMaxHeap();
                while (($name = readdir($directory)) !== false) {
                    if (preg_match('/^[a-f0-9]{64}$/D', $name) !== 1 || strcmp($name, $after) <= 0) continue;
                    // 非数字前缀强制堆按字符串比较，避免纯数字文件名的浮点精度问题。
                    $candidate = 'n' . $name;
                    if ($candidates->count() < $budget) $candidates->insert($candidate);
                    elseif (strcmp($candidate, $candidates->top()) < 0) {
                        $candidates->extract();
                        $candidates->insert($candidate);
                    }
                }
                $names = iterator_to_array($candidates, false);
                sort($names, SORT_STRING);
                // 空批次回绕；已删除的游标文件不影响比较，新建的较小名称下一轮巡回处理。
                if (!$names) $after = '';
                foreach ($names as $candidate) {
                    $name = substr($candidate, 1);
                    $after = $name;
                    $path = $this->root . '/' . $name;
                    clearstatcache(true, $path);
                    $before = @lstat($path);
                    if (!$before || ($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0777) !== 0600 || $before['nlink'] !== 1 || $before['mtime'] > $now - 86400) continue;
                    $removed += (int) $removeIfUnbound($name, function () use ($path, $before): bool {
                        clearstatcache(true, $path);
                        $after = @lstat($path);
                        return $after === $before && unlink($path);
                    });
                }
                $bytes = json_encode(['identity'=>$identity, 'after'=>$after], JSON_THROW_ON_ERROR);
                rewind($cursor);
                if (fwrite($cursor, $bytes) !== strlen($bytes) || !ftruncate($cursor, strlen($bytes)) || !fflush($cursor)) throw new RuntimeException('附件游标保存失败', 503);
                return $removed;
            } finally {
                if (is_resource($directory)) closedir($directory);
                fclose($cursor);
            }
        });
    }

    private function controlFile(string $path)
    {
        clearstatcache(true, $path);
        $before = @lstat($path);
        if ($before && (($before['mode'] & 0170000) !== 0100000 || ($before['mode'] & 0777) !== 0600 || $before['nlink'] !== 1)) throw new RuntimeException('附件控制文件不安全', 503);
        $mask = umask(0077);
        try { $handle = @fopen($path, $before ? 'r+b' : 'x+b'); } finally { umask($mask); }
        if (!$handle) throw new RuntimeException('附件控制文件不可用', 503);
        clearstatcache(true, $path);
        $after = @lstat($path); $opened = fstat($handle);
        if (!$after || !$opened || ($after['mode'] & 0170000) !== 0100000 || $after['nlink'] !== 1 || ($after['mode'] & 0777) !== 0600
            || $after['ino'] !== $opened['ino'] || $after['dev'] !== $opened['dev']
            || ($before && ($before['ino'] !== $opened['ino'] || $before['dev'] !== $opened['dev']))) {
            fclose($handle); throw new RuntimeException('附件控制文件已变更', 503);
        }
        return $handle;
    }

    public function headers(array $record): array
    {
        $this->validateName($record['name']);
        return ['Content-Type'=> $record['kind'] === 'text' ? 'text/plain; charset=UTF-8' : $record['mime'],
            'Content-Disposition'=>"attachment; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($record['name']),
            'X-Content-Type-Options'=>'nosniff', 'Cache-Control'=>'no-store', 'Content-Security-Policy'=>"default-src 'none'; sandbox"];
    }

    private function validateName(string $name): void
    {
        if ($name === '' || strlen($name) > 255 || !mb_check_encoding($name, 'UTF-8') || preg_match('/[\x00-\x1f\x7f\/\\\\]/u', $name)) throw new InvalidArgumentException('附件名称无效', 400);
    }

    private function secureRoot(): void
    {
        if (!str_starts_with($this->root, '/') || str_contains($this->root, '/public/') || str_contains($this->root, '..')) throw new RuntimeException('附件必须使用私有绝对目录', 503);
        $path = '';
        foreach (explode('/', trim($this->root, '/')) as $segment) {
            $path .= '/' . $segment;
            if (is_link($path)) throw new RuntimeException('私有目录禁止符号链接', 503);
        }
        $mask = umask(0077);
        try { if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) throw new RuntimeException('无法创建附件目录', 503); }
        finally { umask($mask); }
        if (!chmod($this->root, 0700)) throw new RuntimeException('附件目录权限无效', 503);
    }
}
