<?php


namespace app\common\model;


use think\model\concern\SoftDelete;

class Provinces extends BaseModel {

    /**
     * @var bool
     */
    use SoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
