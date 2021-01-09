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
 * Date: 2019/9/4
 */

namespace addons\wechat\backend\model;

use app\common\model\BaseModel;

class WechatQrcode extends BaseModel
{
    protected $name = 'addons_wechat_qrcode';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }

}