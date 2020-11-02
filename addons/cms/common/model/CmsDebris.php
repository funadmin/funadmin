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
 * Date: 2019/9/2
 */

namespace addons\cms\common\model;

use app\common\model\BaseModel;
use addons\cms\common\model\CmsDebrisPos;
class CmsDebris extends BaseModel
{
    protected $name = 'addons_cms_debris';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

    public function cmsDebrisPos()
    {
        return $this->belongsTo('cmsDebrisPos', 'pid', 'id');

    }


}
