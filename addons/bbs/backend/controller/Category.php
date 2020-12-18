<?php

/**
 * funadmin
 * ============================================================================
 * 版权所有 2018-2027 funadmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/26
 */

namespace addons\bbs\backend\controller;

use addons\bbs\common\model\BbsCategory;
use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use fun\helper\TreeHelper;
use think\App;
use think\facade\Db;

class Category extends AddonsBackend
{
    use Curd;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new BbsCategory();
    }
}
