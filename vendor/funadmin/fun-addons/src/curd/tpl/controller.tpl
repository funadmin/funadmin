<?php
declare (strict_types = 1);

namespace {{$controllerNamespace}};

use think\Request;
use think\App;
use think\facade\View;
use {{$modelNamespace}}\{{$modelName}} as {{$modelName}}Model;
use app\common\annotation\NodeAnnotation;
use app\common\annotation\ControllerAnnotation;

/**
 * @ControllerAnnotation (title="{{$tableComment}}")
 */
class {{$controllerName}} extends {{$baseController}}
{
    protected $pageSize = {{$limit}};
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new {{$modelName}}Model();
{{$assign}}

    }

    {{$indexTpl}}

}

