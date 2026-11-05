<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class DictType extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'dict_type';

    public function items()
    {
        return $this->hasMany(DictItem::class, 'type_id');
    }
}
