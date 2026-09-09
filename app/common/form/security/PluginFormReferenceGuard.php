<?php

declare(strict_types=1);

namespace app\common\form\security;

use app\console\model\Form;
use app\console\model\FormSchemaVersion;
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
            $reference = $this->reference((array) ($row['schema_document'] ?? []), $pluginCode);
            if ($reference === null) continue;
            $identifier = (string) ($row['form_key'] ?? $row['id'] ?? 'unknown');
            throw new RuntimeException("插件 {$pluginCode} 的能力 {$reference} 仍被表单 {$identifier} 引用，禁止卸载");
        }
        foreach ($this->versions() as $version) {
            $row = is_array($version) ? $version : $version->toArray();
            $reference = $this->reference((array) ($row['schema_document'] ?? []), $pluginCode);
            if ($reference === null) continue;
            throw new RuntimeException("插件 {$pluginCode} 的能力 {$reference} 仍被已发布表单版本 {$row['form_id']}:{$row['version']} 引用，禁止卸载");
        }
    }

    private function forms(): iterable
    {
        if (is_callable($this->forms)) {
            return ($this->forms)();
        }
        return Form::field(['id', 'form_key', 'schema_document'])->whereNotNull('schema_document')->select();
    }

    private function versions(): iterable
    {
        if (is_callable($this->forms)) return [];
        return FormSchemaVersion::field(['form_id', 'version', 'schema_document'])->select();
    }

    private function reference(array $document, string $pluginCode): ?string
    {
        foreach (['dataSources', 'actions'] as $section) {
            $found = $this->findNamespacedValue((array) ($document[$section] ?? []), $pluginCode);
            if ($found !== null) return $found;
        }
        $stack = [(array) ($document['nodes'] ?? [])];
        while ($stack !== []) {
            $nodes = array_pop($stack);
            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $found = $this->findNamespacedValue($node, $pluginCode);
                if ($found !== null) return $found;
                if (is_array($node['children'] ?? null) && $node['children'] !== []) {
                    $stack[] = $node['children'];
                }
            }
        }
        return null;
    }

    private function findNamespacedValue(mixed $value, string $pluginCode): ?string
    {
        if (is_string($value) && str_starts_with($value, $pluginCode . ':')) return $value;
        if (!is_array($value)) return null;
        foreach ($value as $item) {
            $found = $this->findNamespacedValue($item, $pluginCode);
            if ($found !== null) return $found;
        }
        return null;
    }
}
