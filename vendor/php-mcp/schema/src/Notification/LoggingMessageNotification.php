<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Notification;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\Enum\LoggingLevel;
use PhpMcp\Schema\JsonRpc\Notification;

class LoggingMessageNotification extends Notification
{
    /**
     * @param  LoggingLevel  $level  The severity of this log message.
     * @param  mixed  $data  The data to be logged, such as a string message or an object. Any JSON serializable type is allowed here.
     * @param  string  $logger  An optional name of the logger issuing this message.
     * @param  array|null  $_meta  Optional metadata to include in the notification.
     */
    public function __construct(
        public readonly LoggingLevel $level,
        public readonly mixed $data,
        public readonly ?string $logger = null,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'level' => $level->value,
            'data' => is_string($data) ? $data : json_encode($data),
        ];

        if ($logger !== null) {
            $params['logger'] = $logger;
        }

        if ($_meta !== null) {
            $params['_meta'] = $_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, 'notifications/message', $params);
    }

    /**
     * @param LoggingLevel $level  The severity of this log message.
     * @param mixed $data  The data to be logged, such as a string message or an object. Any JSON serializable type is allowed here.
     * @param string|null $logger  An optional name of the logger issuing this message.
     * @param array|null $_meta  Optional metadata to include in the notification.
     */
    public static function make(LoggingLevel $level, mixed $data, ?string $logger = null, ?array $_meta = null): static
    {
        return new static($level, $data, $logger, $_meta);
    }

    public static function fromNotification(Notification $notification): static
    {
        if ($notification->method !== 'notifications/message') {
            throw new \InvalidArgumentException('Notification is not a notifications/message notification');
        }

        $params = $notification->params;

        if (! isset($params['level']) || ! is_string($params['level'])) {
            throw new \InvalidArgumentException('Missing or invalid level parameter for notifications/message notification');
        }

        if (! isset($params['data'])) {
            throw new \InvalidArgumentException('Missing data parameter for notifications/message notification');
        }

        $level = LoggingLevel::from($params['level']);

        return new static($level, $params['data'], $params['logger'] ?? null, $params['_meta'] ?? null);
    }
}
