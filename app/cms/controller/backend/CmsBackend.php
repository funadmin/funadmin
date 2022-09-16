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
 * Date: 2019/8/2
 */

namespace app\cms\controller\backend;

use app\common\controller\AddonsBackend;
use app\cms\model\CmsAdvPosition;

use app\common\controller\Backend;
use think\App;

class  CmsBackend extends Backend{

    protected $layout = '../../backend/view/layout/main';
    public function __construct(App $app) {
        parent::__construct($app);
    }

}