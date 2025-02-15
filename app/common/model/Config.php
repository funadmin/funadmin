<?php

namespace app\common\model;
use think\model\concern\SoftDelete;

class Config extends BaseModel
{
    /**
     * @var bool
     */
    use SoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}