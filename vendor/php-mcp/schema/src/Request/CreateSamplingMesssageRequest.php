<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Request;

use PhpMcp\Schema\Constants;
use PhpMcp\Schema\Content\SamplingMessage;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\ModelPreferences;

/**
 * A request from the server to sample an LLM via the client. The client has full discretion over which model to select. The client should also inform the user before beginning sampling, to allow them to inspect the request (human in the loop) and decide whether to approve it.
 */
class CreateSamplingMesssageRequest extends Request
{
    /**
     * @param  SamplingMessage[]  $messages  The messages to send to the model.
     * @param  int  $maxTokens  The maximum number of tokens to sample, as requested by the server. The client MAY choose to sample fewer tokens than requested.
     * @param  ModelPreferences|null  $preferences The server's preferences for which model to select. The client MAY ignore these preferences.
     * @param  string|null  $systemPrompt  An optional system prompt the server wants to use for sampling. The client MAY modify or omit this prompt.
     * @param  string|null  $includeContext  A request to include context from one or more MCP servers (including the caller), to be attached to the prompt. The client MAY ignore this request.
     *
     * Allowed values: "none", "thisServer", "allServers"
     * @param  float|null  $temperature  The temperature to use for sampling. The client MAY ignore this request.
     * @param  string[]|null  $stopSequences  A list of sequences to stop sampling at. The client MAY ignore this request.
     * @param  array|null  $metadata  Optional metadata to pass through to the LLM provider. The format of this metadata is provider-specific.
     * @param  array|null  $_meta  Optional metadata to include in the request.
     */
    public function __construct(
        string|int $id,
        public readonly array $messages,
        public readonly int $maxTokens,
        public readonly ?ModelPreferences $preferences = null,
        public readonly ?string $systemPrompt = null,
        public readonly ?string $includeContext = null,
        public readonly ?float $temperature = null,
        public readonly ?array $stopSequences = null,
        public readonly ?array $metadata = null,
        public readonly ?array $_meta = null
    ) {
        $params = [
            'messages' => array_map(fn (SamplingMessage $message) => $message->toArray(), $this->messages),
            'maxTokens' => $this->maxTokens,
        ];

        if ($this->preferences !== null) {
            $params['preferences'] = $this->preferences->toArray();
        }

        if ($this->systemPrompt !== null) {
            $params['systemPrompt'] = $this->systemPrompt;
        }

        if ($this->includeContext !== null) {
            $params['includeContext'] = $this->includeContext;
        }

        if ($this->temperature !== null) {
            $params['temperature'] = $this->temperature;
        }

        if ($this->stopSequences !== null) {
            $params['stopSequences'] = $this->stopSequences;
        }

        if ($this->metadata !== null) {
            $params['metadata'] = $this->metadata;
        }

        if ($this->_meta !== null) {
            $params['_meta'] = $this->_meta;
        }

        parent::__construct(Constants::JSONRPC_VERSION, $id, 'sampling/createMessage', $params);
    }

    /**
     * @param string|int $id  The ID of the request to cancel.
     * @param SamplingMessage[] $messages  The messages to send to the model.
     * @param int $maxTokens  The maximum number of tokens to sample, as requested by the server. The client MAY choose to sample fewer tokens than requested.
     * @param ModelPreferences|null $preferences The server's preferences for which model to select. The client MAY ignore these preferences.
     * @param string|null $systemPrompt  An optional system prompt the server wants to use for sampling. The client MAY modify or omit this prompt.
     */
    public static function make(string|int $id, array $messages, int $maxTokens, ?ModelPreferences $preferences = null, ?string $systemPrompt = null, ?string $includeContext = null, ?float $temperature = null, ?array $stopSequences = null, ?array $metadata = null, ?array $_meta = null): static
    {
        return new static($id, $messages, $maxTokens, $preferences, $systemPrompt, $includeContext, $temperature, $stopSequences, $metadata, $_meta);
    }
}
