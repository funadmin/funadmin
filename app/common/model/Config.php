<?php

namespace app\common\model;
use think\model\concern\SoftDelete;

class Config extends BaseModel
{
    /**
     * @var bool
     */
    use SoftDelete;


    
    protected $deleteTime = 'delete_time';


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}