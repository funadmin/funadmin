<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * CRUD 生成用例：验证定义、渲染模板、规划并按显式确认写入。
 */
final class CrudGenerator
{
    public const TEMPLATE_VERSION = 'm5-production-v1';

    private readonly DefinitionValidator $validator;
    private readonly TemplateRenderer $renderer;
    private readonly ConfirmationToken $tokens;

    public function __construct(
        private readonly string $projectRoot,
        ?string $templateRoot = null,
        ?ConfirmationToken $tokens = null
    ) {
        $this->validator = new DefinitionValidator();
        $this->renderer = new TemplateRenderer($templateRoot ?? __DIR__ . '/templates/v1');
        $this->tokens = $tokens ?? new ConfirmationToken($projectRoot);
    }

    public function plan(CrudDefinition $definition): array
    {
        $this->validator->validate($definition, $this->projectRoot);
        $target = (array) $definition->get('target', ['type' => 'core']);
        $pluginTarget = ($target['type'] ?? 'core') === 'plugin';
        $preconditions = $pluginTarget
            ? [(new PluginCrudTarget($this->projectRoot))->migrationPrecondition($definition)]
            : [];
        $operations = [];
        if ($pluginTarget) {
            $manifest = 'plugins/' . $target['plugin'] . '/plugin.json';
            $operations[$manifest] = ['type' => 'manifest-merge-cas', 'plugin' => (string) $target['plugin']];
        }
        return (new GenerationPlanner($this->projectRoot, $this->tokens))->plan(
            $definition,
            $this->renderFiles($definition),
            $preconditions,
            $operations
        );
    }

    /**
     * business managed 正式生成只读规划；调用方必须显式传入可靠 baseline。
     */
    public function planManaged(CrudDefinition $definition, array $baselines): array
    {
        $this->validator->validate($definition, $this->projectRoot);
        $target = (array) $definition->get('target', ['type' => 'core']);
        if (($target['type'] ?? 'core') === 'plugin') {
            return $this->planManagedPlugin($definition, $baselines);
        }
        $artifactByPath = [];
        foreach ((array) $definition->get('generationTargets', []) as $artifactType => $path) {
            if (is_string($path)) {
                $artifactByPath[str_replace('\\', '/', $path)] = (string) $artifactType;
            }
        }
        foreach ($baselines as &$baseline) {
            if (is_array($baseline) && is_string($baseline['path'] ?? null)) {
                $path = str_replace('\\', '/', $baseline['path']);
                if (isset($artifactByPath[$path])) {
                    $baseline['artifactType'] = $artifactByPath[$path];
                } elseif (($baseline['artifactType'] ?? '') === 'migration') {
                    unset($baseline['artifactType']);
                }
            }
        }
        unset($baseline);
        return (new GenerationPlanner($this->projectRoot, $this->tokens))->planManaged(
            $definition,
            $this->renderFiles($definition),
            $baselines
        );
    }

    /**
     * 返回仅供受信应用服务组装事务 bundle 的确定性 Remote 内容。
     * 此入口不规划、不签发 token，也不写入文件。
     */
    public function renderManagedBundle(CrudDefinition $definition, array $baselines = []): array
    {
        $this->validator->validate($definition, $this->projectRoot);
        $target = (array) $definition->get('target', ['type' => 'core']);
        if (($target['type'] ?? 'core') === 'plugin' && ($target['scope'] ?? '') !== 'console') {
            throw new InvalidArgumentException('managed 插件仅支持 console');
        }
        return $this->renderFiles($definition, $this->manifestBase($definition, $baselines), true);
    }

    public function generate(
        CrudDefinition $definition,
        string $confirmToken,
        array $allowOverwrite = [],
        string $operator = 'unknown'
    ): array {
        return $this->generatePlanned($definition, $this->plan($definition), $confirmToken, $allowOverwrite, $operator);
    }

    /** 使用预检时产生的同一计划写入，避免 DDL 后重新规划产生竞态。 */
    public function generatePlanned(
        CrudDefinition $definition,
        array $plan,
        string $confirmToken,
        array $allowOverwrite = [],
        string $operator = 'unknown'
    ): array {
        $this->validator->validate($definition, $this->projectRoot);
        $startedAt = gmdate(DATE_ATOM);
        if (!hash_equals($definition->hash(), (string) ($plan['definitionHash'] ?? ''))) {
            throw new InvalidArgumentException('预检计划与 Definition 不一致');
        }
        try {
            $write = (new AtomicWriter($this->projectRoot, null, $this->tokens))->write($plan, $confirmToken, $allowOverwrite);
            $manifest = GenerationManifest::create(
                $definition,
                self::TEMPLATE_VERSION,
                $plan,
                $operator,
                $write['status'],
                ['validationResult' => ['valid' => true]],
                $startedAt,
                gmdate(DATE_ATOM)
            )->toArray();
            return ['plan' => $plan, 'write' => $write, 'manifest' => $manifest];
        } catch (\Throwable $exception) {
            $manifest = GenerationManifest::create(
                $definition,
                self::TEMPLATE_VERSION,
                $plan,
                $operator,
                'failed',
                ['validationResult' => ['valid' => true]],
                $startedAt,
                gmdate(DATE_ATOM),
                ['message' => $exception->getMessage()]
            )->toArray();
            throw new GenerationFailedException($exception->getMessage(), $manifest, $exception);
        }
    }

    private function planManagedPlugin(CrudDefinition $definition, array $baselines): array
    {
        $target = $definition->get('target');
        if (($target['scope'] ?? '') !== 'console') throw new InvalidArgumentException('managed 插件仅支持 console');
        $precondition = (new PluginCrudTarget($this->projectRoot))->migrationPrecondition($definition);
        $remote = $this->renderManagedBundle($definition, $baselines);
        $manifest = 'plugins/' . $target['plugin'] . '/plugin.json';
        $inputs = [];
        $byPath = [];
        foreach ($baselines as $baseline) {
            $path = (string) ($baseline['path'] ?? '');
            if (!array_key_exists($path, $remote) || str_ends_with($path, '.sql')) {
                throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
            }
            $byPath[$path] = $baseline;
        }
        foreach ($remote as $path => $content) {
            $artifact = $path === $manifest ? 'manifest' : (str_ends_with($path, '.sql') ? 'migration' : 'source');
            $input = $byPath[$path] ?? ['path' => $path];
            if ($artifact === 'manifest') {
                // ManifestMerger 已按受管键检查冲突；提交仍执行整文件 Local CAS。
                $input = ['path' => $path, 'baseContent' => (string) file_get_contents(PathGuard::resolve($this->projectRoot, $path, '插件目录'))];
            }
            if ($artifact === 'migration' && is_file(PathGuard::resolve($this->projectRoot, $path, '插件目录'))) {
                $local = (string) file_get_contents(PathGuard::resolve($this->projectRoot, $path, '插件目录'));
                if ($local !== $content) throw new InvalidArgumentException('插件 migration 不可覆盖');
                $input['baseContent'] = $local;
                $input['artifactType'] = 'immutableMigration';
            } else {
                $input['artifactType'] = $artifact;
            }
            $input['remoteContent'] = $content;
            $inputs[] = $input;
        }
        $plan = (new ThreeWayMergePlanner($this->projectRoot))->plan($inputs);
        $manifestPlan = (new ManifestMerger($this->projectRoot))->plan($definition, $this->manifestBase($definition, $baselines));
        foreach ($plan['files'] as &$file) {
            if ($file['artifactType'] === 'immutableMigration') $file['artifactType'] = 'migration';
            if ($file['path'] === $manifest && $manifestPlan['conflictPaths'] !== []) {
                $file = array_replace($file, array_intersect_key($manifestPlan, array_flip(['conflictPaths', 'baseContent', 'localContent', 'remoteContent'])),
                    ['status' => 'conflict', 'content' => null, 'mergedHash' => null, 'nextBaseHash' => null]);
                $plan['blocked'] = true;
            }
        }
        unset($file);
        $plan['definitionHash'] = $definition->hash();
        $plan['preconditions'] = [$precondition];
        $plan['allowedPaths'] = array_keys($remote);
        $plan['planDigest'] = hash('sha256', CrudDefinition::canonicalJson($plan));
        return $plan;
    }

    private function manifestBase(CrudDefinition $definition, array $baselines): array
    {
        if (($definition->get('target')['type'] ?? 'core') !== 'plugin') return [];
        $path = 'plugins/' . $definition->get('target')['plugin'] . '/plugin.json';
        foreach ($baselines as $baseline) {
            if (($baseline['path'] ?? '') !== $path) continue;
            if (($baseline['artifactType'] ?? '') !== 'manifest') throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
            $base = json_decode((string) ($baseline['baseContent'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($base)) throw new InvalidArgumentException('Manifest 模块基线无效');
            return $base;
        }
        return [];
    }

    private function renderFiles(CrudDefinition $definition, array $manifestBase = [], bool $managed = false): array
    {
        $target = (array) $definition->get('target', ['type' => 'core']);
        if (($target['type'] ?? 'core') === 'plugin') {
            return (new PluginCrudTarget($this->projectRoot))->files($definition, $this->renderer, $manifestBase, $managed);
        }
        $paths = $definition->get('generationTargets', []);
        $templates = $definition->get('templates', []);
        $context = $this->context($definition);
        $files = [];
        foreach ($paths as $type => $path) {
            if ($type === 'migration' && $definition->isAdopted()) continue;
            if (!isset($templates[$type])) {
                throw new InvalidArgumentException('目标缺少模板：' . $type);
            }
            $files[$path] = $this->renderer->render($templates[$type], $context);
        }
        return $files;
    }

    private function context(CrudDefinition $definition): array
    {
        return ProductionTemplateContext::build($definition);
    }
}
