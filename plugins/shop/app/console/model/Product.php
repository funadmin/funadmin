<?php

declare(strict_types=1);

namespace app\console\model\plugin\shop;

use app\console\model\BackendModel;
use app\common\model\concern\LaravelSoftDelete;

final class Product extends BackendModel
{
    use LaravelSoftDelete;

    protected $name = 'shop_product';
    protected $pk = 'id';
    protected $type = array (
  'id' => 'integer',
  'price' => 'string',
  'status' => 'integer',
  'created_at' => 'datetime',
  'updated_at' => 'datetime',
  'deleted_at' => 'datetime',
);


}

