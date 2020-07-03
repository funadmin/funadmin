<?php
/**
 * lemobbs
 * ============================================================================
 * 版权所有 2018-2027 lemocms，并保留所有权利。
 * 网站地址: https://www.lemocms.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/7
 */

namespace addons\bbs;
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
        'name' => 'bbs',    // 插件标识唯一
        'title' => 'bbs社区插件',    // 插件名称
        'description' => 'thinkph6-lemobbs社区插件',    // 插件简介
        'status' => 1,    // 状态
        'author' => 'yuege',
        'require' => '0.1',
        'version' => '0.1',
        'website' => 'https://demo.lemocms.com/bbs'

    ];
    public $menu = [
        'is_nav'=>0,//1导航栏；0 非导航栏
        'menu'=>[ //菜单;
            'href' => 'admin/bbs',
            'title' => 'bbs管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'fa  fa-instagram',
            'menulist' => [
                [
                    'href' => 'bbs.bbs/index',
                    'title' => 'bbs文章',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'bbs.bbs/add', 'title' => '添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/edit', 'title' => '编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/delete', 'title' => '删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/state', 'title' => '状态', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],
                [
                    'href' => 'bbs.bbs/cate',
                    'title' => 'bbs文章分类',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa  fa-empire ',
                    'menulist' => [
                        ['href' => 'bbs.bbs/cateAdd', 'title' => '分类添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/cateEdit', 'title' => '分类编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/cateDel', 'title' => '分类删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbs/cateState', 'title' => '状态', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],
                [
                    'href' => 'bbs.bbsComment/index',
                    'title' => 'bbs评论',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'bbs.bbsComment/delete', 'title' => '评论删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsComment/state', 'title' => '评论状态', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],

                [
                    'href' => 'bbs.bbsAdv/index',
                    'title' => '广告管理',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'bbs.bbsAdv/add', 'title' => '添加广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/edit', 'title' => '编辑广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/state', 'title' => '广告状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/delete', 'title' => '删除广告', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],

                [
                    'href' => 'bbs.bbsAdv/pos',
                    'title' => '广告位',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'bbs.bbsAdv/posAdd', 'title' => '添加广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/posEdit', 'title' => '编辑广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/posState', 'title' => '广告位状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsAdv/posDel', 'title' => '删除广告位', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],


                [
                    'href' => 'bbs.bbsLink/index',
                    'title' => '友情链接',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'bbs.bbsLink/add', 'title' => '链接添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsLink/edit', 'title' => '链接编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsLink/delete', 'title' => '链接删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'bbs.bbsLink/state', 'title' => '链接状态', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],


                [
                    'href' => 'ucenter.sign/index',
                    'title' => '用户签到',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'ucenter.sign/index', 'title' => '签到状态', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'ucenter.sign/rule',
                    'title' => '用户签到规则',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-telegram',
                    'menulist' => [
                        ['href' => 'ucenter.sign/ruleAdd', 'title' => '规则添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'ucenter.sign/ruleEdit', 'title' => '规则编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'ucenter.sign/ruleDel', 'title' => '规则删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'ucenter.sign/ruleState', 'title' => '规则状态', 'status' => 1,
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