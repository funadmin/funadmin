<?php


namespace app\common\model;

use app\common\model\BaseModel;
use think\model\concern\SoftDelete;

class Attach extends BaseModel {

    /**
     * @var bool
     */
    use SoftDelete;


    
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}
