<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

/**
 * An out-of-band notification used to inform the receiver of a progress update for a long-running request.
 */
class ProgressNotification extends Notification
{
    /**
     * @param  string|int  $progressToken  The progress token which was given in the initial request, used to associate this notification with the request that is proceeding.
     * @param  float  $progress The progress thus far. This should increase every time progress is made, even if the total is unknown.
     * @param  float|null  $total Total number of items to process (or total progress required), if known.
     * @param  string|null  $message  An optional message describing the current progress.
     * @param  array|null  $_meta  Optional metadata to include in the notification.
     */
    public function __construct(
        public readonly string|int $progressToken,
        public readonly float $progress,
        public readonly ?float $total = null,
        public readonly ?string $message = null,
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/progress', $params);
    }

    /**
     * @param string|int $progressToken  The progress token which was given in the initial request, used to associate this notification with the request that is proceeding.
     * @param float $progress The progress thus far. This should increase every time progress is made, even if the total is unknown.
     * @param float|null $total Total number of items to process (or total progress required), if known.
     * @param string|null $message An optional message describing the current progress.
     * @param array|null $_meta Optional metadata to include in the notification.
     */
    public static function make(string|int $progressToken, float $progress, ?float $total = null, ?string $message = null, ?array $_meta = null): static
    {
        return new static($progressToken, $progress, $total, $message, $_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/progress') {
            throw new \InvalidArgumentException('Notification is not a notifications/progress notification');
        }

        $params = $notification->params;

        if (! isset($params['progressToken']) || ! is_string($params['progressToken'])) {
            throw new \InvalidArgumentException('Missing or invalid progressToken parameter for notifications/progress notification');
        }

        if (! isset($params['progress']) || ! is_float($params['progress'])) {
            throw new \InvalidArgumentException('Missing or invalid progress parameter for notifications/progress notification');
        }

        return new static($params['progressToken'], $params['progress'], $params['total'] ?? null, $params['message'] ?? null, $params['_meta'] ?? null);
    }
}
