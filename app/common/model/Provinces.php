<?php


namespace app\common\model;


use think\model\concern\SoftDelete;

class Provinces extends BaseModel {

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
