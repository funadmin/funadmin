<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\PromptReference;
use PhpMcp\Schema\ResourceReference;

/**
 * A request from the client to the server, to ask for completion options.
 */
class CompletionCompleteRequest extends Request
{
    /**
     * @param  PromptReference|ResourceReference  $ref  The prompt or resource to complete.
     * @param  array{ name: string, value: string }  $argument  The argument to complete.
     */
    public function __construct(
        string|int $id,
        public readonly PromptReference|ResourceReference $ref,
        public readonly array $argument,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'ref' => $this->ref->toArray(),
            'argument' => $this->argument,
        ];

        if ($this->_meta !== null) {
            $params['_meta'] = $this->_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'completion/complete', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param PromptReference|ResourceReference $ref  The prompt or resource to complete.
     * @param array<string, mixed> $argument  The arguments to complete.
     * @param array|null $_meta  Optional metadata to include in the request.
     */
    public static function make(string|int $id, PromptReference|ResourceReference $ref, array $argument, ?array $_meta = null): static
    {
        return new static($id, $ref, $argument, $_meta);
    }

    public static function fromRequest(Request $request): static
    {
        if ($request->method !== 'completion/complete') {
            throw new \InvalidArgumentException('Request is not a completion/complete request');
        }

        $params = $request->params;

        if (! isset($params['ref']) || ! is_array($params['ref'])) {
            throw new \InvalidArgumentException("Missing or invalid 'ref' parameter for completion/complete.");
        }

        $ref = match ($params['ref']['type'] ?? null) {
            'ref/prompt' => new PromptReference($params['ref']['name']),
            'ref/resource' => new ResourceReference($params['ref']['uri']),
            default => throw new \InvalidArgumentException("Invalid 'ref' parameter for completion/complete."),
        };

        if (! isset($params['argument']) || ! is_array($params['argument'])) {
            throw new \InvalidArgumentException("Missing or invalid 'argument' parameter for completion/complete.");
        }

        return new static($request->id, $ref, $params['argument'], $params['_meta'] ?? null);
    }
}
