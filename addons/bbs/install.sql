    CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_bbs` (
  `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` smallint(5) NOT NULL DEFAULT '0' COMMENT '类别ID',
  `user_id` int(11) DEFAULT NULL,
  `is_top` int(11) NOT NULL DEFAULT '0' COMMENT '是否置顶0否，1是',
  `is_reply` tinyint(1) DEFAULT '1' COMMENT '是否可回复',
  `comment_num` int(11) DEFAULT '0' COMMENT '评论数量',
  `collect_num` int(11) DEFAULT '0' COMMENT '收藏数',
  `score` int(11) NOT NULL DEFAULT '0' COMMENT '积分',
  `is_fine` int(11) DEFAULT '0' COMMENT '是否为精贴0否，1是',
  `is_solved` tinyint(4) DEFAULT '0' COMMENT '是否已结',
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT '文章标题',
  `intro` varchar(255)  DEFAULT NULL  COMMENT '文章摘要',
  `content` longtext NOT NULL,
  `author` varchar(30) NOT NULL DEFAULT '' COMMENT '文章作者',
  `author_email` varchar(60) NOT NULL DEFAULT '' COMMENT '作者邮箱',
  `keywords` varchar(255) NOT NULL DEFAULT '' COMMENT '关键字,用逗号隔开',
  `type` tinyint(1) UNSIGNED NOT NULL DEFAULT '1',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示,1:显示,0:不显示',
  `file_url` varchar(255) NOT NULL DEFAULT '' COMMENT '附件地址',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `click` int(11) DEFAULT '0' COMMENT '浏览量',
  `publish_time` int(11) DEFAULT NULL COMMENT '文章预告发布时间',
  `sort` tinyint(1) DEFAULT '0',
  `thumb` varchar(255) DEFAULT '' COMMENT '文章缩略图',
  `tags` varchar(100) NOT NULL DEFAULT '',
  `create_time` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `update_time` int(11) DEFAULT '0',
  `delete_time` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `pid` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='论坛资源表';

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_category` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(20) DEFAULT NULL COMMENT '类别名称',
  `title_alias` varchar(20) DEFAULT NULL COMMENT '别名',
  `title_type` smallint(6) DEFAULT '0' COMMENT '默认分组',
  `pid` smallint(6) DEFAULT '0' COMMENT '上级ID',
  `is_menu` tinyint(1) DEFAULT '0' COMMENT '是否导航显示',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态',
  `sort` smallint(6) DEFAULT '50' COMMENT '排序',
  `intro` varchar(255) DEFAULT NULL COMMENT '分类描述',
  `keywords` varchar(30) DEFAULT NULL COMMENT '搜索关键词',
  `create_time` int(11) NOT NULL,
  `update_time` int(11) NOT NULL,
   PRIMARY KEY (`id`),
   UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COMMENT='论坛分类表';

INSERT INTO `__PREFIX__addons_bbs_category` (`id`, `title`, `title_alias`, `title_type`, `pid`, `is_menu`, `status`, `sort`, `intro`, `keywords`, `create_time`, `update_time`) VALUES
(1, '提问', '', 1, 0, 0, 1, 0, '1233', '', 0, 1568363450),
(2, '分享', '', 1, 0, 0, 1, 2, '', '', 0, 0),
(3, '讨论', '', 1, 0, 0, 1, 2, '', '', 0, 1568279565),
(4, '建议', '', 1, 0, 0, 1, 4, '', '', 0, 0),
(5, '公告', '', 1, 0, 0, 1, 5, '', '', 0, 0),
(6, '动态', '', 1, 0, 0, 1, 6, '', '', 0, 0),
(7, 'bug反馈', 'bug反馈', 0, 0, 0, 1, 50, NULL, NULL, 1580711449, 1580711449);


CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_collect` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) NOT NULL,
  `collect` text COMMENT '收藏的json 信息',
  `bbs_id` int(1) NOT NULL COMMENT '文章id',
  `create_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COMMENT='收藏表';

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_comment` (
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



INSERT INTO `__PREFIX__addons_bbs_comment` (`id`, `user_id`, `bbs_id`, `reply_user_id`, `content`, `create_time`, `status`, `is_read`, `is_adopt`, `like_num`) VALUES
(1, 1, 4, 0, 'face[微笑] ', 1580809716, 1, 0, 0, 0),
(2, 1, 5, 0, '哪个帖子呢', 1580867958, 1, 0, 0, 0);


CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_comment_like` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT 'like user id',
  `comment_id` int(11) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='赞表';



CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_message` (
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



INSERT INTO `__PREFIX__addons_bbs_message` (`id`, `receive_id`, `send_id`, `content`, `article_id`, `type`, `is_read`, `score`, `create_time`) VALUES
(1, 11, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580634538),
(2, 1, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580710628),
(3, 12, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580718413),
(4, 11, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580781519),
(5, 1, 0, '亲爱的lemo用户,您获得了2L币！', NULL, 0, 0, '2.00', 1580789018),
(6, 1, 1, '回复了您的文章', NULL, 1, 0, NULL, 1580809716),
(7, 13, 1, '回复了您的文章', NULL, 1, 0, NULL, 1580868021);

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_user_sign` (
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



INSERT INTO `__PREFIX__addons_bbs_user_sign` (`id`, `uid`, `sign_total`, `sign_count`, `sign_last`, `sign_time`, `score`, `month_score`) VALUES
(1, 11, 2, 1, 1580781519, '1580634538,1580781519', 0, NULL),
(2, 1, 3, 3, 1580868219, '1580710628,1580789018,1580868219', 0, NULL),
(3, 12, 1, 1, 1580718413, '1580718413', 0, NULL);

CREATE TABLE IF NOT EXISTS `__PREFIX__addons_bbs_user_sign_rule` (
  `id` int(2) UNSIGNED NOT NULL AUTO_INCREMENT,
  `days` int(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '连续天数',
  `score` int(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '积分',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COMMENT='用户签到积分规则';



INSERT INTO `__PREFIX__addons_bbs_user_sign_rule` (`id`, `days`, `score`) VALUES
(1, 1, 2),
(2, 5, 3),
(3, 10, 8),
(4, 20, 15),
(5, 30, 25),
(6, 60, 40);

CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_bbs_link` (
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


INSERT INTO `__PREFIX__addons_bbs_link` (`id`, `name`, `url`, `type_id`, `email`, `qq`, `sort`, `status`, `create_time`, `update_time`) VALUES
(23, 'funadmin', 'https://www.funadmin.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566102829, 1568359676),
(25, '百度', 'https://www.baidu.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103165, 1566103165),
(26, '新浪', 'https://www.sina.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103233, 1566103233);


CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_bbs_adv_position` (
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

INSERT INTO `__PREFIX__addons_bbs_adv_position` (`id`, `position_name`, `ad_width`, `ad_height`, `position_desc`, `position_style`, `status`, `create_time`, `update_time`) VALUES
(1, '首页轮播', 0, 0, '首页轮播', NULL, 1, NULL, NULL);

CREATE TABLE IF NOT EXISTS  `__PREFIX__addons_bbs_adv` (
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

INSERT INTO `__PREFIX__addons_bbs_adv` (`id`, `pid`, `media_type`, `ad_name`, `ad_link`, `ad_image`, `start_time`, `end_time`, `link_admin`, `link_email`, `link_phone`, `click_count`, `sort`, `status`, `orderby`, `target`, `bgcolor`, `create_time`, `update_time`) VALUES
(1, 1, 0, '首页轮播', 'https://bbs.funadmin.com/', '/static/addons/bbs/banner/1.jpg', 1451577600, 1767283200, '', '', '', 0, 1, 1, 0, 0, '#ff8000', 0, 1566106884),
(2, 1, 0, '首页轮播', 'https://bbs.funadmin.com/', '/static/addons/bbs/banner/2.jpg', 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, 0, '#fea8c1', 0, 0),
(3, 1, 0, '首页轮播', 'https://bbs.funadmin.com', '/static/addons/bbs/banner/1.png', 0, 0, '', '', '', 0, NULL, 1, 50, 0, NULL, NULL, NULL);



