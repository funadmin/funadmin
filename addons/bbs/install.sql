CREATE TABLE IF NOT EXISTS  `__PREFIX__bbs` (
  `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` smallint(5) NOT NULL DEFAULT '0' COMMENT '类别ID',
  `user_id` int(11) DEFAULT NULL,
  `is_top` int(11) NOT NULL DEFAULT '0' COMMENT '是否置顶0否，1是',
  `is_reply` tinyint(1) DEFAULT '1' COMMENT '是否可回复',
  `comment_num` int(11) DEFAULT '0' COMMENT '评论数量',
  `collect_num` int(11) DEFAULT '0' COMMENT '收藏数',
  `score` int(11) NOT NULL DEFAULT '0' COMMENT '飞吻',
  `is_fine` int(11) DEFAULT '0' COMMENT '是否为精贴0否，1是',
  `is_solved` tinyint(4) DEFAULT '0' COMMENT '是否已结',
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT '文章标题',
  `description` varchar(255)  DEFAULT NULL  COMMENT '文章摘要',
  `content` longtext NOT NULL,
  `author` varchar(30) NOT NULL DEFAULT '' COMMENT '文章作者',
  `author_email` varchar(60) NOT NULL DEFAULT '' COMMENT '作者邮箱',
  `keywords` varchar(255) NOT NULL DEFAULT '' COMMENT '关键字,用逗号隔开',
  `article_type` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示,1:显示,0:不显示',
  `file_url` varchar(255) NOT NULL DEFAULT '' COMMENT '附件地址',
  `open_type` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `click` int(11) DEFAULT '0' COMMENT '浏览量',
  `publish_time` int(11) DEFAULT NULL COMMENT '文章预告发布时间',
  `sort` tinyint(1) DEFAULT '0',
  `thumb` varchar(255) DEFAULT '' COMMENT '文章缩略图',
  `tags` varchar(100) NOT NULL DEFAULT '',
  `create_time` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `pid` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='论坛资源表';


INSERT INTO `__PREFIX__bbs` (`id`, `pid`, `user_id`, `is_top`, `is_reply`, `comment_num`, `collect_num`, `score`, `is_fine`, `is_solved`, `title`, `description`, `content`, `author`, `author_email`, `keywords`, `article_type`, `status`, `file_url`, `open_type`, `link`, `click`, `publish_time`, `sort`, `thumb`, `tags`, `create_time`, `update_time`) VALUES
(1, 1, 1, 0, 1, 0, 0, 0, 1, 0, '表单自定义校验', NULL, '各位大神，layui有表单校验插件吗？类似于jquery-form-validator这种的，每次提交表单之前通过解析返回的JSON后，知道要校验哪些控件，去灵活的校验', '', '', '', 1, 1, '', 0, '', 16, NULL, 0, '', '', 1580633016, 1580809803),
(2, 2, 11, 1, 1, 0, 0, 0, 0, 0, 'Lemocms管理系统：为二次开发而生，让开发变得更简单', NULL, '[pre]\n基于最新的thinkphp6(tp6) ，easywechat4.X，layui2.5.5 开发的后台管理框架，集成了权限管理，插件管理，微信管理，内容管理，,restful api 接口等各方面的内容本项目长期更新，期待更多的功能 QQ群 455018252\n[/pre]\n[hr]\n\nLemocms v1.2管理系统：为二次开发而生，让开发变得更简单\n[hr]\n这是一款快速、高效、便捷、灵活的应用开发框架。\n系统采用最新版TinkPHP6框架开发，底层安全可靠，数据查询更快，运行效率更高，网站速度更快, 后续随官网升级而升级\n密码动态加密,相同密码入库具有唯一性，用户信息安全牢固,告别简单md5加密\n自适应前端，桌面和移动端访问界面友好简洁，模块清晰。\nlayui采用最新layui2.5.5 框架\neasywechat 采用最新的4.*版本\n后台权限\n站点管理\n日志管理\n内容管理\n模型管理\n会员管理\n微信管理\n插件管理\nrestful api 接口\n...更多', '', '', '', 1, 1, '', 0, '', 45, NULL, 0, '', '', 1580634269, 1580634341),
(3, 5, 1, 1, 1, 0, 0, 0, 1, 0, 'thinkphp  V6.0.2版本发布，祝大家2020新年快乐！', NULL, '[pre]\n\n thinkphp V6.0.2版本发布，祝大家2020新年快乐！<br>本次更新包含一个可能的Session安全隐患修正，建议更新\n\n[/pre]\n\n[pre]\n本次更新包含一个可能的Session安全隐患修正，建议更新。主要更新：改进设置方法后缀后的操作名获取问题\n修正optimize:schema指令<br>修正Request类inputData处理\n改进中间件方法支持传多个参数修正sessionid检查的一处隐患\n完善对15位身份证号码的校验<br>增加远程多对多关联支持\n增加MongoDb的事务支持（mongodb版本V4.0+）>改进insertAll的replace支持\n\n安装和更新[hr]V6版本开始仅支持Composer安装及更新，支持上个版本的无缝更新，直接使用\n\ncomposer update</pre>更新到最新版本即可。\n[/pre]\n如果需要全新安装，使用：\n\n[pre]\ncomposer create-project topthink/think tp\n[/pre]', '', '', '', 1, 1, '', 0, '', 31, NULL, 0, '', '', 1580711492, 1580712222),
(4, 6, 1, 1, 1, 0, 0, 0, 1, 0, 'lemobbs 春节版发布', NULL, '[pre]\n\nlemobbs  基于最新的lemocms .layui fly  模板开发的社区管理框架，\n\n[/pre]\n集成了权限管理，插件管理，微信管理，内容管理，,restful api 接口等各方面的内容本项目长期更新，\n期待更多的功能 QQ群455019756， 455018252\n\nlemobbs  v1.2管理系统：为二次开发而生，让开发变得更简单\n\n这是一款快速、高效、便捷、灵活的应用开发框架。\n系统采用最新版TinkPHP6框架开发，底层安全可靠，数据查询更快，运行效率更高，网站速度更快, 后续随官网升级而升级\n密码动态加密\n用户信息安全牢固,\n告别简单md5加密\n自适应前端，桌面和移动端访问界面友好简洁，模块清晰。\nlayui采用最新layui2.5.5 框架\neasywechat 采用最新的4.*版本\n后台权限\n站点管理\n日志管理\n内容管理\n模型管理\n会员管理\n微信管理\n插件管理\nrestful api 接口\n...更多', '', '', '', 1, 1, '', 0, '', 88, NULL, 0, '', '', 1580711627, 1580718331),
(5, 3, 13, 0, 1, 0, 0, 0, 0, 0, '论坛贴无法显示？', NULL, '论坛贴无法显示？', '', '', '', 1, 1, '', 0, '', 8, NULL, 0, '', '', 1580816983, 1580816983);

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_cate` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(20) DEFAULT NULL COMMENT '类别名称',
  `title_alias` varchar(20) DEFAULT NULL COMMENT '别名',
  `title_type` smallint(6) DEFAULT '0' COMMENT '默认分组',
  `pid` smallint(6) DEFAULT '0' COMMENT '上级ID',
  `show_in_nav` tinyint(1) DEFAULT '0' COMMENT '是否导航显示',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态',
  `sort` smallint(6) DEFAULT '50' COMMENT '排序',
  `cat_desc` varchar(255) DEFAULT NULL COMMENT '分类描述',
  `keywords` varchar(30) DEFAULT NULL COMMENT '搜索关键词',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
   PRIMARY KEY (`id`),
   UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='论坛分类表';




INSERT INTO `__PREFIX__bbs_cate` (`id`, `title`, `title_alias`, `title_type`, `pid`, `show_in_nav`, `status`, `sort`, `cat_desc`, `keywords`, `create_time`, `update_time`) VALUES
(1, '提问', '', 1, 0, 0, 1, 0, '1233', '', 0, 1568363450),
(2, '分享', '', 1, 0, 0, 1, 2, '', '', 0, 0),
(3, '讨论', '', 1, 0, 0, 1, 2, '', '', 0, 1568279565),
(4, '建议', '', 1, 0, 0, 1, 4, '', '', 0, 0),
(5, '公告', '', 1, 0, 0, 1, 5, '', '', 0, 0),
(6, '动态', '', 1, 0, 0, 1, 6, '', '', 0, 0),
(7, 'bug反馈', 'bug反馈', 0, 0, 0, 1, 50, NULL, NULL, 1580711449, 1580711449);


CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_collect` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `collect` text COMMENT '收藏的json 信息',
  `bbs_id` int(1) NOT NULL COMMENT '文章id',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COMMENT='收藏表';



INSERT INTO `__PREFIX__bbs_collect` (`id`, `user_id`, `collect`, `bbs_id`, `create_time`) VALUES
(7, 1, NULL, 2, 1580710638),
(8, 1, NULL, 4, 1580809579);


CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_comment` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '评论人',
  `bbs_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '文章id',
  `reply_user_id` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '回复对象id',
  `content` text NOT NULL COMMENT '评论内容',
  `create_time` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `status` int(1) NOT NULL DEFAULT '1' COMMENT '状态1正常，0删除',
  `is_read` int(1) NOT NULL DEFAULT '0' COMMENT '是否已读0未读，1已读',
  `is_adopt` tinyint(1) DEFAULT '0' COMMENT '采纳',
  `like_num` int(11) DEFAULT '0' COMMENT '喜欢欢人数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COMMENT='评论表';



INSERT INTO `__PREFIX__bbs_comment` (`id`, `user_id`, `bbs_id`, `reply_user_id`, `content`, `create_time`, `status`, `is_read`, `is_adopt`, `like_num`) VALUES
(1, 1, 4, 0, 'face[微笑] ', 1580809716, 1, 0, 0, 0),
(2, 1, 5, 0, '哪个帖子呢', 1580867958, 1, 0, 0, 0);



CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_comment_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT 'like user id',
  `comment_id` int(11) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='赞表';



CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_message` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receive_id` int(11) DEFAULT NULL COMMENT '接受者id',
  `send_id` int(11) DEFAULT '0' COMMENT '发送者id  0 系统',
  `content` varchar(255) DEFAULT NULL COMMENT '内容',
  `article_id` int(11) DEFAULT NULL COMMENT '文章id',
  `type` tinyint(1) DEFAULT '0' COMMENT '0 系统  1 文章，2评论 回复',
  `is_read` tinyint(1) DEFAULT '0' COMMENT '0未读 1已读',
  `score` decimal(10,2) DEFAULT NULL COMMENT '积分',
  `create_time` int(11) DEFAULT NULL,
   PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8  DEFAULT CHARSET=utf8mb4 COMMENT='消息';



INSERT INTO `__PREFIX__bbs_message` (`id`, `receive_id`, `send_id`, `content`, `article_id`, `type`, `is_read`, `score`, `create_time`) VALUES
(1, 11, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580634538),
(2, 1, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580710628),
(3, 12, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580718413),
(4, 11, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580781519),
(5, 1, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580789018),
(6, 1, 1, '回复了您的文章', NULL, 1, 0, NULL, 1580809716),
(7, 13, 1, '回复了您的文章', NULL, 1, 0, NULL, 1580868021);

CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_user_sign` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `uid` int(11) DEFAULT '0' COMMENT '用户id',
  `sign_total` int(6) DEFAULT '0' COMMENT '累计签到天数',
  `sign_count` int(6) DEFAULT '0' COMMENT '连续签到天数',
  `sign_last` int(11) DEFAULT '0' COMMENT '最后签到时间',
  `sign_time` mediumtext COMMENT '历史签到时间，以逗号隔开',
  `score` int(11) DEFAULT '0' COMMENT '用户累计签到总积分',
  `month_score` int(6) DEFAULT NULL COMMENT '本月累计积分',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COMMENT='签到表';



INSERT INTO `__PREFIX__bbs_user_sign` (`id`, `uid`, `sign_total`, `sign_count`, `sign_last`, `sign_time`, `score`, `month_score`) VALUES
(1, 11, 2, 1, 1580781519, '1580634538,1580781519', 0, NULL),
(2, 1, 3, 3, 1580868219, '1580710628,1580789018,1580868219', 0, NULL),
(3, 12, 1, 1, 1580718413, '1580718413', 0, NULL);

CREATE TABLE IF NOT EXISTS `__PREFIX__bbs_user_sign_rule` (
  `id` int(2) UNSIGNED NOT NULL AUTO_INCREMENT,
  `days` int(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '连续天数',
  `score` int(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '积分',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='用户签到积分规则';



INSERT INTO `__PREFIX__bbs_user_sign_rule` (`id`, `days`, `score`) VALUES
(1, 1, 2),
(2, 5, 3),
(3, 10, 8),
(4, 20, 15),
(5, 30, 25),
(6, 60, 40);

CREATE TABLE IF NOT EXISTS  `__PREFIX__bbs_link` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '链接名称',
  `url` varchar(200) NOT NULL COMMENT '链接URL',
  `type_id` tinyint(4) DEFAULT NULL COMMENT '所属栏目ID',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `qq` varchar(20) DEFAULT NULL COMMENT '联系QQ',
  `sort` int(5) NOT NULL DEFAULT '50' COMMENT '排序',
  `status` tinyint(2) NOT NULL DEFAULT '1' COMMENT '0禁用1启用',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB   AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4;


INSERT INTO `__PREFIX__bbs_link` (`id`, `name`, `url`, `type_id`, `email`, `qq`, `sort`, `status`, `create_time`, `update_time`) VALUES
(23, 'lemocms', 'https://www.lemocms.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566102829, 1568359676),
(25, '百度', 'https://www.baidu.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103165, 1566103165),
(26, '新浪', 'https://www.sina.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103233, 1566103233);


CREATE TABLE IF NOT EXISTS  `__PREFIX__bbs_adv_position` (
  `id` int(3) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id',
  `position_name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告位置名称',
  `ad_width` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位宽度',
  `ad_height` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位高度',
  `position_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '广告描述',
  `position_style` mediumtext COMMENT '模板',
  `status` tinyint(1) DEFAULT '0' COMMENT '0关闭1开启',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
   UNIQUE KEY `position_id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2  DEFAULT CHARSET=utf8mb4;

INSERT INTO `__PREFIX__bbs_adv_position` (`id`, `position_name`, `ad_width`, `ad_height`, `position_desc`, `position_style`, `status`, `create_time`, `update_time`) VALUES
(1, '首页轮播', 0, 0, '首页轮播', NULL, 1, NULL, NULL);

CREATE TABLE IF NOT EXISTS  `__PREFIX__bbs_adv` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '广告id',
  `pid` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位置ID',
  `media_type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告类型',
  `ad_name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告名称',
  `ad_link` varchar(255) DEFAULT '' COMMENT '链接地址',
  `ad_image` mediumtext NOT NULL COMMENT '图片地址',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '投放时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `link_admin` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人',
  `link_email` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人邮箱',
  `link_phone` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人联系电话',
  `click_count` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击量',
  `sort` int(20) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示',
  `orderby` smallint(6) DEFAULT '50' COMMENT '排序',
  `target` tinyint(1) DEFAULT '0' COMMENT '是否开启浏览器新窗口',
  `bgcolor` varchar(20) DEFAULT NULL COMMENT '背景颜色',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
   KEY `enabled` (`status`) USING BTREE,
   KEY `position_id` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

INSERT INTO `__PREFIX__bbs_adv` (`id`, `pid`, `media_type`, `ad_name`, `ad_link`, `ad_image`, `start_time`, `end_time`, `link_admin`, `link_email`, `link_phone`, `click_count`, `sort`, `status`, `orderby`, `target`, `bgcolor`, `create_time`, `update_time`) VALUES
(1, 1, 0, '首页轮播', 'https://bbs.lemocms.com/', '/static/addons/bbs/banner/1.jpg', 1451577600, 1767283200, '', '', '', 0, 1, 1, 0, 0, '#ff8000', 0, 1566106884),
(2, 1, 0, '首页轮播', 'https://bbs.lemocms.com/', '/static/addons/bbs/banner/2.jpg', 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, 0, '#fea8c1', 0, 0),
(3, 1, 0, '首页轮播', 'https://bbs.lemocms.com', '/static/addons/bbs/banner/1.png', 0, 0, '', '', '', 0, NULL, 1, 50, 0, NULL, NULL, NULL);



