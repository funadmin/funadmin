-- ----------------------------
-- Table structure for __PREFIX__cms_link
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__cms_link` (
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
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_link
-- ----------------------------
INSERT INTO `__PREFIX__cms_link` VALUES ('23', 'lemocms', 'https://www.lemocms.com', '0', '994927909@qq.com', '994927909', '50', '1', '1566102829', '1577524944');
INSERT INTO `__PREFIX__cms_link` VALUES ('25', '百度', 'https://www.baidu.com', '0', '994927909@qq.com', '994927909', '50', '1', '1566103165', '1573640285');
INSERT INTO `__PREFIX__cms_link` VALUES ('26', '新浪', 'https://www.sina.com', '0', '994927909@qq.com', '994927909', '50', '1', '1566103233', '1573640288');

-- ----------------------------
-- Table structure for __PREFIX__cms_adv
-- ----------------------------
CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_adv` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '广告id',
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '广告位置ID',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '广告类型 0,图片,1 文字链接,2 视频',
  `ad_name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告名称',
  `ad_link` varchar(255) DEFAULT '' COMMENT '链接地址',
  `ad_image` mediumtext COMMENT '图片地址',
  `ad_code` mediumtext COMMENT '代码',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '投放时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `link_admin` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人',
  `link_email` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人邮箱',
  `link_phone` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人联系电话',
  `hits` mediumint(8) unsigned NOT NULL DEFAULT '0' COMMENT '点击量',
  `sort` int(20) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '是否显示',
  `target` tinyint(1) DEFAULT '0' COMMENT '是否开启浏览器新窗口',
  `bgcolor` varchar(20) DEFAULT NULL COMMENT '背景颜色',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enabled` (`status`) USING BTREE,
  KEY `position_id` (`pid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_adv
-- ----------------------------
INSERT INTO `__PREFIX__cms_adv` VALUES ('1', '1', '0', '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190703111316458.jpg', null, '1451577600', '1767283200', '', '', '', '0', '0', '1', '0', '#ff8000', '0', '1566106884');
INSERT INTO `__PREFIX__cms_adv` VALUES ('2', '1', '0', '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190702113753219.jpg', null, '1451577600', '1767283200', '', '', '', '0', '0', '1', '0', '#fea8c1', '0', '0');
INSERT INTO `__PREFIX__cms_adv` VALUES ('3', '1', '0', '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190702114153240.jpg', null, '1451577600', '1767283200', '', '', '', '0', '0', '1', '0', '#f1e6d2', '0', '0');
INSERT INTO `__PREFIX__cms_adv` VALUES ('4', '2', '0', 'news', 'javascript:void(0);', '/static/addons/cms/frontend/images/190704110314965.jpg', null, '1451577600', '1767283200', '', '', '', '0', '0', '1', '0', '#f1dcf7', '0', '1567574061');
INSERT INTO `__PREFIX__cms_adv` VALUES ('5', '4', '0', 'about', 'javascript:void(0);', '/static/addons/cms/frontend/images/190704094410753.jpg', null, '1451577600', '1767283200', '', '', '', '0', '0', '1', '0', '#000000', '0', '0');
INSERT INTO `__PREFIX__cms_adv` VALUES ('6', '4', '0', 'product', 'https://www.baidu.com', '/static/addons/cms/frontend/images/190704110314965.jpg', null, '0', '0', '', '994927909@qq.com', '', '0', '0', '1', '0', '', '1566107420', '1582681681');
INSERT INTO `__PREFIX__cms_adv` VALUES ('7', '5', '0', 'cases', 'https://www.lemocms.com', '/static/addons/cms/frontend/images/190705114610748.jpg', null, '0', '0', '', '', '', '0', null, '1', '0', null, null, null);

-- ----------------------------
-- Table structure for __PREFIX__cms_adv_position
-- ----------------------------
CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_adv_position` (
  `id` int(3) unsigned NOT NULL AUTO_INCREMENT COMMENT '表id',
  `position_name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告位置名称',
  `ad_width` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '广告位宽度',
  `ad_height` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '广告位高度',
  `position_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '广告描述',
  `position_style` mediumtext COMMENT '模板',
  `status` tinyint(1) DEFAULT '0' COMMENT '0关闭1开启',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_adv_position
-- ----------------------------
INSERT INTO `__PREFIX__cms_adv_position` VALUES ('1', 'Index页面自动增加广告位 1 ', '0', '0', 'Cart页面', '', '1', '0', '0');
INSERT INTO `__PREFIX__cms_adv_position` VALUES ('2', 'about', '0', '0', 'about', '', '1', '0', '0');
INSERT INTO `__PREFIX__cms_adv_position` VALUES ('3', 'news', '0', '0', '', null, '1', null, '1582519639');
INSERT INTO `__PREFIX__cms_adv_position` VALUES ('4', 'cases', '0', '0', '', null, '1', null, '1582519640');
INSERT INTO `__PREFIX__cms_adv_position` VALUES ('5', 'product', '0', '0', '', null, '1', null, '1582519638');


-- ----------------------------
-- Table structure for __PREFIX__cms_article
-- ----------------------------
CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `cateid` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID',
  `uid` int(8) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '用户名',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT ' ' COMMENT '关键词',
  `description` varchar(255) NOT NULL COMMENT '描述',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL DEFAULT ' ' COMMENT '标签',
  `posid` tinyint(2) unsigned NOT NULL DEFAULT '0' COMMENT '推荐位',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态',
  `recommend` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '允许评论',
  `is_read` smallint(5) NOT NULL DEFAULT '0' COMMENT '是否可阅读',
  `readfee` smallint(5) NOT NULL DEFAULT '0' COMMENT '阅读收费',
  `sort` int(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`id`,`status`,`sort`),
  KEY `cateid` (`id`,`cateid`,`status`),
  KEY `sort` (`id`,`cateid`,`status`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='__PREFIX__cms_article模型表';

-- ----------------------------
-- Records of __PREFIX__cms_article
-- ----------------------------
INSERT INTO `__PREFIX__cms_article` VALUES ('1', '16', '0', ' ', '公司简介 -lemocms', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'CMS,CMS,LEMOCMS,后台管理系统,CMS系统', 'lemocms是基于最新TP6+layui框架的后台管理系统。是一款完全开源的项目，是您轻松开发建站的首选利器。框架插件式开发,易于二次开发，插件系统帮您一键安装卸载，减少系统冗余,代码维护简单，能满足专注业务深度开发的需求。', '<p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; xxx网络科技股份有限公司是一家集策略咨询、创意创新、视觉设计、技术研发、内容制造、营销推广为一体的综合型数字化创新服务企业，其利用公司持续积累的核心技术和互联网思维，提供以互联网、移动互联网为核心的网络技术服务和互动整合营销服务，为传统企业实现“互联网+”升级提供整套解决方案。公司定位于中大型企业为核心客户群，可充分满足这一群体相比中小企业更为丰富、高端、多元的互联网数字综合需求。</p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; &nbsp; xxx网络科技股份有限公司作为一家互联网数字服务综合商，其主营业务包括移动互联网应用开发服务、数字互动整合营销服务、互联网网站建设综合服务和电子商务综合服务。</p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; &nbsp; xxx网络科技股份有限公司秉承实现全网价值营销的理念，通过实现互联网与移动互联网的精准数字营销和用户数据分析，日益深入到客户互联网技术建设及运维营销的方方面面，在帮助客户形成自身互联网运作体系的同时，有效对接BAT(百度，阿里，腾讯)等平台即百度搜索、阿里电商、腾讯微信，通过平台的推广来推进互联网综合服务，实现企业、用户、平台三者完美对接，并形成高效互动的枢纽，在帮助客户获取互联网高附加价值的同时获得自身的不断成长和壮大。</p><p><br/></p>', 'lemocms,layui', '0', '1', '0', '0', '0', '0', '12', '1582688940', '0');
INSERT INTO `__PREFIX__cms_article` VALUES ('2', '6', '0', ' ', '微信小程序和支付宝小程序的区别在哪里？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '微信小程序,支付宝小程序-lemocms', '', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">支付宝小程序和微信小程序区别在哪?支付宝小程序的新闻引起了很大的动静，作为早些发布的微信小程序，很多人将两者进行对比，微信小程序和支付宝小程序有什么不同呢?区别在哪呢?下面一起来看看对比分析。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">仔细推敲的话，支付宝的小程序与微信部分相似的同时，依然存在很大差异。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">开发基本无差别，微信小程序可以快速迁移至支付宝；</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">支付宝小程序流露出之后，就有开发者连夜尝试，结果发现，之前开发的微信小程序可以直接迁移到支付宝，只需重命名文件后缀、一些事件函数和部分API即可，此外就是后端登录系统需要改动，但整体架构一致。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">从开发成本来看，支付宝在这一点的确占了便宜。毕竟，所有运营者花成本开发的小程序，都希望在更多平台上展现。微信花时间培育的市场，支付宝就这么摘了果子。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">2.功能差别，微信小程序更加成熟</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">值得注意的是，微信小程序近期在新能力的开放上快马加鞭，一个月释放了二十多个新能力。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">任何产品的成功都是诸多客观原因共同努力的结果。不论从生态构建、产品体系搭建甚至是用户体验上，微信小程序略胜一筹。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">3.生态差别，微信用户基数大，数据更完整</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">从微信和支付宝的属性去看，微信主要场景是社交，支付宝是电商或支付，各自的小程序无疑会带有各自的属性色彩与优劣势。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">阿里巴巴是从事小程序开发的专业技术公司，提供各行业小程序开发服务，并对外开放小程序代理加盟业务。阿里巴巴是新三板上市企业，腾讯西南地区服务商，拥有十年技术经验，专注移动互联网技术研发。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">4.优势差别，微信流量优势大，支付宝仰仗B端客户</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">直白来说，蚂蚁金服更懂得 B 端商户需要什么，再将这部分资源封装成可以取用的产品，交由第三方开发者发挥。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">所以我们可以看到，蚂蚁金服在今年接连开放出诸多能力：包括向小米和华为开放 VR Pay 功能;针对金融机构开放最新的财富管理类 AI;向保险行业开放技术产品“定损宝”;开放出“无人值守”与新客服平台等等</p><p><br/></p>', 'layui,thinkphp,easywechat', '0', '1', '1', '1', '0', '0', '4', '1582689666', '1582711433');
INSERT INTO `__PREFIX__cms_article` VALUES ('3', '6', '0', ' ', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇\n', '<p>当微信成为一个月活超过10亿的超级APP时，它在许多人的心目中就变成了一个拥有巨大流量的“蓄水池”。这对于求流量而不得的商家来说是一个巨大的诱惑。&nbsp; 而小程序作为一个2B2C的入口，可以把人、商品、服务用一种轻型的APP的形式承载。腾讯大力开发小程序能起到一个连接的作用，使越来越多的商家与微信生态发生联系，最后的支付环节也能与微信支付连接，这对腾讯在智慧零售的变革中崛起发挥着重要作用。</p>', '', '0', '1', '1', '1', '0', '0', '63', '1582689774', '0');
INSERT INTO `__PREFIX__cms_article` VALUES ('4', '6', '0', ' ', '为什么要以小程序为基础搭建商城？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '为什么要以小程序为基础搭建商城？', '为什么要以小程序为基础搭建商城？\n', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">小程序自诞生以来，就以方便著称，在今年，微信更是加大了对小程序的重视程度，更新频繁，而小程序也在市场上掀起一阵热潮，各种小程序商城纷纷推广开来，那么究竟小程序有什么优势，让商城建设一定要依托其上?我们一起来看看。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">先要了解，微信小程序商城到底是什么?</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">微信官方解释：小程序是一种不需要下载安装即可使用的应用，它实现了应用“触手可及”的梦想，用户扫一扫或者搜一下即可打开应用。也体现了“用完即走”的理念，用户不用关心是否安装太多应用的问题。应用将无处不在，随时可用，但又无需安装卸载。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">划重点：无需下载安装、用完即走</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">有了这两点，这就意味着，和以往的商城平台不同，无需额外的冗陈操作，只需扫一扫或者搜索相关名字即可</p><p><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', '', '0', '1', '1', '1', '0', '0', '2', '1582689808', '0');
INSERT INTO `__PREFIX__cms_article` VALUES ('5', '14', '0', ' ', '响应式网站有什么优势和价值？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '响应式网站有什么优势和价值？', '响应式网站有什么优势和价值？', '<p><span style=\"color: rgb(42, 42, 42); font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; text-align: justify; background-color: rgb(255, 255, 255);\">“我的要求很简单，不需要你把官网做得有多精致，只要客户能够在网上找到我们，知道我们是做什么的就行。”有很多传统企业大佬现在还是这个执念，觉得我们之前没有网络业务也是挺好的，现在是互联网时代，那我也跟着随便做一个就行了，至于后续能有多大效果，我也无所谓。其实他们不知道，随着网络的发达，很多这种简单的企业官网现在已经成为了僵尸网站，你不追求完美精致，别人追求，别人的网站就把你的网站淹没在网络洪流里。</span><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', 'tp6,layui', '0', '1', '1', '1', '0', '0', '17', '1582689873', '0');

-- ----------------------------
-- Table structure for __PREFIX__cms_category
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__cms_category` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `catename` varchar(255) NOT NULL DEFAULT '' COMMENT '栏目名字',
  `catedir` varchar(30) NOT NULL DEFAULT '' COMMENT '栏目唯一标识',
  `pid` smallint(5) unsigned NOT NULL DEFAULT '0',
  `arrpid` varchar(100) DEFAULT '0',
  `arrchildid` varchar(100) NOT NULL DEFAULT ''''' ',
  `moduleid` tinyint(2) unsigned NOT NULL DEFAULT '0' COMMENT '模型id',
  `module` char(24) NOT NULL DEFAULT '' COMMENT '模型名字',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 单页，0 普通列表  2外链',
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT '标题',
  `keywords` varchar(200) NOT NULL DEFAULT '' COMMENT '关键字',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` text COMMENT '介绍',
  `sort` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `is_menu` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否菜单',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '是否显示',
  `hits` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '点击量',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `url` varchar(100) NOT NULL DEFAULT '' COMMENT '外部链接地址',
  `template_list` varchar(50) NOT NULL DEFAULT '',
  `template_show` varchar(50) NOT NULL,
  `page_size` tinyint(4) NOT NULL DEFAULT '15',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `listorder` (`sort`),
  KEY `pid` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_category
-- ----------------------------
INSERT INTO `__PREFIX__cms_category` VALUES ('5', '新闻动态', 'dongtai', '0', '0', '5,1,6,14', '19', 'cms_article', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '100', '', '', 'list_article.html', 'show_article.html', '15', '1579344001', '1582684679');
INSERT INTO `__PREFIX__cms_category` VALUES ('6', '最新资讯', 'news', '5', '0,5', '6', '19', 'cms_article', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '100', '', '', 'list_article.html', 'show_article.html', '15', '1579344044', '1582684698');
INSERT INTO `__PREFIX__cms_category` VALUES ('10', '小程序', 'minpro', '13', '0,13', '10', '18', 'cms_product', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '0', '', '', 'list_product.html', 'show_page.html', '15', '0', '1582684670');
INSERT INTO `__PREFIX__cms_category` VALUES ('11', '微信', 'weixin', '13', '0,13', '11', '18', 'cms_product', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '0', '', '', 'list_product.html', 'show_page.html', '15', '0', '1582684662');
INSERT INTO `__PREFIX__cms_category` VALUES ('12', '商城', 'shop', '13', '0,13', '12', '18', 'cms_product', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '0', '', '', 'list_product.html', 'show_page.html', '15', '0', '1582684653');
INSERT INTO `__PREFIX__cms_category` VALUES ('13', '产品服务', 'product', '0', '0', '13,2,3,4,10,11,12', '18', 'cms_product', '0', '服务产品', '服务产品', '服务产品', null, '50', '1', '1', '100', '', '', 'list_product.html', 'show_page.html', '15', '1582536226', '1582684613');
INSERT INTO `__PREFIX__cms_category` VALUES ('14', '行业咨询', 'news', '5', '0,5', '14', '19', 'cms_article', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', '<p>asdf</p>', '50', '1', '1', '100', '', '', 'list_article.html', 'show_article.html', '15', '1582536300', '1582684687');
INSERT INTO `__PREFIX__cms_category` VALUES ('15', '精彩案例', 'cases', '0', '0', '15', '20', 'cms_picture', '0', 'lemocms', 'lemocms,layui', 'lemocms,layui,插件开发', null, '50', '1', '1', '100', '', '', 'list_pic.html', 'show_article.html', '15', '1582536497', '1582684602');
INSERT INTO `__PREFIX__cms_category` VALUES ('16', '关于我们', 'about', '0', '0', '16', '19', 'cms_article', '1', '基于TP6 layui开发的cms 后台管理系统', '关于我们-lemocms', '关于我们-lemocms', '<p>深圳市<span style=\"color: rgb(102, 102, 102); font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; text-align: justify; widows: 1;\">科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商；</span></p>', '50', '1', '1', '0', '', '', 'list.html', 'show_about.html', '15', '1582684581', '1582684581');

-- ----------------------------
-- Table structure for __PREFIX__cms_debris
-- ----------------------------
CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_debris` (
  `id` int(6) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(6) DEFAULT NULL COMMENT '碎片分类ID',
  `title` varchar(120) DEFAULT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `sort` int(11) DEFAULT '50' COMMENT '排序',
  `url` varchar(120) DEFAULT '' COMMENT '链接',
  `image` varchar(120) DEFAULT '' COMMENT '图片',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_debris
-- ----------------------------
INSERT INTO `__PREFIX__cms_debris` VALUES ('1', '1', '底部版权', '<p style=\"text-align: center;\">Copyright © 2018-2019. LEMOCMS | 备案：湘ICP备18009588号 | Powered by LEMOCMS</p>', '50', '', '', '1', '1579333649', '1582616299');


CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_debris_type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(120) DEFAULT NULL,
  `sort` int(1) DEFAULT '50',
  `status` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_debris_type
-- ----------------------------
INSERT INTO `__PREFIX__cms_debris_type` VALUES ('1', '底部版权', '1', '1');

-- ----------------------------
-- Table structure for __PREFIX__cms_download
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__cms_field` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `diyformid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '自定义表单id',
  `moduleid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '模型id',
  `field` varchar(20) NOT NULL DEFAULT '' COMMENT '字段',
  `name` varchar(30) NOT NULL DEFAULT '' COMMENT '名字',
  `required` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '是否必须',
  `minlength` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最少长度',
  `maxlength` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '最大长度',
  `rule` varchar(255) NOT NULL DEFAULT '' COMMENT '规则',
  `msg` varchar(255) NOT NULL DEFAULT '' COMMENT '错误提示',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '字段类型',
  `is_search` tinyint(1) DEFAULT '0' COMMENT '是否可以搜索 0  不可以，1 搜索',
  `value` varchar(50) DEFAULT NULL,
  `field_define` varchar(100) DEFAULT NULL,
  `option` text COMMENT '默认值',
  `sort` int(10) unsigned NOT NULL DEFAULT '0',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_field
-- ----------------------------
INSERT INTO `__PREFIX__cms_field` VALUES ('75','0', '18', 'cateid', '栏目', '1', '0', '6', '', '必须选择一个栏目', 'cateid', '0', '', null, '', '1', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('76','0', '18', 'title', '标题', '1', '0', '180', '', '标题必须为1-180个字符', 'text', '1', '', null, '', '4', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('77','0', '18', 'keywords', '关键词', '1', '0', '120', '', '', 'text', '1', '', null, '', '4', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('78','0', '18', 'description', 'SEO简介', '1', '0', '0', '', '', 'textarea', '1', '', null, '', '5', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('79','0', '18', 'tags', '标签', '0', '0', '0', '', '', 'text', '1', '', null, '', '6', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('80','0', '18', 'thumb', '缩略图', '1', '0', '255', '', '缩略图', 'image', '0', '', null, '', '2', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('81','0', '18', 'content', '内容', '0', '0', '0', '', '', 'editor', '1', 'ueditor', null, '0:ueditor', '7', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('82','0', '18', 'status', '状态', '1', '0', '1', '', '', 'radio', '0', '1', null, '0:禁用\r\n1:启用', '8', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('83','0', '18', 'sort', '排序', '1', '0', '1', '', '', 'text', '0', '1', null, '50', '9', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('84','0', '18', 'hits', '点击次数', '0', '0', '8', '', '', 'number', '0', '', null, '', '10', '1', '1582683760', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('85','0', '19', 'cateid', '栏目', '1', '0', '6', '', '必须选择一个栏目', 'cateid', '0', '', null, '', '1', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('86','0', '19', 'title', '标题', '1', '0', '80', '', '标题必须为1-80个字符', 'text', '1', '', null, '', '2', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('87','0', '19', 'keywords', '关键词', '1', '0', '200', '', '关键词必须在0-200个内', 'text', '1', '', null, '', '3', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('88','0', '19', 'description', 'SEO简介', '1', '0', '0', '', '', 'textarea', '1', '', null, '', '4', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('89','0', '19', 'thumb', '缩略图', '1', '0', '255', '', '缩略图', 'image', '0', '', null, '', '1', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('90','0', '19', 'content', '内容', '0', '0', '255', '', '', 'editor', '1', 'ueditor', null, '0:ueditor', '5', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('91','0','19', 'status', '状态', '1', '0', '1', '', '', 'radio', '0', '1', null, '0:未发布\r\n 1:发布', '7', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('92','0','19', 'recommend', '允许评论', '0', '0', '1', '', '', 'radio', '0', '1', null, '0:禁止评论\r\n 1:允许评论', '8', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('93','0','19', 'readfee', '阅读收费', '0', '0', '5', '', '', 'number', '0', '0', null, '', '9', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('94','0','19', 'is_read', '是否可阅读', '0', '0', '1', '', '', 'radio', '0', '1', null, '0:禁止 \r\n 1:允许', '9', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('95','0','19', 'hits', '点击次数', '0', '0', '8', '', '', 'number', '1', '1', null, '', '10', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('96','0','19', 'posid', '推荐位', '0', '0', '1', '', '', 'posid', '0', '', null, '1:置顶 \r\n2:热门\r\n3:头条', '12', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('97','0', '19', 'tags', '标签', '0', '0', '255', '', '', 'text', '1', '', null, '', '14', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('98','0', '19', 'sort', '排序', '0', '0', '50', '', '', 'text', '0', '', null, '', '14', '1', '1582684044', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('99','0', '20', 'cateid', '栏目', '1', '0', '6', '', '必须选择一个栏目', 'cateid', '0', '', null, '', '1', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('100','0', '20', 'title', '标题', '1', '0', '180', '', '标题必须为1-180个字符', 'text', '1', '', null, '', '4', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('101','0', '20', 'keywords', '关键词', '1', '0', '120', '', '', 'text', '1', '', null, '', '4', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('102','0', '20', 'description', 'SEO简介', '1', '0', '0', '', '', 'textarea', '1', '', null, '', '5', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('103','0', '20', 'tags', '标签', '0', '0', '0', '', '', 'text', '1', '', null, '', '6', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('104','0', '20', 'thumb', '缩略图', '1', '0', '255', '', '缩略图', 'image', '0', '', null, '', '2', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('105','0', '20', 'content', '内容', '0', '0', '0', '', '', 'editor', '1', 'ueditor', null, '0:ueditor', '7', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('106','0', '20', 'status', '状态', '1', '0', '1', '', '', 'radio', '0', '1', null, '0:禁用\r\n1:启用', '8', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('107','0', '20', 'sort', '排序', '1', '0', '1', '', '', 'text', '0', '1', null, '50', '9', '1', '1582684060', '0');
INSERT INTO `__PREFIX__cms_field` VALUES ('108','0', '20', 'hits', '点击次数', '0', '0', '8', '', '', 'number', '0', '', null, '', '10', '1', '1582684060', '0');

-- ----------------------------
-- Table structure for __PREFIX__cms_module
-- ----------------------------
CREATE TABLE IF NOT EXISTS  `__PREFIX__cms_module` (
  `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '模型名称',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '表名',
  `description` varchar(200) NOT NULL DEFAULT '' COMMENT '描述',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 空白，1 文章',
  `ispage` tinyint(1) DEFAULT '0' COMMENT '是否单页',
  `listfields` varchar(255) NOT NULL DEFAULT '' COMMENT '列表页查询字段',
  `template` varchar(255) NOT NULL DEFAULT ' ',
  `sort` smallint(3) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态',
  `create_time` int(11) DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COMMENT='模型表';

-- ----------------------------
-- Records of __PREFIX__cms_module
-- ----------------------------
INSERT INTO `__PREFIX__cms_module` VALUES ('18', 'cms_product', 'cms_product', 'cms_product', '0', '0', '*', '', '50', '1', '1582683759', '1582683759');
INSERT INTO `__PREFIX__cms_module` VALUES ('19', 'cms_article', 'cms_article', '文章', '0', '0', '*', '', '50', '1', '1582684043', '1582684043');
INSERT INTO `__PREFIX__cms_module` VALUES ('20', 'cms_picture', 'cms_picture', '图片', '0', '0', '*', '', '50', '1', '1582684060', '1582684060');

-- ----------------------------
-- Table structure for __PREFIX__cms_picture
-- ----------------------------
CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_picture` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发布人id',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '发布人',
  `cateid` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '栏目id',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `title_style` varchar(255) NOT NULL COMMENT '标题样式',
  `thumb` varchar(255) NOT NULL DEFAULT ' ' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT '' COMMENT '关键词',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL COMMENT '标签',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`id`,`status`,`sort`),
  KEY `cateid` (`id`,`cateid`,`status`),
  KEY `sort` (`id`,`cateid`,`status`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='__PREFIX__cms_picture模型表';

-- ----------------------------
-- Records of __PREFIX__cms_picture
-- ----------------------------
INSERT INTO `__PREFIX__cms_picture` VALUES ('1', '0', ' ', '15', 'XXXXXXXXXXX微信电商公众号商城开发服务', '', '/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg', '服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等；', '服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等；</p><p><img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '7', '1582690082', '0');
INSERT INTO `__PREFIX__cms_picture` VALUES ('2', '0', ' ', '15', 'GitHub 安全警告计划已检测出 400 多万个漏洞', '', '/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg', 'Github 去年推出的安全警告，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者', 'Github 去年推出的安全警告，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者转到安全版本。\n\n', '<p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\"><span style=\"color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; background-color: rgb(255, 255, 255);\">Github 去年推出的</span><a href=\"https://www.oschina.net/news/90737/security-alerts-on-github\" style=\"box-sizing: border-box; background-color: rgb(255, 255, 255); color: rgb(85, 85, 85); text-decoration-line: none; font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal;\">安全警告</a><span style=\"color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; background-color: rgb(255, 255, 255);\">，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。</span><strong style=\"box-sizing: border-box; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者转到安全版本。</strong></p><p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">根据 Github 的说法，目前安全警告已经报告了 50 多万个库中的 400 多万个漏洞。在所有显示的警告中，有将近一半的在一周之内得到了响应，前7天的漏洞解决率大约为30%。实际上，情况可能更好，因为当把统计限制在最近有贡献的库时，也就是说过去90天中有贡献的库，98%的库在7天之内打上了补丁。</p><p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">这个安全警报服务会扫描所有公共库，对于私有库，只扫描依赖图。每当发现有漏洞，库管理员都可以收到消息提示，其中还有漏洞级别及解决步骤提供。</p><p><br/></p>', 'tp6,lemocms,', '1', '1', '0', '1582710692', '0');

-- ----------------------------
-- Table structure for __PREFIX__cms_product
-- ----------------------------
CREATE TABLE IF NOT EXISTS  `__PREFIX__cms_product` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `uid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '发布人id',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '发布人',
  `cateid` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '栏目id',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `title_style` varchar(255) NOT NULL COMMENT '标题样式',
  `thumb` varchar(255) NOT NULL DEFAULT ' ' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT '' COMMENT '关键词',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL COMMENT '标签',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int(5) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `status` (`id`,`status`,`sort`),
  KEY `cateid` (`id`,`cateid`,`status`),
  KEY `sort` (`id`,`cateid`,`status`,`sort`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COMMENT='__PREFIX__cms_product模型表';

-- ----------------------------
-- Records of __PREFIX__cms_product
-- ----------------------------
INSERT INTO `__PREFIX__cms_product` VALUES ('1', '0', ' ', '10', '小程序开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '1', '1582689212', '1582689264');
INSERT INTO `__PREFIX__cms_product` VALUES ('2', '0', ' ', '10', '微信商城', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'lemocms-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '6', '1582689444', '0');
INSERT INTO `__PREFIX__cms_product` VALUES ('3', '0', ' ', '10', '企业小程序', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'lemocms-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '0', '1582689444', '0');
INSERT INTO `__PREFIX__cms_product` VALUES ('4', '0', ' ', '11', '微信开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'lemocms-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '0', '1582689444', '0');
INSERT INTO `__PREFIX__cms_product` VALUES ('5', '0', ' ', '12', '商城开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'lemocms-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', '1', '1', '0', '1582689444', '0');

-- ----------------------------
-- Table structure for __PREFIX__cms_tags
-- ----------------------------
CREATE TABLE IF NOT EXISTS `__PREFIX__cms_tags` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `hits` int(11) DEFAULT '0',
  `nums` int(11) DEFAULT '0',
  `article_ids` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_tags
-- ----------------------------
INSERT INTO `__PREFIX__cms_tags` VALUES ('5', 'lemocms', '8', '1', null);
INSERT INTO `__PREFIX__cms_tags` VALUES ('6', 'layui', '2', '3', ',2');
INSERT INTO `__PREFIX__cms_tags` VALUES ('7', 'tp6', '0', '0', null);
INSERT INTO `__PREFIX__cms_tags` VALUES ('8', 'thinkphp', '0', '2', ',2');
INSERT INTO `__PREFIX__cms_tags` VALUES ('9', 'easywechat', '0', '1', ',2');

CREATE TABLE   IF NOT EXISTS `__PREFIX__cms_diyform` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(30) NOT NULL COMMENT '表单名称',
  `title` varchar(100) DEFAULT NULL COMMENT '	表单标题',
  `seotitle` varchar(255) DEFAULT NULL,
  `keywords` varchar(100) DEFAULT NULL COMMENT '关键字',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `tablename` varchar(30) DEFAULT NULL COMMENT '表名',
  `fields` varchar(255) DEFAULT NULL COMMENT '字段列表',
  `needlogin` tinyint(1) DEFAULT '0' COMMENT '是否需要登录 0 不需要',
  `successtips` varchar(50) DEFAULT NULL COMMENT '成功提示文字',
  `redirecturl` varchar(120) DEFAULT NULL COMMENT '成功后跳转链接	',
  `template` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `createtime` int(11) DEFAULT NULL,
  `updatetime` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of __PREFIX__cms_diyform
-- ----------------------------
INSERT INTO `__PREFIX__cms_diyform` VALUES ('1', '留言', '留言', '留言', '留言', '留言', 'cms_message', 'username,mobile,wechat,', '0', null, null, null, null, null, null);


CREATE TABLE  IF NOT EXISTS `__PREFIX__cms_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(30) DEFAULT NULL,
  `company` varchar(50) DEFAULT NULL,
  `mobile` varchar(11) DEFAULT NULL,
  `wechat` varchar(50) DEFAULT NULL,
  `content` varchar(150) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COMMENT='留言表';

-- ----------------------------
-- Records of __PREFIX__cms_message
-- ----------------------------
INSERT INTO `__PREFIX__cms_message` VALUES ('1', 'ce', 'ce', 'ce', 'e', 'e', null, null);

