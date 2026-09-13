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
