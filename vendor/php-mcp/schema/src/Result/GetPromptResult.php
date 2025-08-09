<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Content\PromptMessage;
use PhpMcp\Schema\JsonRpc\Response;

class GetPromptResult extends Result
{
    /**
     * Create a new GetPromptResult.
     *
     * @param  PromptMessage[]  $messages  The messages in the prompt
     * @param  string|null  $description  Optional description of the prompt
     */
    public function __construct(
        public readonly array $messages,
        public readonly ?string $description = null
    ) {
        foreach ($this->messages as $message) {
            if (!$message instanceof PromptMessage) {
                throw new \InvalidArgumentException('Messages must be an array of PromptMessage objects.');
            }
        }
    }

    /**
     * Create a new GetPromptResult.
     *
     * @param  PromptMessage[]  $messages  The messages in the prompt
     * @param  string|null  $description  Optional description of the prompt
     */
    public static function make(array $messages, ?string $description = null): static
    {
        return new static($messages, $description);
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        $result = [
            'messages' => array_map(fn($message) => $message->toArray(), $this->messages),
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        return $result;
    }

    public static function fromArray(array $data): static
    {
        if (!isset($data['messages']) || !is_array($data['messages'])) {
            throw new \InvalidArgumentException("Missing or invalid 'messages' array in GetPromptResult data.");
        }

        $messages = [];
        foreach ($data['messages'] as $message) {
            $messages[] = PromptMessage::fromArray($message);
        }

        return new static(
            $messages,
            $data['description'] ?? null
        );
    }

    public static function fromResponse(Response $response): static
    {
        return self::fromArray($response->result);
    }
}
