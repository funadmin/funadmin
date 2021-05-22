<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
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
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="多语言")
 * Class Languages
 * @package app\backend\controller\sys
 */
class Languages extends Backend {


    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new LanguagesModel();
    }

}
