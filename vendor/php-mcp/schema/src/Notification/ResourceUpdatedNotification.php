<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Notification;

class ResourceUpdatedNotification extends Notification
{
    public function __construct(
        public readonly string $uri,
        public readonly ?array $_meta = null
    ) {
        $params = ['uri' => $uri];
        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/resources/updated', $params);
    }

    public static function make(string $uri, ?array $_meta = null): static
    {
        return new static($uri, $_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/resources/updated') {
            throw new \InvalidArgumentException('Notification is not a notifications/resources/updated notification');
        }

        $params = $notification->params;

        if (! isset($params['uri']) || ! is_string($params['uri'])) {
            throw new \InvalidArgumentException('Missing or invalid uri parameter for notifications/resources/updated notification');
        }

        return new static($params['uri'], $params['_meta'] ?? null);
    }
}
