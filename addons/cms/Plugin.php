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

namespace addons\cms;
// 注意命名空间规范
use fun\Addons;

/**
 * 插件测试
 *
 */
class Plugin extends Addons    // 需继承fun\Addon类
{

    public $menu = [
        'is_nav'=>0,//1导航栏；0 非导航栏
        'menu'=>
            [
            'href' => 'addons/cms',
            'title' => 'cms管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'fa fa-telegram',
            'menulist' => [
                [
                    'href' => 'addons/cms.cmsCategory/index',
                    'title' => '栏目分类',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsCategory/add', 'title' => '添加分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/edit', 'title' => '编辑分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/delete', 'title' => '删除分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/state', 'title' => '分类状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/content', 'title' => '栏目内容', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/addinfo', 'title' => '添加内容', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/contentDel', 'title' => '内容删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/flashCache', 'title' => '清除缓存', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'admin/cms.cmsCategory/list',
                    'title' => '栏目列表',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsCategory/content', 'title' => '栏目内容', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/addinfo', 'title' => '添加栏目信息', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/board', 'title' => '栏目面板', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/contentState', 'title' => '栏目内容状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsCategory/flashCache', 'title' => '刷新缓存', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'admin/cms.cmsModule/index',
                    'title' => '模型管理',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-bandcamp',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsModule/add', 'title' => '添加模型', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/edit', 'title' => '编辑模型', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/state', 'title' => '模型状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/delete', 'title' => '删除模型', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/field', 'title' => '字段列表', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/fieldAdd', 'title' => '字段添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/fieldEdit', 'title' => '字段编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/fieldDel', 'title' => '字段删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/fieldState', 'title' => '字段状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsModule/fieldSort', 'title' => '字段排序', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],
                [
                    'href' => 'admin/cms.cmsLink/index',
                    'title' => '友情链接',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-instagram',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsLink/add', 'title' => '添加友情', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsLink/edit', 'title' => '编辑友情', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsLink/state', 'title' => '友情状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsLink/delete', 'title' => '删除友情', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],
                
                [
                    'href' => 'admin/cms.cmsAdv/index',
                    'title' => '广告管理',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsAdv/add', 'title' => '添加广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/edit', 'title' => '编辑广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/state', 'title' => '广告状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/delete', 'title' => '删除广告', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],
                
                [
                    'href' => 'admin/cms.cmsAdv/pos',
                    'title' => '广告位',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsAdv/posAdd', 'title' => '添加广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/posEdit', 'title' => '编辑广告位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/posState', 'title' => '广告位状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsAdv/posDel', 'title' => '删除广告位', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],



                [
                    'href' => 'admin/cms.cmsDebris/index',
                    'title' => '碎片管理',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsDebris/add', 'title' => '添加碎片', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/edit', 'title' => '编辑碎片', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/state', 'title' => '碎片状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/delete', 'title' => '删除碎片', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],

                [
                    'href' => 'admin/cms.cmsDebris/pos',
                    'title' => '碎片位',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsDebris/posAdd', 'title' => '添加碎片位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/posEdit', 'title' => '编辑碎片位', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/posState', 'title' => '碎片位状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsDebris/posDel', 'title' => '删除碎片位', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],

                [
                    'href' => 'admin/cms.cmsTags/index',
                    'title' => '标签',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'admin/cms.cmsTags/index', 'title' => '标签', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsTags/add', 'title' => '添加标签', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsTags/edit', 'title' => '编辑标签', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'admin/cms.cmsTags/delete', 'title' => '删除标签', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],

//                [
//                    'href' => 'admin/cms.cmsDiyform/index',
//                    'title' => '自定义表单',
//                    'status' => 1,
//                    'menu_status' => 1,
//                    'icon' => 'fa fa-check-square',
//                    'menulist' => [
//                        ['href' => 'admin/cms.cmsDiyform/index', 'title' => '表单', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/add', 'title' => '添加表单', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/edit', 'title' => '编辑表单', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/delete', 'title' => '删除表单', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/state', 'title' => '表单状态', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/datalist', 'title' => '数据列表', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/datadel', 'title' => '数据删除', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/field', 'title' => '字段列表', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/fieldadd', 'title' => '字段添加', 'status' => 1,
//                            'menu_status' => 0,],
//                        ['href' => 'admin/cms.cmsDiyform/fielddel', 'title' => '字段删除', 'status' => 1,
//                            'menu_status' => 0,],
//
//
//                    ]
//                ],




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
//        dump($param);
//         当前插件的配置信息，配置信息存在当前目录的config.php文件中，见下方
//        dump($this->getInfo());
//        dump($this->getConfig(true));
        // 可以返回模板，模板文件默认读取的为插件目录中的文件。模板名不能为空！
        return $this->fetch('info');
    }

}