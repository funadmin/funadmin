<?php

declare(strict_types=1);

namespace app\shop\model;

use think\Model;
use app\common\model\concern\LaravelSoftDelete;

final class Product extends Model
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

