<?php
/**
 * lemocms
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/7
 */

namespace addons\database;
// 注意命名空间规范
use think\Addons;

/**
 * 插件测试
 *
 */
class Plugin extends Addons    // 需继承think\Addons类
{
    // 该插件的基础信息
    public $info = [
        'name' => 'database',    // 插件标识唯一
        'title' => '数据库管理',    // 插件名称
        'description' => '数据库插件-lemocms数据库管理插件',    // 插件简介
        'status' => 1,    // 状态
        'author' => 'yuege',
        'require' => '0.1',
        'version' => '0.1',
        'website' => ''

    ];
    public $menu = [
        'is_nav'=>0,//1导航栏；0 非导航栏
        'menu'=> [
            'href' => 'database',
            'title' => '数据库管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'fa fa-database',
            'menulist' => [
                [
                    'href' => 'sys.database/index',
                    'title' => '数据列表',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'sys.database/optimize', 'title' => '数据优化', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'sys.database/repair', 'title' => '数据修复', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'sys.database/backup', 'title' => '数据备份', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],
                [
                    'href' => 'sys.database/restore',
                    'title' => '备份列表',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'sys.database/import', 'title' => '导入数据', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'sys.database/downFile', 'title' => '下载数据', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'sys.database/delSqlFiles', 'title' => '删除数据', 'status' => 1,
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


    /**
     * 实现的testhook钩子方法
     * @return mixed
     */
    public function testhook($param)
    {
        // 调用钩子时候的参数信息
        dump($param);
        // 当前插件的配置信息，配置信息存在当前目录的config.php文件中，见下方
        dump($this->getInfo());
        dump($this->getConfig(true));
        // 可以返回模板，模板文件默认读取的为插件目录中的文件。模板名不能为空！
        return $this->fetch('info');
    }

}