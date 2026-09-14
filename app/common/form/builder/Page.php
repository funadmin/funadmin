<?php

declare(strict_types=1);
namespace app\common\form\builder;

use app\common\form\schema\ListButtonSchemaValidator;
use InvalidArgumentException;

/** 专用 CRUD 的纯展示定义，不发布、不绑定数据库、不执行动作。 */
final class Page
{
    private array $definition;
    private function __construct(string $key) {
        $this->definition = ['pageSchemaVersion' => 1, 'key' => $key, 'search' => [], 'columns' => [], 'toolbar' => [], 'rowActions' => [], 'pagination' => ['pageSize' => 20, 'pageSizes' => [10, 20, 50, 100]]];
    }
    public static function make(string $key): self { return new self($key); }
    public function list(array $configuration): self { $this->definition['list'] = $configuration; return $this; }
    public function search(array $items): self { $this->definition['search'] = $items; return $this; }
    public function columns(array $items): self { $this->definition['columns'] = $items; return $this; }
    public function toolbar(array $items): self { $this->definition['toolbar'] = $items; return $this; }
    public function rowActions(array $items): self { $this->definition['rowActions'] = $items; return $this; }
    public function pagination(int $pageSize, array $pageSizes): self { $this->definition['pagination'] = compact('pageSize', 'pageSizes'); return $this; }
    public function compile(): array { self::validate($this->definition); return $this->definition; }

    public static function validate(array $page): void
    {
        self::keys($page, ['pageSchemaVersion', 'key', 'search', 'columns', 'toolbar', 'rowActions', 'pagination', 'list']);
        if (($page['pageSchemaVersion'] ?? null) !== 1) self::fail();
        self::identifier($page['key'] ?? null);
        foreach (['search', 'columns', 'toolbar', 'rowActions'] as $section) {
            $items = $page[$section] ?? null;
            if (!is_array($items) || !array_is_list($items) || count($items) > 100) self::fail();
            $ids = [];
            foreach ($items as $item) {
                if (!is_array($item)) self::fail();
                $id = $item[$section === 'search' ? 'field' : ($section === 'columns' ? 'key' : 'id')] ?? null;
                self::identifier($id);
                if (isset($ids[$id])) self::fail();
                $ids[$id] = true;
                if (!is_string($item['label'] ?? null) || strlen($item['label']) > 300) self::fail();
                foreach (['visibleWhen', 'disabledWhen', 'activeWhen'] as $condition) if (isset($item[$condition])) self::condition($item[$condition]);
                if ($section === 'search') {
                    self::keys($item, ['field', 'label', 'type', 'placeholder', 'options']);
                    if (!in_array($item['type'] ?? null, ['input', 'select'], true)) self::fail();
                    if (isset($item['placeholder']) && !is_string($item['placeholder'])) self::fail();
                    if (isset($item['options'])) {
                        if (!is_array($item['options']) || !array_is_list($item['options'])) self::fail();
                        foreach ($item['options'] as $option) {
                            if (!is_array($option)) self::fail();
                            self::keys($option, ['label', 'value']);
                            if (!is_string($option['label'] ?? null) || (!is_string($option['value'] ?? null) && !is_int($option['value'] ?? null))) self::fail();
                        }
                    }
                } elseif ($section === 'columns') {
                    self::keys($item, ['key', 'label', 'prop', 'type', 'width', 'minWidth', 'align', 'fixed', 'slot', 'formatter', 'visibleWhen']);
                    foreach (['prop', 'slot', 'formatter'] as $key) if (isset($item[$key])) self::identifier($item[$key]);
                    foreach (['width', 'minWidth'] as $key) if (isset($item[$key]) && (!is_int($item[$key]) || $item[$key] < 1 || $item[$key] > 2000)) self::fail();
                    if (isset($item['type']) && $item['type'] !== 'selection') self::fail();
                    if (isset($item['align']) && !in_array($item['align'], ['left', 'center', 'right'], true)) self::fail();
                    if (isset($item['fixed']) && !in_array($item['fixed'], ['left', 'right'], true)) self::fail();
                } else {
                    self::keys($item, ['id', 'label', 'icon', 'color', 'permission', 'hidden', 'disabled', 'visibleWhen', 'disabledWhen', 'activeWhen', 'inactiveColor', 'selectionCount', 'action']);
                    if (isset($item['selectionCount']) && !is_bool($item['selectionCount'])) self::fail();
                    if (isset($item['inactiveColor']) && !in_array($item['inactiveColor'], ['primary', 'info', 'warning', 'danger', 'success'], true)) self::fail();
                    if (($item['action']['type'] ?? '') !== 'registered') self::fail();
                    $base = array_diff_key($item, array_flip(['visibleWhen', 'disabledWhen', 'activeWhen', 'inactiveColor', 'selectionCount']));
                    (new ListButtonSchemaValidator())->validate(['buttons' => [$section === 'toolbar' ? 'toolbar' : 'row' => [$base]]], []);
                }
            }
        }
        if (array_key_exists('list', $page)) {
            if (!is_array($page['list'])) self::fail();
            $list = $page['list'];
            self::keys($list, ['category', 'leftTree', 'buttons']);
            if (isset($list['category'])) {
                $category = $list['category'];
                if (!is_array($category)) self::fail();
                self::keys($category, ['enabled', 'field']);
                if (!is_bool($category['enabled'] ?? null)) self::fail();
                if (isset($category['field'])) self::identifier($category['field']);
                if ($category['enabled']) {
                    $fields = array_column($page['search'], null, 'field');
                    $field = $fields[$category['field'] ?? ''] ?? [];
                    if (($field['type'] ?? '') !== 'select' || !array_key_exists('options', $field)) self::fail();
                }
            } elseif (array_key_exists('category', $list)) self::fail();
            try {
                if (array_key_exists('leftTree', $list)) (new \app\common\form\schema\FormSchemaValidator())->validateLeftTree($list['leftTree']);
                if (array_key_exists('buttons', $list)) {
                    if (!is_array($list['buttons'])) self::fail();
                    self::keys($list['buttons'], ['categoryToolbar', 'categoryNode']);
                    (new ListButtonSchemaValidator())->validate($list, []);
                }
            } catch (\app\common\form\schema\FormSchemaException $e) { self::fail(); }
        }
        $pagination = $page['pagination'] ?? [];
        self::keys($pagination, ['pageSize', 'pageSizes']);
        if (!is_array($pagination['pageSizes'] ?? null) || !array_is_list($pagination['pageSizes']) || !$pagination['pageSizes']) self::fail();
        foreach ($pagination['pageSizes'] as $size) if (!is_int($size) || $size < 1 || $size > 1000) self::fail();
        if (!in_array($pagination['pageSize'] ?? null, $pagination['pageSizes'], true)) self::fail();
    }
    private static function condition(mixed $value): void {
        if (!is_array($value)) self::fail();
        self::keys($value, ['field', 'op', 'value']);
        self::identifier($value['field'] ?? null);
        if (!in_array($value['op'] ?? null, ['eq', 'neq'], true) || !array_key_exists('value', $value) || (!is_scalar($value['value']) && $value['value'] !== null)) self::fail();
    }
    private static function identifier(mixed $value): void {
        if (!is_string($value) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $value) || in_array($value, ['constructor', 'prototype', '__proto__'], true)) self::fail();
    }
    private static function keys(array $value, array $allowed): void { if (array_diff(array_keys($value), $allowed)) self::fail(); }
    private static function fail(): never { throw new InvalidArgumentException('PAGE_SCHEMA_INVALID'); }
}
