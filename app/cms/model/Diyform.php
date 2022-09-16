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

namespace app\cms\model;

use app\common\model\BaseModel;
use think\facade\Db;
use think\model\concern\SoftDelete;

class Diyform extends BaseModel
{
    use SoftDelete;
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
    protected $name = 'addons_cms_diyform';

    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


    public static function onAfterInsert($model){
        $prefix = self::get_table_prefix();
        $sql = <<<EOF
            CREATE TABLE `{$prefix}{$model->tablename}` (
              `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
              `member_id` int(10) DEFAULT 0 COMMENT '会员ID',
              `admin_id` int(10) DEFAULT 0 COMMENT '管理员ID',
              `status` tinyint(1) DEFAULT 1 COMMENT '状态',
              `create_time` int(11) DEFAULT NULL COMMENT '添加时间',
              `update_time` int(11) DEFAULT 0 COMMENT '更新时间',
              `delete_time` int(11) DEFAULT 0 COMMENT '伪删除时间',
            PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=COMPACT COMMENT='{$model->name}';
EOF;
        Db::execute($sql);

    }


}
