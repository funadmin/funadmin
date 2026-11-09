<?php

declare(strict_types=1);

namespace app\common\form\schema;

/**
 * 单个数据字段节点（kind !== 'layout'）的只读视图。
 * 字段默认值只在这里定义，所有后端消费方都通过本类读取。
 */
final class FormFieldView
{
    private function __construct(private readonly array $node)
    {
    }

    public static function fromNode(array $node): self
    {
        return new self($node);
    }

    public function node(): array
    {
        return $this->node;
    }

    public function nodeId(): string
    {
        return (string) ($this->node['id'] ?? '');
    }

    public function name(): string
    {
        return (string) ($this->node['field'] ?? '');
    }

    public function title(): string
    {
        return (string) ($this->node['title'] ?? '');
    }

    public function type(): string
    {
        return (string) ($this->node['type'] ?? '');
    }

    /** repeatable / subform 是子表容器，不对应主表列。 */
    public function isRelationContainer(): bool
    {
        return in_array($this->type(), ['repeatable', 'subform'], true);
    }

    public function isVirtual(): bool
    {
        return $this->isRelationContainer() || $this->relationType() === 'has_many';
    }

    public function columnType(): string
    {
        return (string) ($this->database()['columnType'] ?? '');
    }

    public function nullable(): bool
    {
        return (bool) ($this->database()['nullable'] ?? true);
    }

    public function unsigned(): bool
    {
        return (bool) ($this->database()['unsigned'] ?? false);
    }

    public function index(): string
    {
        return (string) ($this->database()['index'] ?? 'none');
    }

    public function comment(): string
    {
        return (string) ($this->database()['comment'] ?? '');
    }

    public function defaultValue(): mixed
    {
        return $this->node['defaultValue'] ?? null;
    }

    public function props(): array
    {
        return $this->object('props');
    }

    public function access(): array
    {
        return $this->object('access');
    }

    /** @return list<array<string, mixed>> */
    public function validation(): array
    {
        return array_values(array_filter($this->object('validation'), 'is_array'));
    }

    public function required(): bool
    {
        foreach ($this->validation() as $rule) {
            if (($rule['type'] ?? '') === 'required') return true;
        }
        return false;
    }

    /** @return array<string, mixed> 规则类型 => 参数（无参数为 true），不含 required。 */
    public function rules(): array
    {
        $rules = [];
        foreach ($this->validation() as $rule) {
            $type = (string) ($rule['type'] ?? '');
            if ($type !== '' && $type !== 'required') $rules[$type] = $rule['value'] ?? true;
        }
        return $rules;
    }

    public function conditions(): array
    {
        return $this->object('conditions');
    }

    public function dataSource(): ?array
    {
        $source = $this->node['dataSource'] ?? null;
        return is_array($source) ? $source : null;
    }

    public function dataSourceKind(): string
    {
        return (string) ($this->dataSource()['kind'] ?? '');
    }

    public function relationType(): string
    {
        return (string) ($this->relation()['type'] ?? 'none');
    }

    public function relationTable(): string
    {
        return (string) ($this->relation()['table'] ?? '');
    }

    public function relationLabelField(): string
    {
        return (string) ($this->relation()['labelField'] ?? '');
    }

    /** 原样返回；缺省为 ''，需要 id 回退的调用方自行 `?: 'id'`。 */
    public function relationValueField(): string
    {
        return (string) ($this->relation()['valueField'] ?? '');
    }

    public function relationMultiple(): bool
    {
        return (bool) ($this->relation()['multiple'] ?? false);
    }

    public function relationOnDelete(): string
    {
        return (string) ($this->relation()['onDelete'] ?? 'restrict');
    }

    public function listShow(): bool
    {
        return (bool) ($this->list()['show'] ?? false);
    }

    public function listSort(): bool
    {
        return (bool) ($this->list()['sort'] ?? false);
    }

    public function listFilter(): string
    {
        return (string) ($this->list()['filter'] ?? '');
    }

    public function listFormatter(): string
    {
        return (string) ($this->list()['formatter'] ?? '');
    }

    public function listWidth(): int
    {
        return (int) ($this->list()['width'] ?? 0);
    }

    public function hidden(): bool
    {
        return (bool) ($this->node['hidden'] ?? false);
    }

    public function disabled(): bool
    {
        return (bool) ($this->node['disabled'] ?? false);
    }

    /** 只认整数栅格；响应式对象等其它形态回退为 24。 */
    public function span(): int
    {
        $span = $this->object('layout')['span'] ?? null;
        return is_int($span) ? $span : 24;
    }

    public function group(): string
    {
        return (string) ($this->object('layout')['group'] ?? '');
    }

    private function database(): array
    {
        return $this->object('database');
    }

    private function relation(): array
    {
        $relation = $this->database()['relation'] ?? null;
        return is_array($relation) ? $relation : [];
    }

    private function list(): array
    {
        return $this->object('list');
    }

    private function object(string $key): array
    {
        $value = $this->node[$key] ?? null;
        return is_array($value) ? $value : [];
    }
}
