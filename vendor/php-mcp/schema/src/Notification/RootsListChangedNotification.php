<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

/**
 * A notification from the client to the server, informing it that the list of roots has changed.
 * This notification should be sent whenever the client adds, removes, or modifies any root.
 * The server should then request an updated list of roots using the ListRootsRequest.
 */
class RootsListChangedNotification extends Notification
{
    public function __construct(
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/roots/list_changed', $params);
    }

    public static function make(?array $_meta = null): static
    {
        return new static($_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/roots/list_changed') {
            throw new \InvalidArgumentException('Notification is not a notifications/roots/list_changed notification');
        }

        return new static($notification->params['_meta'] ?? null);
    }
}
