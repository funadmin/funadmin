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
            throw new InvalidArgumentException('business managed 三方规划暂不支持插件目标');
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
    public function renderManagedBundle(CrudDefinition $definition): array
    {
        $this->validator->validate($definition, $this->projectRoot);
        $target = (array) $definition->get('target', ['type' => 'core']);
        if (($target['type'] ?? 'core') === 'plugin') {
            throw new InvalidArgumentException('business managed 渲染暂不支持插件目标');
        }
        return $this->renderFiles($definition);
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

    private function renderFiles(CrudDefinition $definition): array
    {
        $target = (array) $definition->get('target', ['type' => 'core']);
        if (($target['type'] ?? 'core') === 'plugin') {
            return (new PluginCrudTarget($this->projectRoot))->files($definition, $this->renderer);
        }
        $paths = $definition->get('generationTargets', []);
        $templates = $definition->get('templates', []);
        $context = $this->context($definition);
        $files = [];
        foreach ($paths as $type => $path) {
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
