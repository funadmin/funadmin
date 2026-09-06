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
        return (new GenerationPlanner($this->projectRoot, $this->tokens))->plan($definition, $this->renderFiles($definition));
    }

    public function generate(
        CrudDefinition $definition,
        string $confirmToken,
        array $allowOverwrite = [],
        string $operator = 'unknown'
    ): array {
        $plan = $this->plan($definition);
        $write = (new AtomicWriter($this->projectRoot, null, $this->tokens))->write($plan, $confirmToken, $allowOverwrite);
        return [
            'plan' => $plan,
            'write' => $write,
            'manifest' => GenerationManifest::create(
                $definition,
                self::TEMPLATE_VERSION,
                $plan,
                $operator,
                $write['status']
            )->toArray(),
        ];
    }

    private function renderFiles(CrudDefinition $definition): array
    {
        $paths = $definition->get('paths', []);
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
