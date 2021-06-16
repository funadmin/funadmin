<?php
declare (strict_types = 1);

namespace app\backend\model;
use app\common\model\BaseModel;
use think\Model;

/**
 * @mixin \think\Model
 */
class TestCate extends BaseModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    
}
