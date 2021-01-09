<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\common\traits\Curd;
use app\common\model\Languages as LanguagesModel;
use think\App;

class Languages extends Backend {


    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new LanguagesModel();

    }

}
