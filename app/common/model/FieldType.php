<?php


namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\Module as M;
use think\facade\Config;
use think\model\concern\SoftDelete;

class FieldType extends BaseModel {

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
