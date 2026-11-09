<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

/** 仅从 active generation 的 JSON 文件读取插件激活状态，不访问控制面。 */
class PluginActivationReader
{
    public function __construct(private readonly string $runtimePath, private readonly bool $allowUninitialized = false)
    {
    }

    public function read(): PluginActivationSnapshot
    {
        return $this->readBundle()['activation'];
    }

    public function activeGeneration(): ?string
    {
        $generation = trim((string) @file_get_contents($this->runtimePath . DIRECTORY_SEPARATOR . 'active'));
        return preg_match('/^[a-f0-9]{32}$/', $generation) === 1 ? $generation : null;
    }

    public function readOwnership(): PluginOwnershipSnapshot
    {
        return $this->readBundle()['ownership'];
    }

    /** @return array{activation: PluginActivationSnapshot, ownership: PluginOwnershipSnapshot} */
    public function readBundle(): array
    {
        // Existing deployments without any activation directory have no hook contributions.
        // Once initialized, a missing or damaged pointer must always fail closed.
        if ($this->allowUninitialized && !file_exists($this->runtimePath) && !is_link($this->runtimePath)) {
            return ['activation' => PluginActivationSnapshot::trusted([]), 'ownership' => PluginOwnershipSnapshot::trusted([])];
        }
        $untrusted = $this->untrustedBundle();
        $pointer = $this->runtimePath . DIRECTORY_SEPARATOR . 'active';
        $generation = trim((string) @file_get_contents($pointer));
        if (preg_match('/^[a-f0-9]{32}$/', $generation) !== 1) {
            return $untrusted;
        }

        $file = $this->runtimePath . DIRECTORY_SEPARATOR . 'generations' . DIRECTORY_SEPARATOR . $generation . DIRECTORY_SEPARATOR . 'snapshot.json';
        $json = @file_get_contents($file);
        if (!is_string($json)) {
            return $untrusted;
        }

        try {
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $untrusted;
        }
        if (!$this->validEnvelope($payload, $generation)) {
            return $untrusted;
        }

        $activationTrusted = $this->validActivation($payload);
        $ownershipTrusted = $this->validOwnershipSection($payload);
        return [
            'activation' => $activationTrusted
                ? PluginActivationSnapshot::trusted($payload['plugins'])
                : PluginActivationSnapshot::untrusted(),
            'ownership' => $ownershipTrusted
                ? PluginOwnershipSnapshot::trusted($payload['ownership'])
                : PluginOwnershipSnapshot::untrusted(),
        ];
    }

    private function validEnvelope(mixed $payload, string $generation): bool
    {
        if (!is_array($payload)
            || ($payload['schema_version'] ?? null) !== PluginActivationCompiler::SCHEMA_VERSION
            || ($payload['generation'] ?? null) !== $generation) {
            return false;
        }
        $allowedKeys = ['schema_version', 'generation', 'plugins', 'ownership', 'activation_hash', 'ownership_hash', 'integrity_hash'];
        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !in_array($key, $allowedKeys, true)) {
                return false;
            }
        }
        return !isset($payload['integrity_hash'])
            || (is_string($payload['integrity_hash']) && preg_match('/^[a-f0-9]{64}$/', $payload['integrity_hash']) === 1);
    }

    private function validActivation(array $payload): bool
    {
        if (!is_array($payload['plugins'] ?? null)
            || !$this->validSectionHash($payload, 'activation_hash', 'plugins')) {
            return false;
        }
        foreach ($payload['plugins'] as $code => $plugin) {
            if (!is_string($code) || !$this->validPlugin($plugin, $code)) {
                return false;
            }
        }
        return true;
    }

    private function validOwnershipSection(array $payload): bool
    {
        if (!is_array($payload['ownership'] ?? null)
            || !$this->validSectionHash($payload, 'ownership_hash', 'ownership')) {
            return false;
        }
        foreach ($payload['ownership'] as $code => $ownership) {
            if (!is_string($code)
                || preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1
                || !$this->validOwnership($ownership)) {
                return false;
            }
        }
        return true;
    }

    private function validPlugin(mixed $plugin, string $code): bool
    {
        if (!is_array($plugin)
            || array_keys(array_diff_key($plugin, array_flip(['serverHooks', 'serverHookFiles']))) !== ['code', 'state', 'enabled', 'operation_token', 'version', 'package_hash', 'dependencies', 'applications']
            || $plugin['code'] !== $code
            || preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1
            || !is_string($plugin['state'])
            || !is_bool($plugin['enabled'])
            || (!is_null($plugin['operation_token']) && !is_string($plugin['operation_token']))
            || !is_string($plugin['version'])
            || !is_string($plugin['package_hash'])
            || !is_array($plugin['dependencies'])
            || !array_is_list($plugin['dependencies'])
            || !$this->validApplications($plugin['applications'])) {
            return false;
        }

        if (array_key_exists('serverHooks', $plugin) || array_key_exists('serverHookFiles', $plugin)) {
            if (!is_array($plugin['serverHooks'] ?? null) || !array_is_list($plugin['serverHooks']) || !is_array($plugin['serverHookFiles'] ?? null)) {
                return false;
            }
            foreach ($plugin['serverHooks'] as $hook) {
                if (!is_array($hook) || !is_string($hook['id'] ?? null) || !is_string($hook['point'] ?? null)
                    || !in_array($hook['type'] ?? null, ['action', 'filter'], true) || !is_string($hook['file'] ?? null)
                    || !is_string($hook['handler'] ?? null) || !is_int($hook['priority'] ?? 100)
                    || preg_match('/^hooks\/[A-Za-z0-9_.\/-]+\.php$/D', $hook['file']) !== 1
                    || !is_string($plugin['serverHookFiles'][$hook['file']] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/D', $plugin['serverHookFiles'][$hook['file']]) !== 1) {
                    return false;
                }
            }
        }
        foreach ($plugin['dependencies'] as $dependency) {
            if (!is_string($dependency) || preg_match('/^[a-z][a-z0-9]*$/', $dependency) !== 1) {
                return false;
            }
        }
        return true;
    }

    private function validOwnership(mixed $ownership): bool
    {
        return is_array($ownership)
            && array_keys($ownership) === ['applications']
            && $this->validApplications($ownership['applications']);
    }

    private function validApplications(mixed $applications): bool
    {
        return is_array($applications)
            && array_keys($applications) === ['app', 'admin']
            && is_bool($applications['app'])
            && is_bool($applications['admin']);
    }

    private function validSectionHash(array $payload, string $hashKey, string $sectionKey): bool
    {
        $hash = $payload[$hashKey] ?? null;
        if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            return false;
        }
        return hash_equals($hash, PluginActivationCompiler::integrityHash([
            'schema_version' => $payload['schema_version'],
            'generation' => $payload['generation'],
            $sectionKey => $payload[$sectionKey],
        ]));
    }

    /** @return array{activation: PluginActivationSnapshot, ownership: PluginOwnershipSnapshot} */
    private function untrustedBundle(): array
    {
        return [
            'activation' => PluginActivationSnapshot::untrusted(),
            'ownership' => PluginOwnershipSnapshot::untrusted(),
        ];
    }
}
