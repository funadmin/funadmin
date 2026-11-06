<?php
declare(strict_types=1);
namespace app\cms\template;
final readonly class RenderContext
{
    public function __construct(public string $siteId, public string $locale, public array $page = []) {}
}
