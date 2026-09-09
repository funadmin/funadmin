<?php

declare(strict_types=1);

namespace app\console\model;

use app\common\model\concern\LaravelSoftDelete;

final class Test extends BackendModel
{
    use LaravelSoftDelete;

    protected string $name = 'test';
    protected string $pk = 'id';
    protected array $type = array (
  'id' => 'integer',
  'field_3' => 'integer',
  'field_4' => 'integer',
  'field_6' => 'integer',
  'field_8' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);


}

