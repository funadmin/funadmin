<?php

namespace app\common\model;

use think\model\concern\SoftDelete;

class DictItem extends BaseModel
{
    use SoftDelete;

    protected $name = 'dict_item';

    public function type()
    {
        return $this->belongsTo(DictType::class, 'type_id');
    }
}
