<?php

declare(strict_types=1);

namespace PhpMcp\Schema;

use JsonSerializable;

/**
 * A template description for resources available on the server.
 */
class ResourceTemplate implements JsonSerializable
{
    /**
     * Resource name pattern regex - must contain only alphanumeric characters, underscores, and hyphens.
     */
    private const RESOURCE_NAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';

    /**
     * URI Template pattern regex - requires a valid scheme, followed by colon and path with at least one placeholder.
     * Example patterns: config://{key}, file://{path}/contents.txt, db://{table}/{id}, etc.
     */
    private const URI_TEMPLATE_PATTERN = '/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/.*{[^{}]+}.*/';

    /**
     * @param string $uriTemplate  A URI template (according to RFC 6570) that can be used to construct resource URIs.
     * @param string $name A human-readable name for the type of resource this template refers to. This can be used by clients to populate UI elements.
     * @param string|null $description This can be used by clients to improve the LLM's understanding of available resources. It can be thought of like a "hint" to the model.
     * @param string|null $mimeType The MIME type for all resources that match this template. This should only be included if all resources matching this template have the same type.
     * @param Annotations|null $annotations Optional annotations for the client.
     */
    public function __construct(
        public readonly string $uriTemplate,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $mimeType = null,
        public readonly ?Annotations $annotations = null
    ) {
        if (!preg_match(self::RESOURCE_NAME_PATTERN, $name)) {
            throw new \InvalidArgumentException("Invalid resource name: must contain only alphanumeric characters, underscores, and hyphens.");
        }
        if (!preg_match(self::URI_TEMPLATE_PATTERN, $uriTemplate)) {
            throw new \InvalidArgumentException("Invalid URI template: must be a valid URI template with at least one placeholder.");
        }
    }

    /**
     * @param string $uriTemplate  A URI template (according to RFC 6570) that can be used to construct resource URIs.
     * @param string $name A human-readable name for the type of resource this template refers to. This can be used by clients to populate UI elements.
     * @param string|null $description This can be used by clients to improve the LLM's understanding of available resources. It can be thought of like a "hint" to the model.
     * @param string|null $mimeType The MIME type for all resources that match this template. This should only be included if all resources matching this template have the same type.
     * @param Annotations|null $annotations Optional annotations for the client.
     */
    public static function make(string $uriTemplate, string $name, ?string $description = null, ?string $mimeType = null, ?Annotations $annotations = null): static
    {
        return new static($uriTemplate, $name, $description, $mimeType, $annotations);
    }

    public function toArray(): array
    {
        $data = [
            'uriTemplate' => $this->uriTemplate,
            'name' => $this->name,
        ];
        if ($this->description !== null) {
            $data['description'] = $this->description;
        }
        if ($this->mimeType !== null) {
            $data['mimeType'] = $this->mimeType;
        }
        if ($this->annotations !== null) {
            $data['annotations'] = $this->annotations->toArray();
        }
        return $data;
    }

    public static function fromArray(array $data): static
    {
        if (empty($data['uriTemplate']) || !is_string($data['uriTemplate'])) {
            throw new \InvalidArgumentException("Invalid or missing 'uriTemplate' in ResourceTemplate data.");
        }
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException("Invalid or missing 'name' in ResourceTemplate data.");
        }
        return new static(
            uriTemplate: $data['uriTemplate'],
            name: $data['name'],
            description: $data['description'] ?? null,
            mimeType: $data['mimeType'] ?? null,
            annotations: isset($data['annotations']) ? Annotations::fromArray($data['annotations']) : null
        );
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
