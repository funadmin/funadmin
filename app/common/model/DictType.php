<?php

namespace app\common\model;

use think\model\concern\SoftDelete;

class DictType extends BaseModel
{
    use SoftDelete;

    protected $name = 'dict_type';

    public function items()
    {
        return $this->hasMany(DictItem::class, 'type_id');
    }
}
