<?php
declare (strict_types = 1);

namespace {{$modelNamespace}};
use app\common\model\BaseModel;
use think\Model;

/**
 * @mixin \think\Model
 */
class {{$modelName}} extends BaseModel
{
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    {{$joinTpl}}
}
