<?php

declare(strict_types=1);

namespace app\common\crud;

/**
 * 仅基于路径和内存内容判定文本或二进制，避免误把二进制内容暴露到计划中。
 */
final class ContentClassifier
{
    private const BINARY_EXTENSIONS = [
        '7z', 'avi', 'bin', 'bmp', 'class', 'dat', 'doc', 'docx', 'eot', 'exe', 'gif', 'gz',
        'ico', 'jar', 'jpeg', 'jpg', 'mov', 'mp3', 'mp4', 'ogg', 'otf', 'pdf', 'png', 'rar',
        'so', 'tar', 'ttf', 'wav', 'webm', 'webp', 'woff', 'woff2', 'xls', 'xlsx', 'zip',
    ];

    /** @return array{kind: string, type: string} */
    public function classify(string $path, string $content, ?string $declaredKind = null): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $mime = $this->mime($content);
        $binary = $declaredKind === 'binary'
            || str_contains($content, "\0")
            || !$this->isUtf8($content)
            || in_array($extension, self::BINARY_EXTENSIONS, true)
            || ($content !== '' && $mime !== '' && !str_starts_with($mime, 'text/') && !in_array($mime, [
                'application/json', 'application/javascript', 'application/xml', 'application/x-httpd-php',
            ], true));

        return ['kind' => $binary ? 'binary' : 'text', 'type' => $mime !== '' ? $mime : 'application/octet-stream'];
    }

    private function isUtf8(string $content): bool
    {
        return preg_match('//u', $content) === 1;
    }

    private function mime(string $content): string
    {
        if (!class_exists(\finfo::class)) {
            return '';
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        return is_string($mime) ? strtolower($mime) : '';
    }
}
