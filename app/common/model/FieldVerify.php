<?php


namespace app\common\model;

use app\common\model\BaseModel;
use app\common\model\Module as M;
use app\common\model\concern\LaravelSoftDelete;

class FieldVerify extends BaseModel {

    /**
     * 验证规则编码是现有字符串主键。
     *
     * @var string
     */
    protected $pk = 'verify';

    protected $keyType = 'string';

    /**
     * @var bool
     */
    use LaravelSoftDelete;


    

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }



}
