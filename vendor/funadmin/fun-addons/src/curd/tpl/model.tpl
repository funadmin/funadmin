<?php
declare (strict_types = 1);

namespace {{$modelNamespace}};
use app\common\model\BaseModel;
use think\Model;
use think\model\concern\SoftDelete;
/**
 * @mixin \think\Model
 */
class {{$modelName}} extends BaseModel
{
    {{$softDelete}}

    {{$connection}}

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }
    {{$joinTpl}}

    {{$attrTpl}}
}
