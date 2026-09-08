<?php

declare(strict_types=1);

namespace app\common\form\observability;

use Closure;
use Throwable;
use think\facade\Log;

final class FormObservability
{
    private const FIELDS = [
        'formKey', 'schemaHash', 'nodeId', 'action', 'dataSource', 'stage', 'duration', 'requestId',
    ];

    private readonly Closure $writer;

    public function __construct(?callable $writer = null)
    {
        $this->writer = $writer !== null
            ? Closure::fromCallable($writer)
            : static function (array $event): void {
                Log::info('form.event', $event);
            };
    }

    public function record(array $context): void
    {
        $event = array_fill_keys(self::FIELDS, '');
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $context)) {
                $event[$field] = $context[$field];
            }
        }
        $event['duration'] = max(0.0, (float) $event['duration']);
        ($this->writer)($event);
    }

    public function measure(array $context, callable $operation): mixed
    {
        $startedAt = hrtime(true);
        try {
            $result = $operation();
            $this->complete($context, 'success', $startedAt);
            return $result;
        } catch (Throwable $exception) {
            $this->complete($context, 'failure', $startedAt);
            throw $exception;
        }
    }

    private function complete(array $context, string $stage, int $startedAt): void
    {
        $context['stage'] = $stage;
        $context['duration'] = (hrtime(true) - $startedAt) / 1_000_000;
        $this->record($context);
    }
}
