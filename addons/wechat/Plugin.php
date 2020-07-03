<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/11/7
 */

namespace addons\wechat;
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
        'name' => 'wechat',    // 插件标识唯一
        'title' => '微信管理',    // 插件名称
        'description' => '微信菜单，粉丝，回复等插件-SpeedAdmin微信管理插件',    // 插件简介
        'status' => 1,    // 状态
        'author' => 'yuege',
        'require' => '0.3',
        'version' => '0.3',
        'website' => ''

    ];
    public $menu = [
        'is_nav'=>0,//1导航栏；0 非导航栏
        'menu'=> [
            'href' => 'wechat',
            'title' => '微信管理',
            'status' => 1,
            'auth_open' => 1,
            'menu_status' => 1,
            'icon' => 'fa fa-wechat',
            'menulist' => [
                [
                    'href' => 'wechat.wechat/index',
                    'title' => '微信账号',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'wechat.wechat/add', 'title' => '添加账号', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/edit', 'title' => '编辑账号', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/delete', 'title' => '删除账号', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/state', 'title' => '状态', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],
                [
                    'href' => 'wechat.wechat/menu',
                    'title' => '微信菜单',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-comments-o',
                    'menulist' => [
                        ['href' => 'wechat.wechat/getWxAccount', 'title' => '获取账号', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/changeApp', 'title' => '切换账号', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/addWeixinMenu', 'title' => '添加菜单', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/updataWechatMenu', 'title' => '更新菜单', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],
                [
                    'href' => 'wechat.wechat/fans',
                    'title' => '微信粉丝',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-instagram',
                    'menulist' => [
                        ['href' => 'wechat.wechat/fansAysn', 'title' => '粉丝同步', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/fansTagGroup', 'title' => '粉丝标签修改', 'status' => 1,
                            'menu_status' => 0,],


                    ]
                ],
                
                [
                    'href' => 'wechat.wechat/tag',
                    'title' => '粉丝标签',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'wechat.wechat/tagState', 'title' => '标签状态', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/tagAysn', 'title' => '标签同步', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/tagAdd', 'title' => '标签添加', 'status' => 1,
                        'menu_status' => 0,],
                        ['href' => 'wechat.wechat/tagEdit', 'title' => '标签编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/tagDel', 'title' => '标签删除', 'status' => 1,
                            'menu_status' => 0,],





                    ]
                ],
                
                [
                    'href' => 'wechat.wechat/message',
                    'title' => '历史消息',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-newspaper-o',
                    'menulist' => [
                        ['href' => 'wechat.wechat/messageDel', 'title' => '消息删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/messageReply', 'title' => '消息回复', 'status' => 1,
                            'menu_status' => 0,],

                    ]
                ],


              
                [
                    'href' => 'wechat.wechat/material',
                    'title' => '微信素材',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-camera',
                    'menulist' => [
                        ['href' => 'wechat.wechat/materialAdd', 'title' => '素材添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/materialEdit', 'title' => '素材编辑', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/materialAysn', 'title' => '素材同步', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/materialDel', 'title' => '素材删除', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/materialSend', 'title' => '素材发送', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/materialPreview', 'title' => '素材预览', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/getMaterialByType', 'title' => '获取素材类型', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/getListImage', 'title' => '获取图片列表', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/imageUpload', 'title' => '图片上传', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/videoUpload', 'title' => '视频上传', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/voiceUpload', 'title' => '音频上传', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/thumbUpload', 'title' => '缩略图上传', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/UeditUploadImage', 'title' => '百度图片上传', 'status' => 1,
                            'menu_status' => 0,],

                        ['href' => 'wechat.wechat/UeditUploadVideo', 'title' => '百度视频上传', 'status' => 1,
                            'menu_status' => 0,],

                        ['href' => 'wechat.wechat/UeditUploaVoice', 'title' => '百度音频上传', 'status' => 1,
                            'menu_status' => 0,],




                    ]
                ],

                [
                    'href' => 'wechat.wechat/qrcode',
                    'title' => '微信二维码',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-qrcode',
                    'menulist' => [
                        ['href' => 'wechat.wechat/qrcodeAdd', 'title' => '二维码添加', 'status' => 1,
                            'menu_status' => 0,],

                        ['href' => 'wechat.wechat/qrcodeDel', 'title' => '二维码删除', 'status' => 1,
                            'menu_status' => 0,],

                        ['href' => 'wechat.wechat/qrcodeState', 'title' => '二维码状态', 'status' => 1,
                            'menu_status' => 0,],



                    ]
                ],

                [
                    'href' => 'wechat.wechat/reply',
                    'title' => '微信素材',
                    'status' => 1,
                    'menu_status' => 1,
                    'icon' => 'fa fa-clock-o',
                    'menulist' => [
                        ['href' => 'wechat.wechat/replyAdd', 'title' => '回复添加', 'status' => 1,
                            'menu_status' => 0,],
                        ['href' => 'wechat.wechat/replyEdit', 'title' => '回复添加', 'status' => 1,
                            'menu_status' => 0,],

                        ['href' => 'wechat.wechat/replyDel', 'title' => '回复删除', 'status' => 1,
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