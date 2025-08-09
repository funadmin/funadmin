<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

/**
 * This notification can be sent by either side to indicate that it is cancelling a previously-issued request.
 *
 * The request SHOULD still be in-flight, but due to communication latency, it is always possible that this notification MAY arrive after the request has already finished.
 *
 * This notification indicates that the result will be unused, so any associated processing SHOULD cease.
 *
 * A client MUST NOT attempt to cancel its `initialize` request.
 */
class CancelledNotification extends Notification
{
    /**
     * @param string $requestId The ID of the request that is being cancelled. This MUST correspond to the ID of a request previously issued in the same direction.
     * @param string|null $reason An optional string describing the reason for the cancellation. This MAY be logged or presented to the user.
     * @param array|null $_meta Additional metadata about the notification.
     */
    public function __construct(
        public readonly string $requestId,
        public readonly ?string $reason = null,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'requestId' => $this->requestId,
        ];
        if ($this->reason !== null) {
            $params['reason'] = $this->reason;
        }
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/cancelled', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param string $requestId The ID of the request that is being cancelled. This MUST correspond to the ID of a request previously issued in the same direction.
     * @param string|null $reason An optional string describing the reason for the cancellation. This MAY be logged or presented to the user.
     * @param array|null $_meta Additional metadata about the notification.
     */
    public static function make(string $requestId, ?string $reason = null, ?array $_meta = null): static
    {
        return new static($requestId, $reason, $_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/cancelled') {
            throw new \InvalidArgumentException('Notification is not a notifications/cancelled notification');
        }

        $params = $notification->params;

        if (! isset($params['requestId']) || ! is_string($params['requestId'])) {
            throw new \InvalidArgumentException('Missing or invalid requestId parameter for notifications/cancelled notification');
        }

        return new static($params['requestId'], $params['reason'] ?? null, $params['_meta'] ?? null);
    }
}
