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
namespace addons\cms\backend\controller;

use app\common\controller\AddonsBackend;
use app\common\traits\Curd;
use think\App;
use addons\cms\common\model\CmsDebris as DebrisModel;
class CmsDebrisPos extends AddonsBackend {
    use Curd;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new DebrisModel();
    }

   }