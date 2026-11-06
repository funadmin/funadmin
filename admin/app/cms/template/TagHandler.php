<?php
declare(strict_types=1);
namespace app\cms\template;
interface TagHandler
{
    public function schema(): array;
    public function configurable(): bool;
    public function resolve(array $parameters, RenderContext $context): mixed;
}
