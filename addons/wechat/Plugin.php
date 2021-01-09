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

namespace addons\wechat;
// 注意命名空间规范
use fun\Addons;
class Plugin extends Addons    // 需继承fun\Addon类
{
    public $menu = [
        'is_nav'=>1,//1导航菜单；0 非导航栏
        'menu'=> [
            'href' => 'wechat',
            'title' => 'wechat',
            'status' => 1,
            'auth_verify' => 1,
            'menu_status' => 1,
            'type' => '1',
            'icon' => 'layui-icon layui-icon-login-wechat',
            'menulist' => [
                [
                    'href' => 'addons/wechat/backend/index',
                    'title' => 'account',
                    'status' => 1,
                    'menu_status' => 1,
                    'type'=>1,
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/index/index', 'title' => 'index', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/index/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/index/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/index/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/index/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/wxMenu',
                    'title' => 'menu',
                    'status' => 1,
                    'menu_status' => 1,
                    'type' => '1',
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/wxMenu/index', 'title' => 'index', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/account', 'title' => 'account', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/wxMenu/update', 'title' => 'update', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/fans',
                    'title' => 'fans',
                    'status' => 1,
                    'menu_status' => 1,
                    'type' => '1',
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/fans/index', 'title' => 'list', 'status' => 1,'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/fans/aysn', 'title' => 'aysn', 'status' => 1,'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/fans/edit', 'title' => 'edit', 'status' => 1,'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/tag',
                    'title' => '标签',
                    'status' => 1,
                    'menu_status' => 1,
                    'type' => '1',
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/tag/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/tag/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/tag/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/tag/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/tag/aysn', 'title' => 'aysn', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/message',
                    'title' => '消息',
                    'status' => 1,
                    'menu_status' => 1,
                    'type' => '1',
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/message/index', 'title' => 'index', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/message/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/message/reply', 'title' => 'reply', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/material',
                    'title' => '素材',
                    'status' => 1,
                    'menu_status' => 1,
                    'type'=>1,
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/material/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/aysn', 'title' => 'aysn', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/send', 'title' => 'send', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/preview', 'title' => 'preview', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/getMaterialByType', 'title' => 'MaterialType', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/getListImage', 'title' => 'imagelist', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/imageUpload', 'title' => 'imageUpload', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/videoUpload', 'title' => 'videoUpload', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/voiceUpload', 'title' => 'voiceUpload', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/thumbUpload', 'title' => 'thumbUpload', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/ueditUploadImage', 'title' => 'UeditUploadImage', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/ueditUploadVideo', 'title' => 'ueditUploadVideo', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/material/ueditUploadVoice', 'title' => 'ueditUploadVoice', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/qrcode',
                    'title' => '二维码',
                    'status' => 1,
                    'menu_status' => 1,
                    'type'=>1,
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/qrcode/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/qrcode/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/qrcode/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/qrcode/modify', 'title' => 'modify', 'status' => 1,
                            'menu_status' => 0,],
                    ]
                ],
                [
                    'href' => 'addons/wechat/backend/reply',
                    'title' => '素材',
                    'status' => 1,
                    'menu_status' => 1,
                    'type'=>1,
                    'icon' => 'layui-icon layui-icon-login-wechat',
                    'menulist' => [
                        ['href' => 'addons/wechat/backend/reply/index', 'title' => 'list', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/reply/add', 'title' => 'add', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/reply/edit', 'title' => 'edit', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/reply/delete', 'title' => 'delete', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'addons/wechat/backend/reply/modify', 'title' => 'modify', 'status' => 1,
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