<?php

declare(strict_types=1);

namespace PhpMcp\Schema\JsonRpc;

use PhpMcp\Schema\Constants;

class Parser
{
    /**
     * Parses a raw JSON string into a JSON-RPC Message object (Request, Notification, Response, Error, or Batch variants).
     *
     * This method determines if the incoming message is a request-like message (Request, Notification, BatchRequest)
     * or a response-like message (Response, Error, BatchResponse) based on the presence of 'method' vs 'result'/'error'.
     *
     * @param string $json The raw JSON string to parse.
     * @return Message A specific instance of Request, Notification, Response, Error, BatchRequest, or BatchResponse.
     * @throws JsonException If the string is not valid JSON.
     * @throws \InvalidArgumentException If the JSON structure does not conform to a recognizable JSON-RPC message type.
     */
    public static function parse(string $json): Message
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON-RPC message: Root must be an object or array.');
        }

        if (array_is_list($data) && !empty($data)) {
            $firstItem = $data[0];
            if (!is_array($firstItem)) {
                throw new \InvalidArgumentException('Invalid JSON-RPC batch: Items must be objects.');
            }

            if (isset($firstItem['method'])) {
                return BatchRequest::fromArray($data);
            } elseif (isset($firstItem['id']) && (isset($firstItem['result']) || isset($firstItem['error']))) {
                return BatchResponse::fromArray($data);
            } else {
                throw new \InvalidArgumentException('Invalid JSON-RPC batch: Items are not recognizable requests or responses.');
            }
        }

        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Constants::JSONRPC_VERSION) {
            throw new \InvalidArgumentException('Invalid or missing "jsonrpc" version. Must be "' . Constants::JSONRPC_VERSION . '".');
        }

        if (isset($data['method'])) {
            if (isset($data['id']) && $data['id'] !== null) {
                return Request::fromArray($data);
            } else {
                return Notification::fromArray($data);
            }
        } elseif (isset($data['id'])) {
            if (array_key_exists('result', $data)) {
                return Response::fromArray($data);
            } elseif (isset($data['error'])) {
                return Error::fromArray($data);
            } else {
                throw new \InvalidArgumentException('Invalid JSON-RPC response/error: Missing "result" or "error" field for message with "id".');
            }
        }

        throw new \InvalidArgumentException('Unrecognized JSON-RPC message structure.');
    }

    /**
     * Specifically parses a raw JSON string into a request-like Message object
     * (Request, Notification, or BatchRequest).
     * Useful on the server-side.
     *
     * @param string $json The raw JSON string to parse.
     * @return Request|Notification|BatchRequest
     * @throws JsonException If the string is not valid JSON.
     * @throws \InvalidArgumentException If the JSON structure does not conform to a request-like message.
     */
    public static function parseRequestMessage(string $json): Request|Notification|BatchRequest
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON-RPC request: Root must be an object or array.');
        }

        if (array_is_list($data) && !empty($data)) {
            return BatchRequest::fromArray($data);
        }

        if (!isset($data['jsonrpc']) || $data['jsonrpc'] !== Constants::JSONRPC_VERSION) {
            throw new \InvalidArgumentException('Invalid or missing "jsonrpc" version for request. Must be "' . Constants::JSONRPC_VERSION . '".');
        }

        if (isset($data['method'])) {
            if (isset($data['id']) && $data['id'] !== null) {
                return Request::fromArray($data);
            } else {
                return Notification::fromArray($data);
            }
        }

        throw new \InvalidArgumentException('Invalid JSON-RPC request message: Missing "method" field.');
    }

    /**
     * Specifically parses a raw JSON string into a response-like Message object
     * (Response, Error, or BatchResponse).
     * Useful on the client-side.
     *
     * @param string $json The raw JSON string to parse.
     * @return Response|Error|BatchResponse
     * @throws JsonException If the string is not valid JSON.
     * @throws \InvalidArgumentException If the JSON structure does not conform to a response-like message.
     */
    public static function parseResponseMessage(string $json): Response|Error|BatchResponse
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON-RPC response: Root must be an object or array.');
        }

        if (array_is_list($data) && !empty($data)) {
            return BatchResponse::fromArray($data);
        }

        if (array_key_exists('result', $data)) {
            return Response::fromArray($data);
        } elseif (isset($data['error'])) {
            return Error::fromArray($data);
        }

        throw new \InvalidArgumentException('Invalid JSON-RPC response/error: Missing "result" or "error" field for message with "id".');
    }
}
