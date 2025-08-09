<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * Capabilities that a server may support. Known capabilities are defined here, in this schema, but this is not a closed set: any server can define its own, additional capabilities.
 */
class ServerCapabilities implements JsonSerializable
{
    /**
     * @param bool|null $tools  Server exposes callable tools.
     * @param bool|null $toolsListChanged  Server supports list changed notifications for tools.
     * @param bool|null $resources  Server provides readable resources.
     * @param bool|null $resourcesSubscribe  Server supports subscribing to changes in the list of resources.
     * @param bool|null $resourcesListChanged  Server supports list changed notifications for resources.
     * @param bool|null $prompts  Server provides prompts templates.
     * @param bool|null $promptsListChanged  Server supports list changed notifications for prompts.
     * @param bool|null $logging  Server emits structured log messages.
     * @param bool|null $completions  Server supports argument autocompletion
     * @param array|null $experimental  Experimental, non-standard features that the server supports.
     */
    public function __construct(
        public readonly ?bool $tools = true,
        public readonly ?bool $toolsListChanged = false,
        public readonly ?bool $resources = true,
        public readonly ?bool $resourcesSubscribe = false,
        public readonly ?bool $resourcesListChanged = false,
        public readonly ?bool $prompts = true,
        public readonly ?bool $promptsListChanged = false,
        public readonly ?bool $logging = false,
        public readonly ?bool $completions = false,
        public readonly ?array $experimental = null
    ) {}

    /**
     * Create a new ServerCapabilities object.
     *
     * @param bool|null $tools  Server offers tools.
     * @param bool|null $toolsListChanged  Server supports sending a notification when the list of tools changes.
     * @param bool|null $resources  Server offers resources.
     * @param bool|null $resourcesSubscribe  Server supports subscribing to changes in the list of resources.
     * @param bool|null $resourcesListChanged  Server supports sending a notification when the list of resources changes.
     * @param bool|null $prompts  Server offers prompts.
     * @param bool|null $promptsListChanged  Server supports sending a notification when the list of prompts changes.
     * @param bool|null $logging  Server supports sending log messages to the client.
     * @param bool|null $completions  Server supports argument autocompletion suggestions.
     * @param array|null $experimental  Experimental, non-standard capabilities that the server supports.
     */
    public static function make(
        ?bool $tools = true,
        ?bool $toolsListChanged = false,
        ?bool $resources = true,
        ?bool $resourcesSubscribe = false,
        ?bool $resourcesListChanged = false,
        ?bool $prompts = true,
        ?bool $promptsListChanged = false,
        ?bool $logging = false,
        ?bool $completions = false,
        ?array $experimental = null
    ) {
        return new static(
            $tools,
            $toolsListChanged,
            $resources,
            $resourcesSubscribe,
            $resourcesListChanged,
            $prompts,
            $promptsListChanged,
            $logging,
            $completions,
            $experimental
        );
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->logging) {
            $data['logging'] = new \stdClass();
        }
        if ($this->completions) {
            $data['completions'] = new \stdClass();
        }

        if ($this->prompts || $this->promptsListChanged) {
            $data['prompts'] = new \stdClass();
            if ($this->promptsListChanged) {
                $data['prompts']->listChanged = $this->promptsListChanged;
            }
        }

        if ($this->resources || $this->resourcesSubscribe || $this->resourcesListChanged) {
            $data['resources'] = new \stdClass();
            if ($this->resourcesSubscribe) {
                $data['resources']->subscribe = $this->resourcesSubscribe;
            }
            if ($this->resourcesListChanged) {
                $data['resources']->listChanged = $this->resourcesListChanged;
            }
        }

        if ($this->tools || $this->toolsListChanged) {
            $data['tools'] = new \stdClass();
            if ($this->toolsListChanged) {
                $data['tools']->listChanged = $this->toolsListChanged;
            }
        }

        if ($this->experimental) {
            $data['experimental'] = (object) $this->experimental;
        }

        return $data;
    }

    public static function fromArray(array $data): static
    {
        $loggingEnabled = isset($data['logging']);
        $completionsEnabled = isset($data['completions']);
        $toolsEnabled = isset($data['tools']);
        $promptsEnabled = isset($data['prompts']);
        $resourcesEnabled = isset($data['resources']);

        $promptsListChanged = null;
        if (isset($data['prompts'])) {
            if (is_array($data['prompts']) && array_key_exists('listChanged', $data['prompts'])) {
                $promptsListChanged = (bool) $data['prompts']['listChanged'];
            } elseif (is_object($data['prompts']) && property_exists($data['prompts'], 'listChanged')) {
                $promptsListChanged = (bool) $data['prompts']->listChanged;
            }
        }


        $resourcesSubscribe = null;
        $resourcesListChanged = null;
        if (isset($data['resources'])) {
            if (is_array($data['resources']) && array_key_exists('subscribe', $data['resources'])) {
                $resourcesSubscribe = (bool) $data['resources']['subscribe'];
            } elseif (is_object($data['resources']) && property_exists($data['resources'], 'subscribe')) {
                $resourcesSubscribe = (bool) $data['resources']->subscribe;
            }
            if (is_array($data['resources']) && array_key_exists('listChanged', $data['resources'])) {
                $resourcesListChanged = (bool) $data['resources']['listChanged'];
            } elseif (is_object($data['resources']) && property_exists($data['resources'], 'listChanged')) {
                $resourcesListChanged = (bool) $data['resources']->listChanged;
            }
        }

        $toolsListChanged = null;
        if (isset($data['tools'])) {
            if (is_array($data['tools']) && array_key_exists('listChanged', $data['tools'])) {
                $toolsListChanged = (bool) $data['tools']['listChanged'];
            } elseif (is_object($data['tools']) && property_exists($data['tools'], 'listChanged')) {
                $toolsListChanged = (bool) $data['tools']->listChanged;
            }
        }

        return new static(
            tools: $toolsEnabled,
            toolsListChanged: $toolsListChanged,
            resources: $resourcesEnabled,
            resourcesSubscribe: $resourcesSubscribe,
            resourcesListChanged: $resourcesListChanged,
            prompts: $promptsEnabled,
            promptsListChanged: $promptsListChanged,
            logging: $loggingEnabled,
            completions: $completionsEnabled,
            experimental: $data['experimental'] ?? null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
