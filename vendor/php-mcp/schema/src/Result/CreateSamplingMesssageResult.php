<?php

declare(strict_types=1);

namespace PhpMcp\Schema\Result;

use PhpMcp\Schema\Content\AudioContent;
use PhpMcp\Schema\Content\ImageContent;
use PhpMcp\Schema\Content\TextContent;
use PhpMcp\Schema\Enum\Role;
use PhpMcp\Schema\JsonRpc\Result;

/**
 * The client's response to a sampling/create_message request from the server. The client should inform the user before returning the sampled message, to allow them to inspect the response (human in the loop) and decide whether to allow the server to see it.
 */
class CreateSamplingMesssageResult extends Result
{
    /**
     * @param  Role  $role  The role of the message.
     * @param  TextContent|ImageContent|AudioContent  $content  The content of the message.
     * @param  string  $model  The name of the model that generated the message.
     * @param  string|null  $stopReason  The reason why sampling stopped, if known.
     */
    public function __construct(
        public readonly Role $role,
        public readonly TextContent|ImageContent|AudioContent $content,
        public readonly string $model,
        public readonly ?string $stopReason = null,
    ) {
    }

    /**
     * @param  Role  $role  The role of the message.
     * @param  TextContent|ImageContent|AudioContent  $content  The content of the message.
     * @param  string  $model  The name of the model that generated the message.
     * @param  string|null  $stopReason  The reason why sampling stopped, if known.
     */
    public static function make(Role $role, TextContent|ImageContent|AudioContent $content, string $model, ?string $stopReason = null): static
    {
        return new static($role, $content, $model, $stopReason);
    }

    public function toArray(): array
    {
        $result = [
            'role' => $this->role->value,
            'content' => $this->content->toArray(),
            'model' => $this->model
        ];

        if ($this->stopReason !== null) {
            $result['stopReason'] = $this->stopReason;
        }
        return $result;
    }
}
