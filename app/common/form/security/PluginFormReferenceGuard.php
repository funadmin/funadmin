<?php

declare(strict_types=1);

namespace app\common\form\security;

use app\console\model\Form;
use RuntimeException;

/** 阻止卸载仍被持久化 FormSchema 引用的插件。 */
final class PluginFormReferenceGuard
{
    public function __construct(private readonly mixed $forms = null)
    {
    }

    public function assertNotReferenced(string $pluginCode): void
    {
        foreach ($this->forms() as $form) {
            $row = is_array($form) ? $form : $form->toArray();
            $type = $this->referencedType((array) ($row['schema_document'] ?? []), $pluginCode);
            if ($type === null) {
                continue;
            }
            $identifier = (string) ($row['form_key'] ?? $row['id'] ?? 'unknown');
            throw new RuntimeException("插件 {$pluginCode} 的组件 {$type} 仍被表单 {$identifier} 引用，禁止卸载");
        }
    }

    private function forms(): iterable
    {
        if (is_callable($this->forms)) {
            return ($this->forms)();
        }
        return Form::field(['id', 'form_key', 'schema_document'])->whereNotNull('schema_document')->select();
    }

    private function referencedType(array $document, string $pluginCode): ?string
    {
        $stack = [(array) ($document['nodes'] ?? [])];
        while ($stack !== []) {
            $nodes = array_pop($stack);
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $type = (string) ($node['type'] ?? '');
                if (str_starts_with($type, $pluginCode . ':')) {
                    return $type;
                }
                if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                    $stack[] = $node['children'];
                }
            }
        }
        return null;
    }
}
