<?php

namespace app\common\model;

use app\common\model\concern\LaravelSoftDelete;

class DictItem extends BaseModel
{
    use LaravelSoftDelete;

    protected $name = 'dict_item';

    public function type()
    {
        return $this->belongsTo(DictType::class, 'type_id');
    }
}
