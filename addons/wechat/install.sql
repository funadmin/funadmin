
--
-- 表的结构 `__PREFIX__addons_wechat_account`
--

CREATE TABLE `__PREFIX__addons_wechat_account` (
                                                   `id` int(11) NOT NULL COMMENT '表id',
                                                   `merchant_id` int(11) NOT NULL DEFAULT '1' COMMENT '商户id',
                                                   `wxname` varchar(60) NOT NULL DEFAULT '' COMMENT '公众号名称',
                                                   `app_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'appid',
                                                   `secret` varchar(50) NOT NULL DEFAULT '' COMMENT 'appsecret',
                                                   `origin_id` varchar(64) NOT NULL DEFAULT '' COMMENT '公众号原始ID',
                                                   `weixin` char(64) NOT NULL COMMENT '微信号',
                                                   `logo` char(255) NOT NULL COMMENT '头像地址',
                                                   `token` char(255) NOT NULL COMMENT 'token微信对接token',
                                                   `aes_key` varchar(150) NOT NULL DEFAULT '' COMMENT '微信对接encodingaeskey',
                                                   `related` varchar(200) NOT NULL DEFAULT 'https://demo.funadmin.com/addons/wechat/wechatauth/related?merchant_id=1' COMMENT '微信对接地址',
                                                   `share_title` char(255) NOT NULL COMMENT '分享标题',
                                                   `share_details` varchar(255) NOT NULL COMMENT '分享详情',
                                                   `share_image` varchar(255) NOT NULL COMMENT '分享图片',
                                                   `share_link` varchar(255) NOT NULL COMMENT '分享链接',
                                                   `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型 1 普通订阅号2认证订阅号 3普通服务号 4认证服务号/认证媒体/政府订阅号',
                                                   `qr` varchar(255) NOT NULL DEFAULT '' COMMENT '二维码',
                                                   `create_time` int(11) NOT NULL COMMENT 'create_time',
                                                   `update_time` int(11) NOT NULL COMMENT 'update_time',
                                                   `delete_time` int(11) NOT NULL COMMENT 'delete_time',
                                                   `status` tinyint(1) DEFAULT '1' COMMENT '微信接入状态,0待接入1已接入 -1 伪删除'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信公公众帐号';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_fans`
--

CREATE TABLE `__PREFIX__addons_wechat_fans` (
                                                `id` int(11) NOT NULL COMMENT '粉丝ID',
                                                `member_id` int(11) NOT NULL DEFAULT '0' COMMENT '会员编号ID',
                                                `source_member_id` int(11) NOT NULL DEFAULT '0' COMMENT '推广人member_id',
                                                `merchant_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺ID',
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
                                                `create_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信公众号获取粉丝列表';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_material`
--

CREATE TABLE `__PREFIX__addons_wechat_material` (
                                                    `id` int(10) UNSIGNED NOT NULL COMMENT '微信公众号素材',
                                                    `merchant_id` int(11) NOT NULL DEFAULT '1',
                                                    `wx_aid` int(11) DEFAULT NULL,
                                                    `media_id` varchar(64) DEFAULT '' COMMENT '微信媒体id',
                                                    `file_name` varchar(255) DEFAULT NULL COMMENT '视频文件名',
                                                    `media_url` varchar(255) DEFAULT NULL,
                                                    `local_cover` varchar(255) NOT NULL DEFAULT ' ',
                                                    `type` varchar(10) NOT NULL COMMENT '图片（image）、视频（video）、语音 （voice）、图文（news）音乐（music）',
                                                    `des` varchar(150) DEFAULT ' ' COMMENT '视频描述',
                                                    `create_time` int(11) DEFAULT NULL,
                                                    `update_time` int(10) UNSIGNED DEFAULT NULL COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信公众号素材';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_material_info`
--

CREATE TABLE `__PREFIX__addons_wechat_material_info` (
                                                         `id` int(10) UNSIGNED NOT NULL COMMENT 'id',
                                                         `merchant_id` int(11) NOT NULL DEFAULT '1',
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
                                                         `hits` int(11) NOT NULL DEFAULT '0' COMMENT '阅读次数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_menu`
--

CREATE TABLE `__PREFIX__addons_wechat_menu` (
                                                `id` int(11) NOT NULL COMMENT '主键',
                                                `merchant_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
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
                                                `update_time` int(11) DEFAULT '0' COMMENT '修改日期'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信设置->微信菜单';

--
-- 转存表中的数据 `__PREFIX__addons_wechat_menu`
--

INSERT INTO `__PREFIX__addons_wechat_menu` (`id`, `merchant_id`, `wx_aid`, `menu_name`, `ico`, `pid`, `menu_event_type`, `media_id`, `menu_event_url`, `hits`, `sort`, `create_time`, `update_time`) VALUES
(1, 0, NULL, '官网', '', 0, 2, 3, 'http://www.funadmin.com/', 0, 1, 1512442512, 0),
(2, 0, NULL, '手册', '', 0, 2, 5, 'http://wx.funadmin.com/', 0, 2, 1512442543, 0),
(3, 0, NULL, '论坛', '', 0, 1, 4, 'http://demo.funadmin.com/', 0, 3, 1512547727, 0),
(4, 0, NULL, '百度', '', 3, 1, 0, 'http://bbs.funadmin.com/', 0, 1, 1542783759, 0);

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_msg_history`
--

CREATE TABLE `__PREFIX__addons_wechat_msg_history` (
                                                       `id` int(10) UNSIGNED NOT NULL,
                                                       `merchant_id` int(10) UNSIGNED DEFAULT '1' COMMENT '商户id',
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
                                                       `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '修改时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信_历史记录表';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_qrcode`
--

CREATE TABLE `__PREFIX__addons_wechat_qrcode` (
                                                  `id` int(11) NOT NULL,
                                                  `merchant_id` int(11) NOT NULL,
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
                                                  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信二维码';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_reply`
--

CREATE TABLE `__PREFIX__addons_wechat_reply` (
                                                 `id` int(10) UNSIGNED NOT NULL COMMENT '微信关键词回复表',
                                                 `merchant_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
                                                 `wx_aid` int(11) DEFAULT NULL,
                                                 `rule` varchar(32) DEFAULT NULL COMMENT '规则名',
                                                 `keyword` varchar(150) DEFAULT NULL,
                                                 `type` varchar(10) DEFAULT 'keyword' COMMENT '查询类型keyword,subscribe,default',
                                                 `msg_type` varchar(10) DEFAULT NULL COMMENT '回复消息类型  文本（text ）图片（image）、视频（video）、语音 （voice）、图文（news） 音乐（music）',
                                                 `data` mediumtext COMMENT 'text使用该自动存储文本',
                                                 `material_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'news、video voice image music的素材id等',
                                                 `status` tinyint(1) DEFAULT '1',
                                                 `create_time` int(11) DEFAULT NULL,
                                                 `update_time` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信回复表';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_tag`
--

CREATE TABLE `__PREFIX__addons_wechat_tag` (
                                               `id` int(11) NOT NULL,
                                               `tag_id` int(11) DEFAULT NULL COMMENT 'tag id',
                                               `name` varchar(100) NOT NULL COMMENT '标签名',
                                               `merchant_id` int(11) NOT NULL DEFAULT '1' COMMENT '店铺id',
                                               `wx_aid` int(11) DEFAULT NULL COMMENT '微信账号id',
                                               `status` tinyint(1) DEFAULT '1',
                                               `create_time` int(11) NOT NULL,
                                               `update_time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信用户标签表';

-- --------------------------------------------------------

--
-- 表的结构 `__PREFIX__addons_wechat_type`
--

CREATE TABLE `__PREFIX__addons_wechat_type` (
                                                `type_id` tinyint(5) NOT NULL,
                                                `name` varchar(50) NOT NULL,
                                                `create_time` int(11) NOT NULL,
                                                `update_time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信类型表';

--
-- 转存表中的数据 `__PREFIX__addons_wechat_type`
--

INSERT INTO `__PREFIX__addons_wechat_type` (`type_id`, `name`, `create_time`, `update_time`) VALUES
(1, '普通订阅号', 0, 0),
(2, '认证订阅号', 0, 0),
(3, '普通服务号', 0, 0),
(4, '认证服务号/认证媒体/政府订阅号', 0, 0);

--
-- 转储表的索引
--

--
-- 表的索引 `__PREFIX__addons_wechat_account`
--
ALTER TABLE `__PREFIX__addons_wechat_account`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_fans`
--
ALTER TABLE `__PREFIX__addons_wechat_fans`
    ADD PRIMARY KEY (`id`),
  ADD KEY `openid` (`openid`(191)),
  ADD KEY `unionid` (`unionid`(191));

--
-- 表的索引 `__PREFIX__addons_wechat_material`
--
ALTER TABLE `__PREFIX__addons_wechat_material`
    ADD PRIMARY KEY (`id`),
  ADD KEY `media_id` (`media_id`);

--
-- 表的索引 `__PREFIX__addons_wechat_material_info`
--
ALTER TABLE `__PREFIX__addons_wechat_material_info`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_menu`
--
ALTER TABLE `__PREFIX__addons_wechat_menu`
    ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_biz_shop_menu_orders` (`sort`),
  ADD KEY `IDX_biz_shop_menu_shopId` (`merchant_id`);

--
-- 表的索引 `__PREFIX__addons_wechat_msg_history`
--
ALTER TABLE `__PREFIX__addons_wechat_msg_history`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_qrcode`
--
ALTER TABLE `__PREFIX__addons_wechat_qrcode`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_reply`
--
ALTER TABLE `__PREFIX__addons_wechat_reply`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_tag`
--
ALTER TABLE `__PREFIX__addons_wechat_tag`
    ADD PRIMARY KEY (`id`);

--
-- 表的索引 `__PREFIX__addons_wechat_type`
--
ALTER TABLE `__PREFIX__addons_wechat_type`
    ADD PRIMARY KEY (`type_id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_account`
--
ALTER TABLE `__PREFIX__addons_wechat_account`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '表id';

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_fans`
--
ALTER TABLE `__PREFIX__addons_wechat_fans`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '粉丝ID';

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_material`
--
ALTER TABLE `__PREFIX__addons_wechat_material`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '微信公众号素材';

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_material_info`
--
ALTER TABLE `__PREFIX__addons_wechat_material_info`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'id';

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_menu`
--
ALTER TABLE `__PREFIX__addons_wechat_menu`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键', AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_msg_history`
--
ALTER TABLE `__PREFIX__addons_wechat_msg_history`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_qrcode`
--
ALTER TABLE `__PREFIX__addons_wechat_qrcode`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_reply`
--
ALTER TABLE `__PREFIX__addons_wechat_reply`
    MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '微信关键词回复表';

--
-- 使用表AUTO_INCREMENT `__PREFIX__addons_wechat_tag`
--
ALTER TABLE `__PREFIX__addons_wechat_tag`
    MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
