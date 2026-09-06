<?php

declare(strict_types=1);

namespace app\common\crud;

use JsonSerializable;

/**
 * 不含凭据的可审计生成清单。
 */
final class GenerationManifest implements JsonSerializable
{
    private const SECRET_PATTERN = '/(?:password|passwd|secret|token|credential|private[_-]?key|api[_-]?key|dsn)/i';

    private function __construct(private readonly array $data)
    {
    }

    public static function create(
        CrudDefinition $definition,
        string $templateVersion,
        array $plan,
        string $operator,
        string $status,
        array $metadata = [],
        ?string $startedAt = null,
        ?string $finishedAt = null,
        ?array $error = null
    ): self {
        $files = array_map(static fn (array $file): array => [
            'path' => $file['path'],
            'status' => $file['status'],
            'hash' => $file['hash'],
            'previousHash' => $file['previousHash'],
        ], $plan['files'] ?? []);
        $createdFiles = array_column(array_filter($files, static fn (array $file): bool => $file['status'] === 'create'), 'path');
        $overwrittenFiles = array_column(array_filter($files, static fn (array $file): bool => $file['status'] === 'conflict'), 'path');
        $safeMetadata = self::withoutSecrets($metadata);
        return new self([
            'schemaVersion' => '1.0',
            'definitionHash' => $definition->hash(),
            'templateVersion' => $templateVersion,
            'files' => $files,
            'createdFiles' => $createdFiles,
            'overwrittenFiles' => $overwrittenFiles,
            'validationResult' => self::withoutSecrets((array) ($safeMetadata['validationResult'] ?? [])),
            'startedAt' => $startedAt ?? gmdate(DATE_ATOM),
            'finishedAt' => $finishedAt,
            'error' => $error === null ? null : self::withoutSecrets($error),
            'operator' => $operator,
            'status' => $status,
            'metadata' => $safeMetadata,
        ]);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    private static function withoutSecrets(array $values): array
    {
        $safe = [];
        foreach ($values as $key => $value) {
            if (preg_match(self::SECRET_PATTERN, (string) $key)) {
                continue;
            }
            $safe[$key] = is_array($value) ? self::withoutSecrets($value) : $value;
        }
        return $safe;
    }
}
