<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

/**
 * This notification is sent from the client to the server after initialization has finished.
 */
class InitializedNotification extends Notification
{
    public function __construct(
        public readonly ?array $_meta = null
    ) {
        $params = [];

        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/initialized', $params);
    }

    public static function make(?array $_meta = null): static
    {
        return new static($_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/initialized') {
            throw new \InvalidArgumentException('Notification is not a notifications/initialized notification');
        }

        return new static($notification->params['_meta'] ?? null);
    }
}
