<?php

declare(strict_types=1);

namespace app\common\plugin\marketplace\dto;

final class CategoryDto
{
    public function __construct(public readonly int $id, public readonly string $name)
    {
    }
}
