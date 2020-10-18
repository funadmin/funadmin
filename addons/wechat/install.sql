DROP TABLE IF EXISTS `__PREFIX__addons_wechat_account`;
CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_wechat_account` (
   `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '表id',
  `store_id` int(11) NOT NULL DEFAULT '1' COMMENT 'uid',
  `wxname` varchar(60) NOT NULL DEFAULT '' COMMENT '公众号名称',
  `aeskey` varchar(256) NOT NULL DEFAULT '' COMMENT 'aeskey',
  `encode` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'encode',
  `app_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'appid',
  `app_secret` varchar(50) NOT NULL DEFAULT '' COMMENT 'appsecret',
  `origin_id` varchar(64) NOT NULL DEFAULT '' COMMENT '公众号原始ID',
  `weixin` char(64) NOT NULL COMMENT '微信号',
  `logo` char(255) NOT NULL COMMENT '头像地址',
  `token` char(255) NOT NULL COMMENT 'token',
  `w_token` varchar(150) NOT NULL DEFAULT '' COMMENT '微信对接token',
  `related` varchar(200) NOT NULL DEFAULT 'https://demo.funadmin.com/wechat/wechatApi/related?store_id=1' COMMENT '微信对接地址',
  `create_time` int(11) NOT NULL COMMENT 'create_time',
  `update_time` int(11) NOT NULL COMMENT 'updatetime',
  `tplcontentid` varchar(2) NOT NULL DEFAULT '' COMMENT '内容模版ID',
  `share_ticket` varchar(150) NOT NULL DEFAULT '' COMMENT '分享ticket',
  `share_dated` char(15) NOT NULL COMMENT 'share_dated',
  `authorizer_access_token` varchar(200) NOT NULL DEFAULT '' COMMENT 'authorizer_access_token',
  `authorizer_refresh_token` varchar(200) NOT NULL DEFAULT '' COMMENT 'authorizer_refresh_token',
  `authorizer_expires` char(10) NOT NULL COMMENT 'authorizer_expires',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型 1 普通订阅号2认证订阅号 3普通服务号 4认证服务号/认证媒体/政府订阅号',
  `web_access_token` varchar(200) DEFAULT '' COMMENT '网页授权token',
  `web_refresh_token` varchar(200) DEFAULT '' COMMENT 'web_refresh_token',
  `web_expires` int(11) NOT NULL COMMENT '过期时间',
  `qr` varchar(200) NOT NULL DEFAULT '' COMMENT 'qr',
  `menu_config` mediumtext COMMENT '菜单',
  `status` tinyint(1) DEFAULT '1' COMMENT '微信接入状态,0待接入1已接入',
   PRIMARY KEY (`id`),
   KEY `uid` (`store_id`) USING BTREE,
   KEY `uid_2` (`store_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='微信公共帐号';


INSERT INTO `__PREFIX__addons_wechat_account` (`id`, `store_id`, `wxname`, `aeskey`, `encode`, `app_id`, `app_secret`, `origin_id`, `weixin`, `logo`, `token`, `w_token`, `related`, `create_time`, `update_time`, `tplcontentid`, `share_ticket`, `share_dated`, `authorizer_access_token`, `authorizer_refresh_token`, `authorizer_expires`, `type`, `web_access_token`, `web_refresh_token`, `web_expires`, `qr`, `menu_config`, `status`) VALUES
(1, 1, 'funadmin', 'adsfda', 0, 'wxecd04cbbfc06a972', 'ec83a45f2a561a90cf5f63e7476bae36', 'gh_8b042cc4ccf9', 'lemomcms', '/storage/uploads/20190905/dfdcecfa905e2858ae45b87542c0c5ab.png', 'weixin', 'weixins', 'https://demo.funadmin.com/wechat/wechatApi/related?store_id=1', 1490691329, 1580223682, '', '', '', '', '', '', 4, '9_ztdL3qhqHHAgFTIANDMStPvneUubYL0sANeFHEYDXu_qzElDwaQeSNwwhi1EfpDXzFwOeP05e0wMRpsJvQVVjnmhiWtZIqOwj4RwIdhXQnB1WPP0yw4pv8x2c_NA2ykcPKD-V6aTa3mFDKO9YJSaAAALWF', '', 1524884051, '/storage/uploads/20190905/2790a6a9cbb9ca1bcdfaca9b25d0316a.jpg', NULL, 1);

DROP TABLE IF EXISTS `__PREFIX__addons_wechat_fans`;
CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_fans` (
  `fans_id` int(11) NOT NULL AUTO_INCREMENT COMMENT  '粉丝ID',
  `wx_aid` int(11) DEFAULT NULL COMMENT '微信账户id',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '会员编号ID',
  `source_uid` int(11) NOT NULL DEFAULT '0' COMMENT '推广人uid',
  `store_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺ID',
  `nickname` varchar(255) NOT NULL COMMENT '昵称',
  `nickname_encode` varchar(255) DEFAULT '',
  `headimgurl` varchar(500) NOT NULL DEFAULT '' COMMENT '头像',
  `sex` smallint(6) NOT NULL DEFAULT '1' COMMENT '性别',
  `language` varchar(20) NOT NULL DEFAULT '' COMMENT '用户语言',
  `country` varchar(60) NOT NULL DEFAULT '' COMMENT '国家',
  `province` varchar(255) NOT NULL DEFAULT '' COMMENT '省',
  `city` varchar(255) NOT NULL DEFAULT '' COMMENT '城市',
  `district` varchar(255) NOT NULL DEFAULT '' COMMENT '行政区/县',
  `openid` varchar(255) NOT NULL DEFAULT '' COMMENT '用户的标识，对当前公众号唯一     用户的唯一身份ID',
  `unionid` varchar(255) NOT NULL DEFAULT '' COMMENT '粉丝unionid',
  `groupid` int(11) NOT NULL DEFAULT '0' COMMENT '粉丝所在组id',
  `subscribe` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否订阅',
  `subscribe_scene` varchar(50) DEFAULT NULL COMMENT '订阅场景',
  `remark` varchar(255) NOT NULL COMMENT '备注',
  `tag` varchar(200) DEFAULT NULL COMMENT '标签',
  `tagid_list` varchar(255) DEFAULT NULL COMMENT '标签列表',
  `subscribe_time` int(11) DEFAULT '0' COMMENT '订阅时间',
  `unsubscribe_time` int(11) DEFAULT '0' COMMENT '解订阅时间',
  `qr_scene` varchar(255) DEFAULT NULL COMMENT '二维码扫码场景（开发者自定义）',
  `qr_scene_str` varchar(255) DEFAULT NULL COMMENT '二维码扫码场景描述（开发者自定义）',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `update_time` int(11) DEFAULT '0' COMMENT '粉丝信息最后更新时间',
  `create_time` int(11) DEFAULT NULL,
    PRIMARY KEY (`fans_id`),
   KEY `openid` (`openid`(191)),
   KEY `unionid` (`unionid`(191))
) ENGINE=InnoDB AUTO_INCREMENT=3  DEFAULT CHARSET=utf8mb4 COMMENT='微信公众号获取粉丝列表';




INSERT INTO `__PREFIX__addons_wechat_fans` (`fans_id`, `wx_aid`, `uid`, `source_uid`, `store_id`, `nickname`, `nickname_encode`, `headimgurl`, `sex`, `language`, `country`, `province`, `city`, `district`, `openid`, `unionid`, `groupid`, `subscribe`, `subscribe_scene`, `remark`, `tag`, `tagid_list`, `subscribe_time`, `unsubscribe_time`, `qr_scene`, `qr_scene_str`, `status`, `update_time`, `create_time`) VALUES
(1, 32, 0, 0, 1, '🐘 心之所向🐘', '\"\\ud83d\\udc18 \\u5fc3\\u4e4b\\u6240\\u5411\\ud83d\\udc18\"', 'http://thirdwx.qlogo.cn/mmopen/Q3auHgzwzM4VFiaYnBD77jqvXaG55kz8cYgynjUAic5oNcrjkicjIGvVVyRYfLsiceojIlI709OKWPAQr95E2y2Ick6jSHSrIJXgtcn1VnDM4qE/132', 1, 'zh_CN', '中国', '湖南', '衡阳', '', 'oBSasxCSibhs0U_O8d1QCLRR6woQ', '', 2, 1, 'ADD_SCENE_QR_CODE', '', '星标组', '[2]', 1568970767, 0, '0', '', 1, 1572230913, 1567909800),
(2, 32, 0, 0, 1, '少年智力开发报订阅', '\"\\u5c11\\u5e74\\u667a\\u529b\\u5f00\\u53d1\\u62a5\\u8ba2\\u9605\"', 'http://thirdwx.qlogo.cn/mmopen/7jOTIafB9k4w5h73kjDCf0o0IXjb7tNuJHk45lY9ZopsqS4rsQ5UxkAgvOqe49UESQyiaHp0jG7u3p1WhiaHpm7g/132', 1, 'zh_CN', '中国', '河北', '石家庄', '', 'oBSasxDCwYJ4QlFRgSbi-SZktfZs', '', 2, 1, 'ADD_SCENE_QR_CODE', '', '其他', '[2]', 1570784081, 0, '0', '', 1, 1572230913, 1571531137);


DROP TABLE IF EXISTS `__PREFIX__addons_wechat_material`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_material` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '微信公众号素材',
  `store_id` int(11) NOT NULL DEFAULT '1',
  `wx_aid` int(11) DEFAULT NULL,
  `media_id` varchar(64) DEFAULT '' COMMENT '微信媒体id',
  `file_name` varchar(255) DEFAULT NULL COMMENT '视频文件名',
  `media_url` varchar(255) DEFAULT NULL,
  `local_cover` varchar(255) NOT NULL DEFAULT ' ',
  `type` varchar(10) NOT NULL COMMENT '图片（image）、视频（video）、语音 （voice）、图文（news）音乐（music）',
  `des` varchar(150) DEFAULT ' ' COMMENT '视频描述',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(10) UNSIGNED DEFAULT NULL COMMENT '更新时间',
   PRIMARY KEY (`id`),
   KEY `media_id` (`media_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='微信公众号素材';




DROP TABLE IF EXISTS `__PREFIX__addons_wechat_material_info`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_material_info` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id',
  `store_id` int(11) NOT NULL DEFAULT '1',
  `wx_aid` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `thumb_media_id` varchar(100) DEFAULT NULL COMMENT '	图文消息的封面图片素材id（必须是永久mediaID）',
  `local_cover` varchar(255) DEFAULT NULL,
  `cover` varchar(200) NOT NULL COMMENT '图文消息封面',
  `title` varchar(100) DEFAULT NULL,
  `author` varchar(50) NOT NULL COMMENT '作者',
  `show_cover` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否显示封面',
  `digest` text COMMENT '图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空',
  `content` text NOT NULL COMMENT '正文',
  `url` varchar(255) NOT NULL COMMENT '图文页的URL，或者，当获取的列表是图片素材列表时，该字段是图片的URL',
  `content_source_url` varchar(200) NOT NULL DEFAULT '' COMMENT '图文消息的原文地址，即点击“阅读原文”后的URL',
  `need_open_comment` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Uint32 是否打开评论，0不打开，1打开',
  `only_fans_can_comment` tinyint(1) DEFAULT '1' COMMENT 'Uint32 是否粉丝才可评论，0所有人可评论，1粉丝才可评论',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序号',
  `hits` int(11) NOT NULL DEFAULT '0' COMMENT '阅读次数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB   AUTO_INCREMENT=1  DEFAULT CHARSET=utf8mb4;

--
DROP TABLE IF EXISTS `__PREFIX__addons_wechat_menu`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_menu` (

  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键',
  `store_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
  `wx_aid` int(11) DEFAULT NULL,
  `menu_name` varchar(50) NOT NULL DEFAULT '' COMMENT '菜单名称',
  `ico` varchar(32) NOT NULL DEFAULT '' COMMENT '菜图标单',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父菜单',
  `menu_event_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1普通url 2 图文素材 3 功能',
  `media_id` int(11) NOT NULL DEFAULT '0' COMMENT '图文消息ID',
  `menu_event_url` varchar(255) NOT NULL DEFAULT '' COMMENT '菜单url',
  `hits` int(11) NOT NULL DEFAULT '0' COMMENT '触发数',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `create_time` int(11) DEFAULT '0' COMMENT '创建日期',
  `update_time` int(11) DEFAULT '0' COMMENT '修改日期',
    PRIMARY KEY (`id`),
   KEY `IDX_biz_shop_menu_orders` (`sort`),
   KEY `IDX_biz_shop_menu_shopId` (`store_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5  DEFAULT CHARSET=utf8mb4 COMMENT='微信设置->微信菜单';


INSERT INTO `__PREFIX__addons_wechat_menu` (`id`, `store_id`, `wx_aid`, `menu_name`, `ico`, `pid`, `menu_event_type`, `media_id`, `menu_event_url`, `hits`, `sort`, `create_time`, `update_time`) VALUES
(1, 0, NULL, '官网', '', 0, 2, 3, 'http://www.funadmin.com/', 0, 1, 1512442512, 0),
(2, 0, NULL, '手册', '', 0, 2, 5, 'http://wx.funadmin.com/', 0, 2, 1512442543, 0),
(3, 0, NULL, '论坛', '', 0, 1, 4, 'http://demo.funadmin.com/', 0, 3, 1512547727, 0),
(4, 0, NULL, '百度', '', 3, 1, 0, 'http://bbs.funadmin.com/', 0, 1, 1542783759, 0);


DROP TABLE IF EXISTS `__PREFIX__addons_wechat_msg_history`;
CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_msg_history` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `store_id` int(10) UNSIGNED DEFAULT '1' COMMENT '商户id',
  `wx_aid` int(11) DEFAULT NULL COMMENT '微信账号id',
  `media_id` int(11) DEFAULT NULL,
  `keyword_id` int(10) DEFAULT '0' COMMENT '关键字id',
  `nickname` varchar(150) DEFAULT NULL COMMENT '昵称',
  `openid` varchar(50) DEFAULT '',
  `content_json` varchar(1000) DEFAULT NULL,
  `content` varchar(1000) DEFAULT '' COMMENT '微信消息',
  `type` varchar(20) DEFAULT '',
  `event` varchar(20) DEFAULT '' COMMENT '详细事件',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态0:禁用;1启用',
  `create_time` int(10) UNSIGNED DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='微信_历史记录表';



DROP TABLE IF EXISTS `__PREFIX__addons_wechat_qrcode`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_qrcode` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `wx_aid` int(11) NOT NULL,
  `name` varchar(50) DEFAULT '',
  `qrcode` varchar(255) NOT NULL,
  `scene_id` int(11) DEFAULT NULL,
  `type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0 临时，1 永久',
  `ticket` varchar(255) NOT NULL,
  `expire_seconds` int(11) NOT NULL,
  `url` varchar(255) NOT NULL DEFAULT ' ',
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
)  ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='微信二维码';


DROP TABLE IF EXISTS `__PREFIX__addons_wechat_reply`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_reply` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '微信关键词回复表',
  `store_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
  `wx_aid` int(11) DEFAULT NULL,
  `rule` varchar(32) DEFAULT NULL COMMENT '规则名',
  `keyword` varchar(150) DEFAULT NULL,
  `type` varchar(10) DEFAULT 'keyword' COMMENT '查询类型keyword,subscribe,default',
  `msg_type` varchar(10) DEFAULT NULL COMMENT '回复消息类型  文本（text ）图片（image）、视频（video）、语音 （voice）、图文（news） 音乐（music）',
  `data` mediumtext COMMENT 'text使用该自动存储文本',
  `material_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'news、video voice image music的素材id等',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='微信回复表';


DROP TABLE IF EXISTS `__PREFIX__addons_wechat_tag`;

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_wechat_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag_id` int(11) DEFAULT NULL COMMENT 'tag id',
  `name` varchar(100) NOT NULL COMMENT '标签名',
  `store_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
  `wx_aid` int(11) DEFAULT NULL COMMENT '微信账号id',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB   AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='微信用户标签表';


DROP TABLE IF EXISTS `__PREFIX__addons_wechat_type`;

CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_wechat_type` (
  `type_id` tinyint(5) NOT NULL,
  `name` varchar(50) NOT NULL,
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信类型表';


INSERT INTO `__PREFIX__addons_wechat_type` (`type_id`, `name`, `create_time`, `update_time`) VALUES
(1, '普通订阅号', 0, 0),
(2, '认证订阅号', 0, 0),
(3, '普通服务号', 0, 0),
(4, '认证服务号/认证媒体/政府订阅号', 0, 0);
