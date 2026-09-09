<?php

declare(strict_types=1);

namespace fun\form;

use app\common\form\schema\FormSchema;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;

final class Form extends NodeCollection
{
    private array $schema;

    private function __construct(string $key)
    {
        $nodes = [];
        parent::__construct($nodes);
        $this->schema = [
            'schemaVersion' => 2, 'key' => $key, 'title' => $key, 'model' => [], 'layout' => [], 'nodes' => [],
            'dataSources' => [], 'actions' => [], 'form' => [], 'submit' => [], 'list' => [],
            'database' => ['table' => '', 'connection' => 'mysql', 'source' => 'created'], 'extensions' => [],
        ];
        $this->nodes =& $this->schema['nodes'];
    }

    public static function make(string $key): self { return new self($key); }
    public function title(string $title): self { $this->schema['title'] = $title; return $this; }
    public function model(array $model): self { $this->schema['model'] = $model; return $this; }
    public function layout(array $layout): self { $this->schema['layout'] = $layout; return $this; }
    public function form(array $form): self { $this->schema['form'] = $form; return $this; }
    public function submit(array $submit): self { $this->schema['submit'] = $submit; return $this; }
    public function list(array $list): self { $this->schema['list'] = $list; return $this; }
    public function extension(string $name, mixed $value): self { $this->schema['extensions'][$name] = $value; return $this; }

    public function table(string $table, string $connection = 'mysql', string $source = 'created'): self
    {
        $this->schema['database'] = compact('table', 'connection', 'source');
        return $this;
    }

    public function dataSource(string $id, string $kind, array $definition = []): self
    {
        $this->schema['dataSources'][] = ['id' => $id, 'kind' => $kind] + $definition;
        return $this;
    }

    public function dataSourceBuilder(string $id, string $kind, array $definition = []): DataSourceBuilder
    {
        $this->schema['dataSources'][] = ['id' => $id, 'kind' => $kind] + $definition;
        return new DataSourceBuilder($this->schema['dataSources'][array_key_last($this->schema['dataSources'])]);
    }

    public function action(string $id, string $event): ActionBuilder
    {
        $this->schema['actions'][] = ['id' => $id, 'event' => $event, 'steps' => []];
        return new ActionBuilder($this->schema['actions'][array_key_last($this->schema['actions'])]);
    }

    public function onSubmit(string $id = 'submit'): ActionBuilder
    {
        $this->schema['submit']['action'] = $id;
        return $this->action($id, 'submit');
    }

    public function toArray(): array { return $this->schema; }
    public function toJson(): string { return $this->compile()->canonicalJson(); }

    public function compile(): FormSchema
    {
        return (new FormSchemaCompiler(new FormSchemaValidator()))->compile($this->schema);
    }

    public function validate(): self
    {
        $this->compile();
        return $this;
    }

    public function register(int $formId, string $actor = 'php-builder', string $summary = ''): \app\console\model\FormSchemaVersion
    {
        return (new \app\console\service\FormBuilderRegistryService())->register($formId, $this, $actor, $summary);
    }
}
