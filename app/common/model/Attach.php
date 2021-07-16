<?php


namespace app\common\model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Attach extends BaseModel {


    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}
