<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

/** 仅从 active generation 的 JSON 文件读取插件激活状态，不访问控制面。 */
class PluginActivationReader
{
    public function __construct(private readonly string $runtimePath)
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
            || array_keys($plugin) !== ['code', 'state', 'enabled', 'needs_reinstall', 'operation_token', 'version', 'package_hash', 'dependencies', 'applications']
            || $plugin['code'] !== $code
            || preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1
            || !is_string($plugin['state'])
            || !is_bool($plugin['enabled'])
            || !is_bool($plugin['needs_reinstall'])
            || (!is_null($plugin['operation_token']) && !is_string($plugin['operation_token']))
            || !is_string($plugin['version'])
            || !is_string($plugin['package_hash'])
            || !is_array($plugin['dependencies'])
            || !array_is_list($plugin['dependencies'])
            || !$this->validApplications($plugin['applications'])) {
            return false;
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
            && array_keys($applications) === ['app', 'console']
            && is_bool($applications['app'])
            && is_bool($applications['console']);
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
