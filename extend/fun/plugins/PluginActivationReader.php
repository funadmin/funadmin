<?php

declare(strict_types=1);

namespace fun\plugins;

/** 仅从 active generation 文件读取插件激活状态，不访问控制面。 */
final class PluginActivationReader
{
    public function __construct(private readonly string $runtimePath)
    {
    }

    public function read(): PluginActivationSnapshot
    {
        $pointer = $this->runtimePath . DIRECTORY_SEPARATOR . 'active';
        if (!is_file($pointer)) {
            return PluginActivationSnapshot::untrusted();
        }
        $generation = trim((string) @file_get_contents($pointer));
        if (preg_match('/^[a-f0-9]{32}$/', $generation) !== 1) {
            return PluginActivationSnapshot::untrusted();
        }
        $file = $this->runtimePath . DIRECTORY_SEPARATOR . 'generations' . DIRECTORY_SEPARATOR . $generation . DIRECTORY_SEPARATOR . 'activation.php';
        if (!is_file($file)) {
            return PluginActivationSnapshot::untrusted();
        }
        try {
            $payload = require $file;
        } catch (\Throwable) {
            return PluginActivationSnapshot::untrusted();
        }
        if (!$this->valid($payload, $generation)) {
            return PluginActivationSnapshot::untrusted();
        }
        return PluginActivationSnapshot::trusted($payload['plugins']);
    }

    private function valid(mixed $payload, string $generation): bool
    {
        if (!is_array($payload)
            || ($payload['schema_version'] ?? null) !== PluginActivationCompiler::SCHEMA_VERSION
            || ($payload['generation'] ?? null) !== $generation
            || !is_string($payload['integrity_hash'] ?? null)
            || !is_array($payload['plugins'] ?? null)) {
            return false;
        }
        $hash = $payload['integrity_hash'];
        unset($payload['integrity_hash']);
        return hash_equals($hash, PluginActivationCompiler::integrityHash($payload));
    }
}
