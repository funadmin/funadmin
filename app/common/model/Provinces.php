<?php


namespace app\common\model;


use app\common\model\concern\LaravelSoftDelete;

class Provinces extends BaseModel {

    /**
     * @var bool
     */
    use LaravelSoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


}
