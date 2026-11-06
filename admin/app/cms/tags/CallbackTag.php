<?php
declare(strict_types=1);
namespace app\cms\tags;
use app\cms\template\TagHandler;
use app\cms\template\RenderContext;
final class CallbackTag implements TagHandler
{
    private \Closure $callback;
    public function __construct(private array $parameters, callable $callback, private bool $backendConfigurable = true) { $this->callback=\Closure::fromCallable($callback); }
    public function schema(): array { return $this->parameters; }
    public function configurable(): bool { return $this->backendConfigurable; }
    public function resolve(array $parameters, RenderContext $context): mixed { return ($this->callback)($parameters,$context); }
}
