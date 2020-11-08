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
        'is_nav'=>1,//1导航栏；0 非导航栏
        'menu'=>
            [
            'href' => 'cms',
            'title' => 'cms管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'layui-icon layui-icon-component',
            'menulist' => [
                [
                    'href' => 'addons/cms/backend/cmsCategory',
                    'title' => 'Category',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-template-1',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsCategory/index', 'title' => '栏目', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategory/add', 'title' => '添加分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategory/edit', 'title' => '编辑分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategory/delete', 'title' => '删除分类', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategory/modify', 'title' => '分类状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategory/flashCache', 'title' => '清除缓存', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/cms/backend/cmsCategorylist',
                    'title' => 'Categorylist',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-template-1',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsCategorylist/index', 'title' => '栏目内容', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategorylist/add', 'title' => '添加栏目信息', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategorylist/delete', 'title' => '添加栏目信息', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategorylist/board', 'title' => '栏目面板', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsCategorylist/modify', 'title' => '栏目内容状态', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/cms/backend/cmsModule',
                    'title' => 'Module',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon  layui-icon-template-1',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsModule/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/field', 'title' => 'field', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/fieldAdd', 'title' => 'fieldadd', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/fieldEdit', 'title' => 'fieldedit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/fielddelete', 'title' => 'fielddelete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsModule/fieldmodify', 'title' => 'fieldmodify', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/cms/backend/cmsLink',
                    'title' => 'Link',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-unlink',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsLink/index', 'title' => 'List', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsLink/add', 'title' => 'Add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsLink/edit', 'title' => 'Edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsLink/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsLink/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],
                
                [
                    'href' => 'addons/cms/backend/cmsAdv',
                    'title' => 'Adv',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-component',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsAdv/index', 'title' => 'List', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdv/add', 'title' => '添加广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdv/edit', 'title' => '编辑广告', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdv/modify', 'title' => '广告状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdv/delete', 'title' => '删除广告', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],
                
                [
                    'href' => 'addons/cms/backend/cmsPos',
                    'title' => 'Advpos',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-unlink
',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsAdvPos/index', 'title' => 'List', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdvPos/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdvPos/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdvPos/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsAdvPos/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],



                [
                    'href' => 'addons/cms/backend/cmsDebris',
                    'title' => 'Debris',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon-list',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsDebris/index', 'title' => 'List', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebris/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebris/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebris/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebris/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],

                [
                    'href' => 'addons/cms/backend/cmsDebrisPos',
                    'title' => 'DebrisPosition',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-location',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsDebrisPos/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebrisPos/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebrisPos/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebrisPos/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsDebrisPos/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],

                [
                    'href' => 'addons/cms/backend/cmsTags',
                    'title' => 'Tags',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-face-smile',
                    'menulist' => [
                        ['href' => 'addons/cms/backend/cmsTags/index', 'title' => 'List', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsTags/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsTags/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms/backend/cmsTags/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],

                [
                    'href' => 'addons/cms.cmsDiyform',
                    'title' => 'Diyform',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'layui-icon layui-icon-form',
                    'menulist' => [
                        ['href' => 'addons/cms.cmsDiyform/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/datalist', 'title' => 'datalist', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/datadel', 'title' => 'datadel', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/field', 'title' => 'field', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/fieldadd', 'title' => 'fieldadd', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/cms.cmsDiyform/fielddel', 'title' => 'fielddel', 'status' => 1,
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