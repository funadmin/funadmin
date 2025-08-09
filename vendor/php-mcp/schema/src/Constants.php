<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

final class Constants
{
    public const LATEST_PROTOCOL_VERSION = "2025-03-26";
    public const JSONRPC_VERSION = "2.0";

    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;

    public const SERVER_ERROR = -32000;
    private function __construct() {}
}
