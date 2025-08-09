<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

/**
 * An optional notification from the server to the client, informing it that the list of resources it can read from has changed. This may be issued by servers without any previous subscription from the client.
 */
class ResourceListChangedNotification extends Notification
{
    public function __construct(
        public readonly ?array $_meta = null
    ) {
        $params = [];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/resources/list_changed', $params);
    }

    public static function make(?array $_meta = null): static
    {
        return new static($_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/resources/list_changed') {
            throw new \InvalidArgumentException('Notification is not a notifications/resources/list_changed notification');
        }

        return new static($notification->params['_meta'] ?? null);
    }
}
