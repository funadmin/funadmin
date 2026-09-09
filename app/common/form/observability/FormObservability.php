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
    private readonly Closure $counter;
    private readonly Closure $duration;

    public function __construct(?callable $writer = null, ?callable $counter = null, ?callable $duration = null)
    {
        $this->writer = $writer !== null
            ? Closure::fromCallable($writer)
            : static function (array $event): void {
                Log::info('form.event', $event);
            };
        $this->counter = Closure::fromCallable($counter ?? static function (string $metric, array $labels): void {
        });
        $this->duration = Closure::fromCallable($duration ?? static function (string $metric, float $value, array $labels): void {
        });
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
        $labels = [];
        foreach (['formKey', 'nodeId', 'action', 'dataSource', 'stage'] as $field) {
            $labels[$field] = (string) ($context[$field] ?? '');
        }
        ($this->counter)('form_operations_total', $labels);
        ($this->duration)('form_operation_duration_ms', (float) $context['duration'], $labels);
    }
}
