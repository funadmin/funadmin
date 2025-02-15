<?php


namespace app\common\model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Addon extends BaseModel {

    /**
     * @var bool
     */
    use SoftDelete;



    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
