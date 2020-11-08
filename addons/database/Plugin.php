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
 * Date: 2019/11/7
 */

namespace addons\database;
// 注意命名空间规范
use fun\Addons;

/**
 * 插件测试
 *
 */
class Plugin extends Addons    // 需继承fun\Addons类
{
    // 该插件的基础信息
    public $menu = [
        'is_nav'=>1,//1导航栏；0 非导航栏
        'menu'=> [
            'href' => 'database',
            'title' => '数据库',
            'status' => 1,
            'auth_verify' => 1,
            'menu_status' => 1,
            'icon' => 'fa fa-database',
            'menulist' => [
                [
                    'href' => 'addons/database/backend/index/index',
                    'title' => '数据列表',
                    'target'=>'_self',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'addons/database/backend/index//optimize', 'title' => '数据优化', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/database/backend/index/repair', 'title' => '数据修复', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/database/backend/index/backup', 'title' => '数据备份', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],
                [
                    'href' => 'addons/database/backend/index/restore',
                    'title' => '备份列表',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'addons/database/backend/index/import', 'title' => '导入数据', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/database/backend/index/downFile', 'title' => '下载数据', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/database/backend/index/delSqlFiles', 'title' => '删除数据', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],


            ]
        ]
    ];

    /**
     * 插件安装方法
     * @return bool
     */
    public function install()
    {

        return true;
    }

    /**
     * 插件卸载方法
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件使用方法
     * @return bool
     */
    public function enabled()
    {

        return true;
    }

    /**
     * 插件禁用方法
     * @return bool
     */
    public function disabled()
    {
        return true;
    }


}