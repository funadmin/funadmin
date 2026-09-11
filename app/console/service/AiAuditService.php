<?php

declare(strict_types=1);

namespace app\console\service;

use RuntimeException;

/** 统一递归脱敏、摘要截断与私有日志落盘。 */
final class AiAuditService
{
    private const SENSITIVE_KEYS = '/(?:token|secret|password|passwd|api[_-]?key|authorization|cookie|credential)/i';

    public function __construct(private readonly string $logRoot, private readonly int $summaryBytes = 4096)
    {
    }

    public function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = preg_match(self::SENSITIVE_KEYS, (string) $key) === 1 ? '[REDACTED]' : $this->redact($item);
            }
            return $result;
        }
        if (!is_string($value)) return $value;
        return preg_replace([
            '/\b(?:Bearer\s+)?(?:sk-|ghp_|github_pat_)[A-Za-z0-9_.-]{8,}\b/i',
            '/\b(token|secret|password|api[_-]?key)\s*[=:]\s*[^\s]+/i',
        ], ['[REDACTED]', '$1=[REDACTED]'], $value) ?? '[REDACTED]';
    }

    public function capture(string $stream, string $content, int $taskId, int $callId): array
    {
        $redacted = (string) $this->redact($content);
        $summary = substr($redacted, 0, $this->summaryBytes);
        $path = null;
        if (strlen($redacted) > $this->summaryBytes) {
            $directory = rtrim($this->logRoot, DIRECTORY_SEPARATOR) . '/' . $taskId;
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('无法创建 AI 私有日志目录');
            $path = $directory . '/' . $callId . '-' . $stream . '.log';
            if (file_put_contents($path, $redacted, LOCK_EX) === false) throw new RuntimeException('无法写入 AI 私有日志');
            chmod($path, 0600);
        }
        return ['summary' => $summary, 'hash' => hash('sha256', $content), 'path' => $path];
    }
}
