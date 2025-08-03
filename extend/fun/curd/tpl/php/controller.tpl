<?php
declare (strict_types = 1);

namespace {%namespace%};

use think\Request;
use think\App;
use think\facade\View;
use {%namespaceModel%}\{%modelName%} as {%modelName%}Model;
use app\common\annotation\NodeAnnotation;
use app\common\annotation\ControllerAnnotation;
use app\common\controller\Backend;
/**
 * @ControllerAnnotation (title="{%tableComment%}")
 */
class {%name%} extends Backend
{
    protected $pageSize = {%limit%};
    protected $layout = '{%layout%}';

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new {%modelName%}Model();
{%assignList%}

    }

    {%indexMethod%}

    {%recycleMethod%}

}

