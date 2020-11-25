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
 * Date: 2019/11/7
 */

namespace addons\bbs;
// 注意命名空间规范
use fun\Addons;

/**
 * 插件测试
 *
 */
class Plugin extends Addons    // 需继承fun\Addons类
{
    // 该插件的基础信息
    public $info = [
        'name' => 'bbs',    // 插件标识唯一
        'title' => '知识付费社区插件',    // 插件名称
        'description' => '知识付费社区插件-funadmin',    // 插件简介
        'status' => 1,    // 状态
        'author' => 'yuege',
        'require' => '1.0',
        'version' => '1.0',
        'website' => 'https://demo.funadmin.com/bbs'

    ];
    public $menu = [
        'is_nav'=>1,//1导航栏；0 非导航栏
        'menu'=>[ //菜单;
            'href' => 'bbs',
            'title' => 'bbs管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'fa  fa-instagram',
            'menulist' => [
                [
                    'href' => 'addons/bbs/backend/index/index',
                    'title' => 'bbs文章',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'addons/bbs/backend/index/index', 'title' => 'list', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/index/add', 'title' => 'add', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/index/edit', 'title' => 'edit', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/index/delete', 'title' => 'delete', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/index/destroy', 'title' => 'destroy', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/index/modify', 'title' => 'modify', 'status' => 1, 'menu_status' => 0,],

                    ]
                ],
                [
                    'href' => 'addons/bbs/backend/category',
                    'title' => 'category',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa  fa-empire ',
                    'menulist' => [
                        ['href' => 'addons/bbs/backend/category/index', 'title' => 'list', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/category/add', 'title' => 'add', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/category/edit', 'title' => 'edit', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/category/delete', 'title' => 'delete', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/category/destroy', 'title' => 'destroy', 'status' => 1, 'menu_status' => 0,],
                        ['href' => 'addons/bbs/backend/category/modify', 'title' => '状态', 'status' => 1, 'menu_status' => 0,],


                    ]
                ],
                [
                    'href' => 'admin/bbs.bbsComment/index',
                    'title' => 'bbs评论',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'admin/bbs.bbsComment/delete', 'title' => '评论delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsComment/state', 'title' => '评论状态', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],

                [
                    'href' => 'admin/bbs.bbsAdv/index',
                    'title' => '广告管理',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'admin/bbs.bbsAdv/add', 'title' => 'add广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/edit', 'title' => 'edit广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/state', 'title' => '广告状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/delete', 'title' => 'delete广告', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],

                [
                    'href' => 'admin/bbs.bbsAdv/pos',
                    'title' => '广告位',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'admin/bbs.bbsAdv/posAdd', 'title' => 'add广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/posEdit', 'title' => 'edit广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/posState', 'title' => '广告位状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsAdv/posDel', 'title' => 'delete广告位', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],


                [
                    'href' => 'admin/bbs.bbsLink/index',
                    'title' => '友情链接',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'admin/bbs.bbsLink/add', 'title' => '链接add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsLink/edit', 'title' => '链接edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsLink/delete', 'title' => '链接delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/bbs.bbsLink/state', 'title' => '链接状态', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],


                [
                    'href' => 'admin/ucenter.sign/index',
                    'title' => '用户签到',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'admin/ucenter.sign/index', 'title' => '签到状态', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'admin/ucenter.sign/rule',
                    'title' => '用户签到规则',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'admin/ucenter.sign/ruleAdd', 'title' => '规则add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/ucenter.sign/ruleEdit', 'title' => '规则edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/ucenter.sign/ruleDel', 'title' => '规则delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/ucenter.sign/ruleState', 'title' => '规则状态', 'status' => 1,
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