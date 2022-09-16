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


use think\App;
use app\cms\model\DebrisPos as DebrisPosModel;
class DebrisPos extends CmsBackend {

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new DebrisPosModel();
    }

   }