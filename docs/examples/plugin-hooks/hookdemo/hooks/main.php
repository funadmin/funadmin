<?php

declare(strict_types=1);

// This private file is loaded after the activation gate; return a callable map.
return [
    'afterUpload' => static function (array $attachment): void {
        // Reused content should not be transformed again.
        if ($attachment['created']) {
            error_log('hookdemo: attachment ' . $attachment['id'] . ' saved');
        }
    },
    'extendUpload' => static function (array $result): array {
        $result['extensions']['hookdemo'] = ['label' => 'processed'];
        return $result;
    },
    'decorate' => static fn (mixed $value): string => (string) $value . ' · hookdemo',
];
