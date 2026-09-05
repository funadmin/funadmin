<?php


namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\Module as M;
use think\model\concern\SoftDelete;

class FieldVerify extends BaseModel {

    /**
     * 013 迁移后使用自增 id 主键。
     *
     * @var string
     */
    protected $pk = 'id';

    /**
     * @var bool
     */
    use SoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }



}
