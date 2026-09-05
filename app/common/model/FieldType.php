<?php


namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\concern\LaravelSoftDelete;

class FieldType extends BaseModel {

    /**
     * @var bool
     */
    use LaravelSoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }



}
