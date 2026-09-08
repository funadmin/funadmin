<?php

declare(strict_types=1);

namespace fun\form;

use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;
use JsonException;

final class Form extends NodeCollection
{
    private array $schema;

    private function __construct(string $key)
    {
        $nodes = [];
        parent::__construct($nodes);
        $this->schema = [
            'schemaVersion' => 2,
            'key' => $key,
            'title' => $key,
            'model' => [],
            'layout' => [],
            'nodes' => [],
            'dataSources' => [],
            'actions' => [],
            'form' => [],
            'submit' => [],
            'list' => [],
            'database' => ['table' => '', 'connection' => 'mysql', 'source' => 'created'],
            'extensions' => ['origin' => 'php'],
        ];
        $this->nodes =& $this->schema['nodes'];
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function title(string $title): self
    {
        $this->schema['title'] = $title;
        return $this;
    }

    public function table(string $table, string $connection = 'mysql', string $source = 'created'): self
    {
        $this->schema['database'] = ['table' => $table, 'connection' => $connection, 'source' => $source];
        return $this;
    }

    public function onSubmit(string $id = 'submit'): ActionBuilder
    {
        $this->schema['actions'][] = ['id' => $id, 'event' => 'submit', 'steps' => []];
        $this->schema['submit']['action'] = $id;
        return new ActionBuilder($this->schema['actions'][array_key_last($this->schema['actions'])]);
    }

    public function toArray(): array
    {
        return $this->schema;
    }

    public function toJson(): string
    {
        try {
            return json_encode($this->schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('表单规则无法编码：' . $exception->getMessage(), previous: $exception);
        }
    }

    public function compile(): FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile($this->schema);
    }

    public function validate(): self
    {
        $this->compile();
        return $this;
    }
}
