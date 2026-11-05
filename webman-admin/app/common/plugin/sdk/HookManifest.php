<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** Validates declarations and file ownership without evaluating plugin code. */
final class HookManifest
{
    public static function validate(string $directory, array $data): void
    {
        foreach (['backendHooks', 'frontendHooks'] as $field) {
            $seen = [];
            foreach ($data[$field] ?? [] as $hook) {
                if (!in_array($hook['type'], $field === 'backendHooks' ? ['action', 'filter'] : ['action', 'filter', 'component'], true)) {
                    throw new RuntimeException('Invalid hook type');
                }
                if (array_key_exists('priority', $hook) && !is_int($hook['priority'])) {
                    throw new RuntimeException('Hook priority must be an integer');
                }
                $id = $hook['id'];
                if (isset($seen[$id])) {
                    throw new RuntimeException($field . ' duplicate id: ' . $id);
                }
                $seen[$id] = true;
                if ($field === 'backendHooks') {
                    self::file($directory, $hook['file']);
                    continue;
                }
                if (!isset($data['adminWeb'])) {
                    throw new RuntimeException('frontendHooks requires adminWeb');
                }
                $web = $data['adminWeb'];
                if ($hook['type'] === 'component') {
                    if (!isset($hook['component']) || isset($hook['file']) || isset($hook['export']) || !isset($web['components'][$hook['component']])) {
                        throw new RuntimeException('frontendHooks component must reference adminWeb.components');
                    }
                } else {
                    if (!isset($hook['file'], $hook['export']) || isset($hook['component'])) {
                        throw new RuntimeException('frontendHooks function requires file and export');
                    }
                    self::file($directory . '/' . $web['source'], $hook['file']);
                }
                $audiences = $hook['audiences'] ?? ['admin'];
                if ($audiences === [] || array_diff($audiences, ['public', 'member', 'admin']) !== []) {
                    throw new RuntimeException('Invalid frontendHooks audience');
                }
                if (count($audiences) !== count(array_unique($audiences))) {
                    throw new RuntimeException('frontendHooks audiences must be unique');
                }
                if (isset($hook['permission'])) {
                    if ($audiences !== ['admin']) {
                        throw new RuntimeException('frontendHooks permission requires audiences=[admin]');
                    }
                    $declared = array_column($web['permissions'] ?? [], 'code');
                    if (!in_array($hook['permission'], [...$declared, 'system:plugin:list'], true)) {
                        throw new RuntimeException('frontendHooks permission must be declared by plugin');
                    }
                }
            }
        }
    }

    private static function file(string $root, string $path): void
    {
        $base = realpath($root);
        $file = realpath($root . '/' . $path);
        if ($base === false || $file === false || !is_file($file) || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Hook file missing or outside source: ' . $path);
        }
        for ($current = $root . '/' . $path; strlen($current) >= strlen($root); $current = dirname($current)) {
            if (is_link($current)) {
                throw new RuntimeException('Hook symlinks are forbidden');
            }
            if ($current === $root) {
                break;
            }
        }
    }
}
