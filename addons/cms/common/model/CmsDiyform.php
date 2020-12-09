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
use think\facade\Db;

class CmsDiyform extends BaseModel
{
    protected $name = 'addons_cms_diyform';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


    public function onAfterInsert($model){
        $prefix = $this->get_table_prefix();
        $sql = <<<EOF
            "CREATE TABLE `{$prefix}{$model->tablename}` (
              `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `uid` int(10) DEFAULT NULL COMMENT '会员ID',
              `status` tinyint(1) DEFAULT 1 COMMENT '状态',
              `create_time` int(10) DEFAULT NULL COMMENT '添加时间',
              `update_time` int(10) DEFAULT NULL COMMENT '更新时间',
              `delete_time` int(10) DEFAULT NULL COMMENT '伪删除时间',
            PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='{$model->name}'";
EOF;
        Db::execute($sql);

    }

}
