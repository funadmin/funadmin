-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- 主机： localhost:3306
-- 生成日期： 2020-11-01 20:29:06
-- 服务器版本： 5.7.26-log
-- PHP 版本： 7.4.9

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `funadmin`
--

-- --------------------------------------------------------

--
-- 表的结构 `fun_addon`
--

CREATE TABLE `fun_addon` (
  `id` int(11) NOT NULL COMMENT '主键',
  `title` varchar(20) NOT NULL DEFAULT '' COMMENT '中文名',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '插件名或标识',
  `images` varchar(200) DEFAULT '' COMMENT '封面',
  `group` varchar(20) DEFAULT '' COMMENT '组别',
  `description` varchar(1000) DEFAULT '' COMMENT '插件描述',
  `author` varchar(40) DEFAULT '' COMMENT '作者',
  `version` varchar(20) DEFAULT '' COMMENT '版本号',
  `require` varchar(50) NOT NULL DEFAULT ' ' COMMENT '需求版本',
  `website` varchar(200) NOT NULL DEFAULT ' ',
  `is_hook` tinyint(1) DEFAULT '0' COMMENT '钩子[0:不支持;1:支持]',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态[-1:删除;0:禁用;1启用]',
  `create_time` int(10) UNSIGNED DEFAULT NULL COMMENT '创建时间',
  `update_time` int(10) UNSIGNED DEFAULT NULL COMMENT '修改时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公用_插件表';

--
-- 转存表中的数据 `fun_addon`
--

INSERT INTO `fun_addon` (`id`, `title`, `name`, `images`, `group`, `description`, `author`, `version`, `require`, `website`, `is_hook`, `status`, `create_time`, `update_time`) VALUES
(11, '数据库管理', 'database', '', '', '数据库插件-FunAdmin数据库管理插件', 'yuege', '0.1', '0.1', '', 0, 1, 1603601991, 1603601991),
(21, 'cms管理系统', 'cms', '', '', 'cms管理插件', 'yuege', '0.1', '0.1', '', 0, 1, 1603606021, 1603606021);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_adv`
--

CREATE TABLE `fun_addons_cms_adv` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '广告id',
  `pid` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位置ID',
  `type` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告类型 0,图片,1 链接,2 视频',
  `name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告名称',
  `url` varchar(255) DEFAULT '' COMMENT '链接地址',
  `path` mediumtext COMMENT '文件地址',
  `code` mediumtext COMMENT '代码',
  `start_time` int(11) NOT NULL DEFAULT '0' COMMENT '投放时间',
  `end_time` int(11) NOT NULL DEFAULT '0' COMMENT '结束时间',
  `admin_id` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人',
  `email` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人邮箱',
  `phone` varchar(60) NOT NULL DEFAULT '' COMMENT '添加人联系电话',
  `hits` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击量',
  `sort` int(20) DEFAULT NULL,
  `status` tinyint(3) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示',
  `target` tinyint(1) DEFAULT '0' COMMENT '是否开启浏览器新窗口',
  `bgcolor` varchar(20) DEFAULT NULL COMMENT '背景颜色',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_adv`
--

INSERT INTO `fun_addons_cms_adv` (`id`, `pid`, `type`, `name`, `url`, `path`, `code`, `start_time`, `end_time`, `admin_id`, `email`, `phone`, `hits`, `sort`, `status`, `target`, `bgcolor`, `create_time`, `update_time`) VALUES
(1, 1, 0, '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190703111316458.jpg', NULL, 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, '#ff8000', 0, 1566106884),
(2, 1, 0, '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190702113753219.jpg', NULL, 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, '#fea8c1', 0, 0),
(3, 1, 0, '首页', 'javascript:void(0);', '/static/addons/cms/frontend/images/190702114153240.jpg', NULL, 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, '#f1e6d2', 0, 0),
(4, 2, 0, 'news', 'javascript:void(0);', '/static/addons/cms/frontend/images/190704110314965.jpg', NULL, 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, '#f1dcf7', 0, 1567574061),
(5, 4, 0, 'about', 'javascript:void(0);', '/static/addons/cms/frontend/images/190704094410753.jpg', NULL, 1451577600, 1767283200, '', '', '', 0, 0, 1, 0, '#000000', 0, 0),
(6, 4, 0, 'product', 'https://www.baidu.com', '/static/addons/cms/frontend/images/190704110314965.jpg', NULL, 0, 0, '', '994927909@qq.com', '', 0, 0, 1, 0, '', 1566107420, 1582681681),
(7, 5, 0, 'cases', 'https://www.funadmin.com', '/static/addons/cms/frontend/images/190705114610748.jpg', NULL, 0, 0, '', '', '', 0, NULL, 1, 0, NULL, NULL, NULL),
(8, 1, 2, 'li ming yue', 'https://swapptest.singlewindow.cn/ceb2grab/grab/realTimeDataUpload', NULL, NULL, 0, 0, '', '', '', 0, NULL, 1, 0, NULL, 1604134926, 1604134926);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_adv_position`
--

CREATE TABLE `fun_addons_cms_adv_position` (
  `id` int(3) UNSIGNED NOT NULL COMMENT '表id',
  `name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告位置名称',
  `width` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位宽度',
  `height` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位高度',
  `intro` varchar(255) NOT NULL DEFAULT '' COMMENT '广告描述简洁',
  `style` mediumtext COMMENT '模板样式',
  `status` tinyint(1) DEFAULT '0' COMMENT '0关闭1开启',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  `delete_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间\r\n'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_adv_position`
--

INSERT INTO `fun_addons_cms_adv_position` (`id`, `name`, `width`, `height`, `intro`, `style`, `status`, `create_time`, `update_time`, `delete_time`) VALUES
(1, 'Index页面自动增加广告位 1 ', 0, 0, 'Cart页面', '', 1, 0, 0, 0),
(2, 'about', 0, 0, 'about', '', 1, 0, 0, 0),
(3, 'news', 0, 0, '', NULL, 1, NULL, 1582519639, 0),
(4, 'cases', 0, 0, '', NULL, 1, NULL, 1582519640, 0),
(5, 'product', 0, 0, '', NULL, 1, NULL, 1582519638, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_article`
--

CREATE TABLE `fun_addons_cms_article` (
  `id` int(11) UNSIGNED NOT NULL COMMENT 'ID',
  `cateid` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '分类ID',
  `uid` int(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '用户ID',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '用户名',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT ' ' COMMENT '关键词',
  `intro` varchar(255) NOT NULL COMMENT '简介',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL DEFAULT ' ' COMMENT '标签',
  `posid` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '推荐位',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态',
  `recommend` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '允许评论',
  `is_read` smallint(5) NOT NULL DEFAULT '0' COMMENT '是否可阅读',
  `readfee` smallint(5) NOT NULL DEFAULT '0' COMMENT '阅读收费',
  `sort` int(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='fun_addons_cms_article模型表';

--
-- 转存表中的数据 `fun_addons_cms_article`
--

INSERT INTO `fun_addons_cms_article` (`id`, `cateid`, `uid`, `username`, `title`, `thumb`, `keywords`, `intro`, `content`, `tags`, `posid`, `status`, `recommend`, `is_read`, `readfee`, `sort`, `hits`, `create_time`, `update_time`) VALUES
(1, 16, 0, ' ', '公司简介 -funadmin', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'CMS,CMS,funadmin,后台管理系统,CMS系统', 'funadmin是基于最新TP6+layui框架的后台管理系统。是一款完全开源的项目，是您轻松开发建站的首选利器。框架插件式开发,易于二次开发，插件系统帮您一键安装卸载，减少系统冗余,代码维护简单，能满足专注业务深度开发的需求。', '<p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; xxx网络科技股份有限公司是一家集策略咨询、创意创新、视觉设计、技术研发、内容制造、营销推广为一体的综合型数字化创新服务企业，其利用公司持续积累的核心技术和互联网思维，提供以互联网、移动互联网为核心的网络技术服务和互动整合营销服务，为传统企业实现“互联网+”升级提供整套解决方案。公司定位于中大型企业为核心客户群，可充分满足这一群体相比中小企业更为丰富、高端、多元的互联网数字综合需求。</p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; &nbsp; xxx网络科技股份有限公司作为一家互联网数字服务综合商，其主营业务包括移动互联网应用开发服务、数字互动整合营销服务、互联网网站建设综合服务和电子商务综合服务。</p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"margin-top: 0px; margin-bottom: 0px; padding: 0px; color: rgb(102, 102, 102); font-family: &quot;microsoft yahei&quot;, Arial; white-space: normal; background-color: rgb(255, 255, 255);\">&nbsp; &nbsp; xxx网络科技股份有限公司秉承实现全网价值营销的理念，通过实现互联网与移动互联网的精准数字营销和用户数据分析，日益深入到客户互联网技术建设及运维营销的方方面面，在帮助客户形成自身互联网运作体系的同时，有效对接BAT(百度，阿里，腾讯)等平台即百度搜索、阿里电商、腾讯微信，通过平台的推广来推进互联网综合服务，实现企业、用户、平台三者完美对接，并形成高效互动的枢纽，在帮助客户获取互联网高附加价值的同时获得自身的不断成长和壮大。</p><p><br/></p>', 'funadmin,layui', 0, 1, 0, 0, 0, 0, 12, 1582688940, 0),
(2, 6, 0, ' ', '微信小程序和支付宝小程序的区别在哪里？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '微信小程序,支付宝小程序-funadmin', '', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">支付宝小程序和微信小程序区别在哪?支付宝小程序的新闻引起了很大的动静，作为早些发布的微信小程序，很多人将两者进行对比，微信小程序和支付宝小程序有什么不同呢?区别在哪呢?下面一起来看看对比分析。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">仔细推敲的话，支付宝的小程序与微信部分相似的同时，依然存在很大差异。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\"><br/></p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">开发基本无差别，微信小程序可以快速迁移至支付宝；</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">支付宝小程序流露出之后，就有开发者连夜尝试，结果发现，之前开发的微信小程序可以直接迁移到支付宝，只需重命名文件后缀、一些事件函数和部分API即可，此外就是后端登录系统需要改动，但整体架构一致。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">从开发成本来看，支付宝在这一点的确占了便宜。毕竟，所有运营者花成本开发的小程序，都希望在更多平台上展现。微信花时间培育的市场，支付宝就这么摘了果子。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">2.功能差别，微信小程序更加成熟</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">值得注意的是，微信小程序近期在新能力的开放上快马加鞭，一个月释放了二十多个新能力。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">任何产品的成功都是诸多客观原因共同努力的结果。不论从生态构建、产品体系搭建甚至是用户体验上，微信小程序略胜一筹。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">3.生态差别，微信用户基数大，数据更完整</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">从微信和支付宝的属性去看，微信主要场景是社交，支付宝是电商或支付，各自的小程序无疑会带有各自的属性色彩与优劣势。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">阿里巴巴是从事小程序开发的专业技术公司，提供各行业小程序开发服务，并对外开放小程序代理加盟业务。阿里巴巴是新三板上市企业，腾讯西南地区服务商，拥有十年技术经验，专注移动互联网技术研发。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">4.优势差别，微信流量优势大，支付宝仰仗B端客户</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">直白来说，蚂蚁金服更懂得 B 端商户需要什么，再将这部分资源封装成可以取用的产品，交由第三方开发者发挥。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">所以我们可以看到，蚂蚁金服在今年接连开放出诸多能力：包括向小米和华为开放 VR Pay 功能;针对金融机构开放最新的财富管理类 AI;向保险行业开放技术产品“定损宝”;开放出“无人值守”与新客服平台等等</p><p><br/></p>', 'layui,thinkphp,easywechat', 0, 1, 1, 1, 0, 0, 4, 1582689666, 1582711433),
(3, 6, 0, ' ', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇', 'BAT打响小程序争夺战，电商类SaaS应用或迎新机遇\n', '<p>当微信成为一个月活超过10亿的超级APP时，它在许多人的心目中就变成了一个拥有巨大流量的“蓄水池”。这对于求流量而不得的商家来说是一个巨大的诱惑。&nbsp; 而小程序作为一个2B2C的入口，可以把人、商品、服务用一种轻型的APP的形式承载。腾讯大力开发小程序能起到一个连接的作用，使越来越多的商家与微信生态发生联系，最后的支付环节也能与微信支付连接，这对腾讯在智慧零售的变革中崛起发挥着重要作用。</p>', '', 0, 1, 1, 1, 0, 0, 63, 1582689774, 0),
(4, 6, 0, ' ', '为什么要以小程序为基础搭建商城？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '为什么要以小程序为基础搭建商城？', '为什么要以小程序为基础搭建商城？\n', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">小程序自诞生以来，就以方便著称，在今年，微信更是加大了对小程序的重视程度，更新频繁，而小程序也在市场上掀起一阵热潮，各种小程序商城纷纷推广开来，那么究竟小程序有什么优势，让商城建设一定要依托其上?我们一起来看看。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">先要了解，微信小程序商城到底是什么?</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">微信官方解释：小程序是一种不需要下载安装即可使用的应用，它实现了应用“触手可及”的梦想，用户扫一扫或者搜一下即可打开应用。也体现了“用完即走”的理念，用户不用关心是否安装太多应用的问题。应用将无处不在，随时可用，但又无需安装卸载。</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">划重点：无需下载安装、用完即走</p><p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">有了这两点，这就意味着，和以往的商城平台不同，无需额外的冗陈操作，只需扫一扫或者搜索相关名字即可</p><p><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', '', 0, 1, 1, 1, 0, 0, 2, 1582689808, 0),
(5, 14, 0, ' ', '响应式网站有什么优势和价值？', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '响应式网站有什么优势和价值？', '响应式网站有什么优势和价值？', '<p><span style=\"color: rgb(42, 42, 42); font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; text-align: justify; background-color: rgb(255, 255, 255);\">“我的要求很简单，不需要你把官网做得有多精致，只要客户能够在网上找到我们，知道我们是做什么的就行。”有很多传统企业大佬现在还是这个执念，觉得我们之前没有网络业务也是挺好的，现在是互联网时代，那我也跟着随便做一个就行了，至于后续能有多大效果，我也无所谓。其实他们不知道，随着网络的发达，很多这种简单的企业官网现在已经成为了僵尸网站，你不追求完美精致，别人追求，别人的网站就把你的网站淹没在网络洪流里。</span><img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', 'tp6,layui', 0, 1, 1, 1, 0, 0, 17, 1582689873, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_category`
--

CREATE TABLE `fun_addons_cms_category` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `catename` varchar(255) NOT NULL DEFAULT '' COMMENT '栏目名字',
  `cateflag` varchar(30) NOT NULL DEFAULT '' COMMENT '栏目唯一标识',
  `pid` smallint(5) UNSIGNED NOT NULL DEFAULT '0',
  `arrpid` varchar(100) DEFAULT '0',
  `arrchildid` varchar(100) NOT NULL DEFAULT ''''' ',
  `moduleid` tinyint(2) UNSIGNED NOT NULL DEFAULT '0' COMMENT '模型id',
  `module` char(24) NOT NULL DEFAULT '' COMMENT '模型名字',
  `type` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '1 单页，0 普通列表  2外链',
  `title` varchar(150) NOT NULL DEFAULT '' COMMENT '标题',
  `keywords` varchar(200) NOT NULL DEFAULT '' COMMENT '关键字',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` text COMMENT '介绍',
  `sort` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '排序',
  `is_menu` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否菜单',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示',
  `hits` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击量',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `url` varchar(100) NOT NULL DEFAULT '' COMMENT '外部链接地址',
  `template_list` varchar(50) NOT NULL DEFAULT '',
  `template_show` varchar(50) NOT NULL,
  `page_size` tinyint(4) NOT NULL DEFAULT '15',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_category`
--

INSERT INTO `fun_addons_cms_category` (`id`, `catename`, `cateflag`, `pid`, `arrpid`, `arrchildid`, `moduleid`, `module`, `type`, `title`, `keywords`, `description`, `content`, `sort`, `is_menu`, `status`, `hits`, `thumb`, `url`, `template_list`, `template_show`, `page_size`, `create_time`, `update_time`) VALUES
(5, '新闻动态', 'dongtai', 0, '0', '5,1,6,14', 19, 'cms_article', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 100, '', '', 'list_article.html', 'show_article.html', 15, 1579344001, 1582684679),
(6, '最新资讯', 'news', 5, '0,5', '6', 19, 'cms_article', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 100, '', '', 'list_article.html', 'show_article.html', 15, 1579344044, 1582684698),
(10, '小程序', 'minpro', 13, '0,13', '10', 18, 'cms_product', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 0, '', '', 'list_product.html', 'show_page.html', 15, 0, 1582684670),
(11, '微信', 'weixin', 13, '0,13', '11', 18, 'cms_product', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 0, '', '', 'list_product.html', 'show_page.html', 15, 0, 1582684662),
(12, '商城', 'shop', 13, '0,13', '12', 18, 'cms_product', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 0, '', '', 'list_product.html', 'show_page.html', 15, 0, 1582684653),
(13, '产品服务', 'product', 0, '0', '13,2,3,4,10,11,12', 18, 'cms_product', 0, '服务产品', '服务产品', '服务产品', NULL, 50, 1, 1, 100, '', '', 'list_product.html', 'show_page.html', 15, 1582536226, 1582684613),
(14, '行业咨询', 'news', 5, '0,5', '14', 19, 'cms_article', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', '<p>asdf</p>', 50, 1, 1, 100, '', '', 'list_article.html', 'show_article.html', 15, 1582536300, 1582684687),
(15, '精彩案例', 'cases', 0, '0', '15', 20, 'cms_picture', 0, 'funadmin', 'funadmin,layui', 'funadmin,layui,插件开发', NULL, 50, 1, 1, 100, '', '', 'list_pic.html', 'show_article.html', 15, 1582536497, 1582684602),
(16, '关于我们', 'about', 0, '0', '16', 19, 'cms_article', 1, '基于TP6 layui开发的cms 后台管理系统', '关于我们-funadmin', '关于我们-funadmin', '<p>深圳市<span style=\"color: rgb(102, 102, 102); font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; text-align: justify; widows: 1;\">科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商；</span></p>', 50, 1, 1, 0, '', '', 'list.html', 'show_about.html', 15, 1582684581, 1582684581);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_debris`
--

CREATE TABLE `fun_addons_cms_debris` (
  `id` int(6) UNSIGNED NOT NULL,
  `tid` int(6) DEFAULT NULL COMMENT '碎片分类ID',
  `title` varchar(120) DEFAULT NULL COMMENT '标题',
  `content` text COMMENT '内容',
  `sort` int(11) DEFAULT '50' COMMENT '排序',
  `url` varchar(120) DEFAULT '' COMMENT '链接',
  `image` varchar(120) DEFAULT '' COMMENT '图片',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_debris`
--

INSERT INTO `fun_addons_cms_debris` (`id`, `tid`, `title`, `content`, `sort`, `url`, `image`, `status`, `create_time`, `update_time`) VALUES
(1, 1, '底部版权', '<p style=\"text-align: center;\">Copyright © 2018-2019. funadmin | 备案：湘ICP备18009588号 | Powered by funadmin</p>', 50, '', '', 1, 1579333649, 1582616299);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_debris_type`
--

CREATE TABLE `fun_addons_cms_debris_type` (
  `id` int(11) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `sort` int(1) DEFAULT '50',
  `status` tinyint(1) DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_debris_type`
--

INSERT INTO `fun_addons_cms_debris_type` (`id`, `title`, `sort`, `status`) VALUES
(1, '底部版权', 1, 1);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_diyform`
--

CREATE TABLE `fun_addons_cms_diyform` (
  `id` int(10) UNSIGNED NOT NULL,
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
  `updatetime` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_diyform`
--

INSERT INTO `fun_addons_cms_diyform` (`id`, `name`, `title`, `seotitle`, `keywords`, `description`, `tablename`, `fields`, `needlogin`, `successtips`, `redirecturl`, `template`, `status`, `createtime`, `updatetime`) VALUES
(1, '留言', '留言', '留言', '留言', '留言', 'cms_message', 'username,mobile,wechat,', 0, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_field`
--

CREATE TABLE `fun_addons_cms_field` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `diyformid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '自定义表单id',
  `moduleid` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '模型id',
  `field` varchar(20) NOT NULL DEFAULT '' COMMENT '字段',
  `name` varchar(30) NOT NULL DEFAULT '' COMMENT '名字',
  `required` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否必须',
  `minlength` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最少长度',
  `maxlength` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最大长度',
  `rule` varchar(255) NOT NULL DEFAULT '' COMMENT '规则',
  `msg` varchar(255) NOT NULL DEFAULT '' COMMENT '错误提示',
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT '字段类型',
  `is_search` tinyint(1) DEFAULT '0' COMMENT '是否可以搜索 0  不可以，1 搜索',
  `value` varchar(50) DEFAULT NULL,
  `field_define` varchar(100) DEFAULT NULL,
  `option` text COMMENT '默认值',
  `sort` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_field`
--

INSERT INTO `fun_addons_cms_field` (`id`, `diyformid`, `moduleid`, `field`, `name`, `required`, `minlength`, `maxlength`, `rule`, `msg`, `type`, `is_search`, `value`, `field_define`, `option`, `sort`, `status`, `create_time`, `update_time`) VALUES
(75, 0, 18, 'cateid', '栏目', 1, 0, 6, '', '必须选择一个栏目', 'cateid', 0, '', NULL, '', 1, 1, 1582683760, 0),
(76, 0, 18, 'title', '标题', 1, 0, 180, '', '标题必须为1-180个字符', 'text', 1, '', NULL, '', 4, 1, 1582683760, 0),
(77, 0, 18, 'keywords', '关键词', 1, 0, 120, '', '', 'text', 1, '', NULL, '', 4, 1, 1582683760, 0),
(78, 0, 18, 'description', 'SEO简介', 1, 0, 0, '', '', 'textarea', 1, '', NULL, '', 5, 1, 1582683760, 0),
(79, 0, 18, 'tags', '标签', 0, 0, 0, '', '', 'text', 1, '', NULL, '', 6, 1, 1582683760, 0),
(80, 0, 18, 'thumb', '缩略图', 1, 0, 255, '', '缩略图', 'image', 0, '', NULL, '', 2, 1, 1582683760, 0),
(81, 0, 18, 'content', '内容', 0, 0, 0, '', '', 'editor', 1, 'ueditor', NULL, '0:ueditor', 7, 1, 1582683760, 0),
(82, 0, 18, 'status', '状态', 1, 0, 1, '', '', 'radio', 0, '1', NULL, '0:禁用\r\n1:启用', 8, 1, 1582683760, 0),
(83, 0, 18, 'sort', '排序', 1, 0, 1, '', '', 'text', 0, '1', NULL, '50', 9, 1, 1582683760, 0),
(84, 0, 18, 'hits', '点击次数', 0, 0, 8, '', '', 'number', 0, '', NULL, '', 10, 1, 1582683760, 0),
(85, 0, 19, 'cateid', '栏目', 1, 0, 6, '', '必须选择一个栏目', 'cateid', 0, '', NULL, '', 1, 1, 1582684044, 0),
(86, 0, 19, 'title', '标题', 1, 0, 80, '', '标题必须为1-80个字符', 'text', 1, '', NULL, '', 2, 1, 1582684044, 0),
(87, 0, 19, 'keywords', '关键词', 1, 0, 200, '', '关键词必须在0-200个内', 'text', 1, '', NULL, '', 3, 1, 1582684044, 0),
(88, 0, 19, 'description', 'SEO简介', 1, 0, 0, '', '', 'textarea', 1, '', NULL, '', 4, 1, 1582684044, 0),
(89, 0, 19, 'thumb', '缩略图', 1, 0, 255, '', '缩略图', 'image', 0, '', NULL, '', 1, 1, 1582684044, 0),
(90, 0, 19, 'content', '内容', 0, 0, 255, '', '', 'editor', 1, 'ueditor', NULL, '0:ueditor', 5, 1, 1582684044, 0),
(91, 0, 19, 'status', '状态', 1, 0, 1, '', '', 'radio', 0, '1', NULL, '0:未发布\r\n 1:发布', 7, 1, 1582684044, 0),
(92, 0, 19, 'recommend', '允许评论', 0, 0, 1, '', '', 'radio', 0, '1', NULL, '0:禁止评论\r\n 1:允许评论', 8, 1, 1582684044, 0),
(93, 0, 19, 'readfee', '阅读收费', 0, 0, 5, '', '', 'number', 0, '0', NULL, '', 9, 1, 1582684044, 0),
(94, 0, 19, 'is_read', '是否可阅读', 0, 0, 1, '', '', 'radio', 0, '1', NULL, '0:禁止 \r\n 1:允许', 9, 1, 1582684044, 0),
(95, 0, 19, 'hits', '点击次数', 0, 0, 8, '', '', 'number', 1, '1', NULL, '', 10, 1, 1582684044, 0),
(96, 0, 19, 'posid', '推荐位', 0, 0, 1, '', '', 'posid', 0, '', NULL, '1:置顶 \r\n2:热门\r\n3:头条', 12, 1, 1582684044, 0),
(97, 0, 19, 'tags', '标签', 0, 0, 255, '', '', 'text', 1, '', NULL, '', 14, 1, 1582684044, 0),
(98, 0, 19, 'sort', '排序', 0, 0, 50, '', '', 'text', 0, '', NULL, '', 14, 1, 1582684044, 0),
(99, 0, 20, 'cateid', '栏目', 1, 0, 6, '', '必须选择一个栏目', 'cateid', 0, '', NULL, '', 1, 1, 1582684060, 0),
(100, 0, 20, 'title', '标题', 1, 0, 180, '', '标题必须为1-180个字符', 'text', 1, '', NULL, '', 4, 1, 1582684060, 0),
(101, 0, 20, 'keywords', '关键词', 1, 0, 120, '', '', 'text', 1, '', NULL, '', 4, 1, 1582684060, 0),
(102, 0, 20, 'description', 'SEO简介', 1, 0, 0, '', '', 'textarea', 1, '', NULL, '', 5, 1, 1582684060, 0),
(103, 0, 20, 'tags', '标签', 0, 0, 0, '', '', 'text', 1, '', NULL, '', 6, 1, 1582684060, 0),
(104, 0, 20, 'thumb', '缩略图', 1, 0, 255, '', '缩略图', 'image', 0, '', NULL, '', 2, 1, 1582684060, 0),
(105, 0, 20, 'content', '内容', 0, 0, 0, '', '', 'editor', 1, 'ueditor', NULL, '0:ueditor', 7, 1, 1582684060, 0),
(106, 0, 20, 'status', '状态', 1, 0, 1, '', '', 'radio', 0, '1', NULL, '0:禁用\r\n1:启用', 8, 1, 1582684060, 0),
(107, 0, 20, 'sort', '排序', 1, 0, 1, '', '', 'text', 0, '1', NULL, '50', 9, 1, 1582684060, 0),
(108, 0, 20, 'hits', '点击次数', 0, 0, 8, '', '', 'number', 0, '', NULL, '', 10, 1, 1582684060, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_link`
--

CREATE TABLE `fun_addons_cms_link` (
  `id` int(5) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT '链接名称',
  `url` varchar(200) NOT NULL COMMENT '链接URL',
  `category_id` tinyint(4) DEFAULT '0' COMMENT '所属栏目ID',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `qq` varchar(20) DEFAULT NULL COMMENT '联系QQ',
  `sort` int(5) NOT NULL DEFAULT '50' COMMENT '排序',
  `status` tinyint(2) NOT NULL DEFAULT '1' COMMENT '0禁用1启用',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_link`
--

INSERT INTO `fun_addons_cms_link` (`id`, `name`, `url`, `category_id`, `email`, `qq`, `sort`, `status`, `create_time`, `update_time`) VALUES
(23, 'funadmin', 'https://www.funadmin.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566102829, 1577524944),
(25, '百度', 'https://www.baidu.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103165, 1573640285),
(26, '新浪', 'https://www.sina.com', 0, '994927909@qq.com', '994927909', 50, 1, 1566103233, 1573640288);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_message`
--

CREATE TABLE `fun_addons_cms_message` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(30) DEFAULT NULL,
  `company` varchar(50) DEFAULT NULL,
  `mobile` varchar(11) DEFAULT NULL,
  `wechat` varchar(50) DEFAULT NULL,
  `content` varchar(150) DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='留言表';

--
-- 转存表中的数据 `fun_addons_cms_message`
--

INSERT INTO `fun_addons_cms_message` (`id`, `username`, `company`, `mobile`, `wechat`, `content`, `create_time`, `update_time`) VALUES
(1, 'ce', 'ce', 'ce', 'e', 'e', NULL, NULL);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_module`
--

CREATE TABLE `fun_addons_cms_module` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `modulename` varchar(100) NOT NULL DEFAULT '' COMMENT '模型名称',
  `tablename` varchar(50) NOT NULL DEFAULT '' COMMENT '表名',
  `intro` varchar(200) NOT NULL DEFAULT '' COMMENT '描述',
  `type` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0 空白，1 文章',
  `ispage` tinyint(1) DEFAULT '0' COMMENT '是否单页',
  `listfields` varchar(255) NOT NULL DEFAULT '' COMMENT '列表页查询字段',
  `template` varchar(255) NOT NULL DEFAULT ' ',
  `sort` smallint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '状态',
  `create_time` int(11) DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `delete_time` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模型表';

--
-- 转存表中的数据 `fun_addons_cms_module`
--

INSERT INTO `fun_addons_cms_module` (`id`, `modulename`, `tablename`, `intro`, `type`, `ispage`, `listfields`, `template`, `sort`, `status`, `create_time`, `update_time`, `delete_time`) VALUES
(18, 'cms_product', 'cms_product', 'cms_product', 0, 0, '*', '', 50, 1, 1582683759, 1582683759, 0),
(19, 'cms_article', 'cms_article', '文章', 0, 0, '*', '', 50, 1, 1582684043, 1582684043, 0),
(20, 'cms_picture', 'cms_picture', '图片', 0, 0, '*', '', 50, 1, 1582684060, 1582684060, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_picture`
--

CREATE TABLE `fun_addons_cms_picture` (
  `id` int(11) UNSIGNED NOT NULL COMMENT 'ID',
  `uid` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '发布人id',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '发布人',
  `cateid` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '栏目id',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `title_style` varchar(255) NOT NULL COMMENT '标题样式',
  `thumb` varchar(255) NOT NULL DEFAULT ' ' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT '' COMMENT '关键词',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL COMMENT '标签',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='fun_addons_cms_picture模型表';

--
-- 转存表中的数据 `fun_addons_cms_picture`
--

INSERT INTO `fun_addons_cms_picture` (`id`, `uid`, `username`, `cateid`, `title`, `title_style`, `thumb`, `keywords`, `description`, `content`, `tags`, `status`, `sort`, `hits`, `create_time`, `update_time`) VALUES
(1, 0, ' ', 15, 'XXXXXXXXXXX微信电商公众号商城开发服务', '', '/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg', '服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等；', '服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等', '<p style=\"box-sizing: border-box; margin: 0px auto; font-size: 1.6rem; color: rgb(42, 42, 42); padding: 10px 0px; line-height: 28.8px; text-align: justify; font-family: &quot;Microsoft YaHei&quot;, &quot;Helvetica Neue&quot;, Helvetica, Arial, sans-serif; white-space: normal; background-color: rgb(255, 255, 255);\">服务内容：微信商城公众号版、小程序版、安卓app、iOSapp，功能包含商品上下架、多商户入驻、拼团、砍价、秒杀、限时抢购、分销等等；</p><p><img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 7, 1582690082, 0),
(2, 0, ' ', 15, 'GitHub 安全警告计划已检测出 400 多万个漏洞', '', '/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg', 'Github 去年推出的安全警告，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者', 'Github 去年推出的安全警告，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者转到安全版本。\n\n', '<p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\"><span style=\"color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; background-color: rgb(255, 255, 255);\">Github 去年推出的</span><a href=\"https://www.oschina.net/news/90737/security-alerts-on-github\" style=\"box-sizing: border-box; background-color: rgb(255, 255, 255); color: rgb(85, 85, 85); text-decoration-line: none; font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal;\">安全警告</a><span style=\"color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; background-color: rgb(255, 255, 255);\">，极大减少了开发人员消除 Ruby 和 JavaScript 项目漏洞的时间。</span><strong style=\"box-sizing: border-box; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">GitHub 安全警告服务，可以搜索依赖寻找已知漏洞然后通过开发者，以便帮助开发者尽可能快的打上补丁修复漏洞，消除有漏洞的依赖或者转到安全版本。</strong></p><p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">根据 Github 的说法，目前安全警告已经报告了 50 多万个库中的 400 多万个漏洞。在所有显示的警告中，有将近一半的在一周之内得到了响应，前7天的漏洞解决率大约为30%。实际上，情况可能更好，因为当把统计限制在最近有贡献的库时，也就是说过去90天中有贡献的库，98%的库在7天之内打上了补丁。</p><p style=\"box-sizing: border-box; margin-top: 0px; margin-bottom: 15px; line-height: 30px; color: rgb(97, 97, 97); font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, Ubuntu, &quot;Helvetica Neue&quot;, Helvetica, Arial, &quot;PingFang SC&quot;, &quot;Hiragino Sans GB&quot;, &quot;Microsoft YaHei UI&quot;, &quot;Microsoft YaHei&quot;, &quot;Source Han Sans CN&quot;, sans-serif; font-size: 14px; white-space: normal; background-color: rgb(255, 255, 255);\">这个安全警报服务会扫描所有公共库，对于私有库，只扫描依赖图。每当发现有漏洞，库管理员都可以收到消息提示，其中还有漏洞级别及解决步骤提供。</p><p><br/></p>', 'tp6,funadmin,', 1, 1, 0, 1582710692, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_product`
--

CREATE TABLE `fun_addons_cms_product` (
  `id` int(11) UNSIGNED NOT NULL COMMENT 'ID',
  `uid` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '发布人id',
  `username` varchar(50) NOT NULL DEFAULT ' ' COMMENT '发布人',
  `cateid` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '栏目id',
  `title` varchar(120) NOT NULL DEFAULT ' ' COMMENT '标题',
  `title_style` varchar(255) NOT NULL COMMENT '标题样式',
  `thumb` varchar(255) NOT NULL DEFAULT ' ' COMMENT '缩略图',
  `keywords` varchar(120) NOT NULL DEFAULT '' COMMENT '关键词',
  `description` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `content` mediumtext NOT NULL COMMENT '内容',
  `tags` varchar(255) NOT NULL COMMENT '标签',
  `status` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '状态',
  `sort` int(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '排序',
  `hits` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击',
  `create_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='fun_addons_cms_product模型表';

--
-- 转存表中的数据 `fun_addons_cms_product`
--

INSERT INTO `fun_addons_cms_product` (`id`, `uid`, `username`, `cateid`, `title`, `title_style`, `thumb`, `keywords`, `description`, `content`, `tags`, `status`, `sort`, `hits`, `create_time`, `update_time`) VALUES
(1, 0, ' ', 10, '小程序开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', '小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 1, 1582689212, 1582689264),
(2, 0, ' ', 10, '微信商城', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'funadmin-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 6, 1582689444, 0),
(3, 0, ' ', 10, '企业小程序', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'funadmin-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 0, 1582689444, 0),
(4, 0, ' ', 11, '微信开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'funadmin-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 0, 1582689444, 0),
(5, 0, ' ', 12, '商城开发', '', '/storage/cms/20200226/991a63d847e174beafe78b4994c4f626.jpg', 'funadmin-小程序开发,公众号开发,网站开发,品牌推广', 'XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商', '<p>XXXXXXXXXXX科技有限公司专注于互联网应用开发服务，主营业务有：网站、公众号、小程序、APP开发，企业品牌网络媒体运营推广，招商活动营销策划等服务；自成立以来，凭借良好的行业经验与技术实力，本着“高效管理”、“合作共赢”、“力创精品”的经营理念，致力于成为国内专业的产业互联网服务商<img src=\"/storage/cms/20200226/93a9fa6aa1396af61f34adf2ebd83452.jpg\" title=\"\" alt=\"\"/></p>', '', 1, 1, 0, 1582689444, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addons_cms_tags`
--

CREATE TABLE `fun_addons_cms_tags` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(80) NOT NULL,
  `hits` int(11) DEFAULT '0',
  `nums` int(11) DEFAULT '0',
  `article_ids` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addons_cms_tags`
--

INSERT INTO `fun_addons_cms_tags` (`id`, `name`, `hits`, `nums`, `article_ids`) VALUES
(5, 'funadmin', 8, 1, NULL),
(6, 'layui', 2, 3, ',2'),
(7, 'tp6', 0, 0, NULL),
(8, 'thinkphp', 0, 2, ',2'),
(9, 'easywechat', 0, 1, ',2');

-- --------------------------------------------------------

--
-- 表的结构 `fun_addon_spshop_adv`
--

CREATE TABLE `fun_addon_spshop_adv` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '广告id',
  `merchant_id` int(10) NOT NULL DEFAULT '1' COMMENT '商户id\r\n',
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
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addon_spshop_adv`
--

INSERT INTO `fun_addon_spshop_adv` (`id`, `merchant_id`, `pid`, `media_type`, `ad_name`, `ad_link`, `ad_image`, `start_time`, `end_time`, `link_admin`, `link_email`, `link_phone`, `click_count`, `sort`, `status`, `orderby`, `target`, `bgcolor`, `create_time`, `update_time`) VALUES
(4, 1, 1, 0, 'PC首页轮播', 'http://shop.zhutiyuedu.com/index/goods/index.html?cate_id=3', '/storage/uploads/20200612/e88bcf9364acbfd5ec3d1bdf99d0d394.jpg', 1451577600, 1767283200, '', '994927909@qq.com', '', 0, 0, 1, 0, 0, '#f1e6d2', 0, 1591952531),
(88, 1, 1, 0, '滚动海报', 'http://shop.zhutiyuedu.com/index/goods/details.html?id=15', '/storage/uploads/20200612/ba7a1c2d4178a9cad2fcf0e0becf1204.jpg', 1573056000, 1577721600, '', '2943694612@qq.com', '', 0, 0, 1, 50, 0, '', 1573114077, 1591953494),
(89, 1, 2, 0, '滚动海报', 'http://shop.zhutiyuedu.com/h5/goods/details.html?id=15', '/storage/uploads/20200612/b644a5ee6a4786d928b1d8106f5b204d.jpg', 0, 0, '', '123456@qq.com', '', 0, 0, 1, 50, 0, '', 0, 1591951860),
(92, 1, 1, 0, 'PC 端首页海报', 'http://shop.zhutiyuedu.com/index/goods/index.html?cate_id=1', '/storage/uploads/20200612/53b1ca94c135e4cb6d3d998d42df723e.jpg', 1556640000, 1751212800, '', '1158879326@qq.com', '', 0, 0, 1, 50, 0, '', 1589167343, 1591951506),
(93, 1, 1, 0, 'PC端首页广告', 'http://shop.zhutiyuedu.com/index/goods/index.html?cate_id=2', '/storage/uploads/20200612/15a837718a409244fb9291f2881f6586.jpg', 1588262400, 1593446400, '', '1158879326@qq.com', '', 0, 0, 1, 50, 0, '', 1589177342, 1591949142),
(94, 1, 2, 0, '手机首页海报', 'http://shop.zhutiyuedu.com/index/goods/index.html?cate_id=3', '/storage/uploads/20200612/90bfab0170969734a03868600afcfc72.jpg', 1588262400, 1782748800, '', '1158879326@qq.com', '', 0, 0, 1, 50, 0, '', 1589330964, 1591951809);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addon_spshop_adv_position`
--

CREATE TABLE `fun_addon_spshop_adv_position` (
  `id` int(3) UNSIGNED NOT NULL COMMENT '表id',
  `position_name` varchar(60) NOT NULL DEFAULT '' COMMENT '广告位置名称',
  `ad_width` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位宽度',
  `ad_height` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '广告位高度',
  `position_desc` varchar(255) NOT NULL DEFAULT '' COMMENT '广告描述',
  `position_style` mediumtext COMMENT '模板',
  `status` tinyint(1) DEFAULT '0' COMMENT '0关闭1开启',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addon_spshop_adv_position`
--

INSERT INTO `fun_addon_spshop_adv_position` (`id`, `position_name`, `ad_width`, `ad_height`, `position_desc`, `position_style`, `status`, `create_time`, `update_time`) VALUES
(1, '首页', 1080, 300, '其他1', '', 1, 1566111321, 1571818062),
(2, '手机首页', 1080, 300, '手机端受饿', '', 1, 1576549734, 1576549750);

-- --------------------------------------------------------

--
-- 表的结构 `fun_addon_spshop_goods`
--

CREATE TABLE `fun_addon_spshop_goods` (
  `id` mediumint(8) UNSIGNED NOT NULL COMMENT '商品id',
  `cate_id` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '商品分类id',
  `extend_cate_id` int(11) UNSIGNED DEFAULT '0' COMMENT '扩展分类id',
  `goods_sn` varchar(60) NOT NULL DEFAULT '' COMMENT '商品编号',
  `name` varchar(120) NOT NULL DEFAULT '' COMMENT '商品名称',
  `video` varchar(255) DEFAULT '' COMMENT '视频',
  `hits` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '点击数',
  `brand_id` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '品牌id',
  `qty` int(10) UNSIGNED NOT NULL DEFAULT '10' COMMENT '库存数量',
  `max_sales_qty` int(10) UNSIGNED DEFAULT NULL COMMENT '最大销售个数',
  `min_sales_qty` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最小销售个数',
  `weight` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '商品重量克为单位',
  `volume` double(10,4) UNSIGNED NOT NULL DEFAULT '0.0000' COMMENT '商品体积。单位立方米',
  `market_price` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '市场价',
  `shop_price` decimal(10,2) UNSIGNED NOT NULL DEFAULT '0.00' COMMENT '本店价',
  `cost_price` decimal(10,2) DEFAULT '0.00' COMMENT '商品成本价',
  `price_ladder` mediumtext COMMENT '价格阶梯',
  `brief` varchar(200) DEFAULT '' COMMENT '简介',
  `keywords` varchar(255) NOT NULL DEFAULT '' COMMENT '商品关键词',
  `details` mediumtext COMMENT '商品详细描述',
  `gallery` varchar(1000) DEFAULT NULL COMMENT '商品轮播图 json 格式',
  `main_image` varchar(255) NOT NULL DEFAULT '' COMMENT 'l商品展示图',
  `share_image` varchar(255) DEFAULT NULL COMMENT '商品分享朋友圈图片',
  `is_virtual` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否为虚拟商品 1是，0否',
  `virtual_indate` int(11) DEFAULT '0' COMMENT '虚拟商品有效期',
  `virtual_refund` tinyint(1) DEFAULT '1' COMMENT '是否允许过期退款， 1是，0否',
  `virtual_sales_sum` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '虚拟销售量',
  `virtual_collect_sum` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '虚拟收藏量',
  `collect_sum` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '收藏量',
  `comment_sum` int(11) DEFAULT '0' COMMENT '商品评论数',
  `sales_sum` int(11) DEFAULT '0' COMMENT '商品总销量',
  `sort` smallint(4) UNSIGNED NOT NULL DEFAULT '50' COMMENT '商品排序',
  `is_on_sale` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否上架',
  `is_free_shipping` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否包邮0否1是',
  `is_recommend` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否推荐',
  `is_new` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否新品',
  `is_hot` tinyint(1) UNSIGNED DEFAULT '0' COMMENT '是否热卖',
  `give_integral` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '购买商品赠送积分',
  `exchange_integral` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '积分兑换：0不参与积分兑换，积分和现金的兑换比例见后台配置',
  `suppliers_id` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '供货商ID',
  `prom_type` tinyint(1) DEFAULT '0' COMMENT '0默认1抢购2团购3优惠促销4预售5拼团6搭配购',
  `prom_id` int(11) NOT NULL DEFAULT '0' COMMENT '优惠活动id',
  `commission` decimal(10,2) DEFAULT '0.00' COMMENT '佣金用于分销分成',
  `spu` varchar(128) DEFAULT '' COMMENT 'SPU',
  `sku` varchar(128) DEFAULT '' COMMENT 'SKU',
  `shipping_template_id` int(11) UNSIGNED DEFAULT '0' COMMENT '运费模板ID',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0',
  `deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '伪删除'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表';

-- --------------------------------------------------------

--
-- 表的结构 `fun_addon_spshop_goods_category`
--

CREATE TABLE `fun_addon_spshop_goods_category` (
  `id` smallint(5) UNSIGNED NOT NULL COMMENT '商品分类id',
  `name` varchar(90) NOT NULL DEFAULT '' COMMENT '商品分类名称',
  `pid` smallint(5) UNSIGNED NOT NULL DEFAULT '0' COMMENT '父id',
  `parent_id_path` varchar(128) DEFAULT '' COMMENT '家族图谱',
  `level` tinyint(1) DEFAULT '0' COMMENT '等级',
  `sort` tinyint(1) UNSIGNED NOT NULL DEFAULT '50' COMMENT '顺序排序',
  `is_show` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' COMMENT '是否显示',
  `image` varchar(512) DEFAULT '' COMMENT '分类图片',
  `is_new` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否上新',
  `is_hot` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为热门分类',
  `cate_group` tinyint(1) DEFAULT '0' COMMENT '分类分组默认0',
  `commission_rate` tinyint(1) DEFAULT '0' COMMENT '分佣比例',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `create_time` int(11) NOT NULL DEFAULT '0',
  `update_time` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_addon_spshop_goods_category`
--

INSERT INTO `fun_addon_spshop_goods_category` (`id`, `name`, `pid`, `parent_id_path`, `level`, `sort`, `is_show`, `image`, `is_new`, `is_hot`, `cate_group`, `commission_rate`, `status`, `create_time`, `update_time`) VALUES
(1, '主题阅读', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 1572319646, 1573605979),
(2, '你读我诵', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 1572319670, 1572319670),
(3, '立小言作文课', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 1572319712, 1572319712),
(4, '主题探究', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 0, 1573606067),
(5, '数创绘本', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 0, 1573606067),
(52, '套书', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 1575016710, 1575016710),
(53, '教师用书', 0, '', 0, 50, 1, '', 0, 0, 0, 0, 1, 1578379256, 1578379256),
(54, '名师课堂', 0, '', 0, 50, 0, '', 0, 0, 0, 0, 1, 1593573335, 1593589692);

-- --------------------------------------------------------

--
-- 表的结构 `fun_admin`
--

CREATE TABLE `fun_admin` (
  `id` tinyint(4) NOT NULL COMMENT '管理员ID',
  `username` varchar(20) NOT NULL COMMENT '管理员用户名',
  `password` varchar(200) NOT NULL COMMENT '管理员密码',
  `group_id` varchar(8) DEFAULT NULL COMMENT '分组ID,用逗号隔开',
  `email` varchar(30) DEFAULT NULL COMMENT '邮箱',
  `realname` varchar(10) DEFAULT NULL COMMENT '真实姓名',
  `mobile` varchar(30) DEFAULT NULL COMMENT '电话号码',
  `ip` varchar(20) DEFAULT NULL COMMENT 'IP地址',
  `token` varchar(100) DEFAULT NULL,
  `mdemail` varchar(50) DEFAULT '0' COMMENT '传递修改密码参数加密',
  `status` tinyint(2) DEFAULT '1' COMMENT '审核状态',
  `avatar` varchar(120) DEFAULT '' COMMENT '头像',
  `create_time` int(11) DEFAULT NULL COMMENT '添加时间',
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员';

--
-- 转存表中的数据 `fun_admin`
--

INSERT INTO `fun_admin` (`id`, `username`, `password`, `group_id`, `email`, `realname`, `mobile`, `ip`, `token`, `mdemail`, `status`, `avatar`, `create_time`, `update_time`) VALUES
(1, 'admin', '$2y$12$jJNSWOS.8he.z3s17YCRtesZ1v6F6Ck3zUGBhniRDr2LNHfUUwH5.', '1,3', '994927909@qq.com', '', '18397423845', '127.0.0.1', '8d4073f0f6f6fb71026cb84d38b8500702b1aa46', '0', 1, '\\storage\\site/20200723\\045256245ee708a40a2ac1b567bc481a.png', 1482132862, 1604121463),
(3, 'demo', '$2y$12$jJNSWOS.8he.z3s17YCRtesZ1v6F6Ck3zUGBhniRDr2LNHfUUwH5.', '3', '994927909@qq.com', '', '18397423845', '127.0.0.1', 'f4c62e44330799b5bb922cbe5fff24d4d2669ddf', '0', 1, '/storage/uploads/20190817\\a17c794ac7fae7db012aa6e997cf3400.jpg', 1564041575, 1603265320);

-- --------------------------------------------------------

--
-- 表的结构 `fun_admin_log`
--

CREATE TABLE `fun_admin_log` (
  `id` bigint(16) UNSIGNED NOT NULL COMMENT '表id',
  `module` varchar(50) DEFAULT NULL,
  `admin_id` int(10) DEFAULT NULL COMMENT '管理员id',
  `username` varchar(100) DEFAULT NULL,
  `method` varchar(50) DEFAULT NULL COMMENT '请求方式',
  `title` varchar(100) DEFAULT NULL COMMENT '日志描述',
  `url` varchar(100) DEFAULT NULL,
  `content` text,
  `agent` varchar(200) DEFAULT NULL,
  `ip` varchar(30) DEFAULT NULL COMMENT 'ip地址',
  `create_time` int(11) DEFAULT NULL COMMENT '日志时间',
  `update_time` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_admin_log`
--

INSERT INTO `fun_admin_log` (`id`, `module`, `admin_id`, `username`, `method`, `title`, `url`, `content`, `agent`, `ip`, `create_time`, `update_time`, `status`) VALUES
(1, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.attach/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596278, 1603596278, 1),
(2, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.config/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596349, 1603596349, 1),
(3, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.languages/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596352, 1603596352, 1),
(4, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.languages/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596361, 1603596361, 1),
(5, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.config/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596361, 1603596361, 1),
(6, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.adminlog/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596364, 1603596364, 1),
(7, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.attach/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596378, 1603596378, 1),
(8, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.attach/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596414, 1603596414, 1),
(9, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.authgroup/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596416, 1603596416, 1),
(10, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603596889, 1603596889, 1),
(11, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598201, 1603598201, 1),
(12, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"2\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598363, 1603598363, 1),
(13, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"2\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598366, 1603598366, 1),
(14, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"2\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598369, 1603598369, 1),
(15, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598385, 1603598385, 1),
(16, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"3\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598448, 1603598448, 1),
(17, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"3\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598455, 1603598455, 1),
(18, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598513, 1603598513, 1),
(19, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"4\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598547, 1603598547, 1),
(20, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"4\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603598550, 1603598550, 1),
(21, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603599908, 1603599908, 1),
(22, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603600779, 1603600779, 1),
(23, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"5\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603600786, 1603600786, 1),
(24, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"5\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603600792, 1603600792, 1),
(25, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601015, 1603601015, 1),
(26, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"6\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601066, 1603601066, 1),
(27, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"6\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601068, 1603601068, 1),
(28, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601089, 1603601089, 1),
(29, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601103, 1603601103, 1),
(30, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601151, 1603601151, 1),
(31, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"7\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601155, 1603601155, 1),
(32, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"7\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601158, 1603601158, 1),
(33, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601167, 1603601167, 1),
(34, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"8\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601478, 1603601478, 1),
(35, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"8\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601481, 1603601481, 1),
(36, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601641, 1603601641, 1),
(37, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"9\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601745, 1603601745, 1),
(38, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"9\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601749, 1603601749, 1),
(39, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601824, 1603601824, 1),
(40, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"10\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601875, 1603601875, 1),
(41, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"10\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601878, 1603601878, 1),
(42, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"database\",\"id\":\"1\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601984, 1603601984, 1),
(43, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"database\",\"id\":\"1\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601987, 1603601987, 1),
(44, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"database\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603601991, 1603601991, 1),
(45, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604409, 1603604409, 1),
(46, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"12\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604458, 1603604458, 1),
(47, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"12\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604576, 1603604576, 1),
(48, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"12\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604663, 1603604663, 1),
(49, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604669, 1603604669, 1),
(50, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"13\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604696, 1603604696, 1),
(51, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"13\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604699, 1603604699, 1),
(52, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604718, 1603604718, 1),
(53, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"14\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604831, 1603604831, 1),
(54, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"14\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604834, 1603604834, 1),
(55, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"14\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604836, 1603604836, 1),
(56, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603604884, 1603604884, 1),
(57, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"15\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605034, 1603605034, 1),
(58, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"15\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605037, 1603605037, 1),
(59, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605066, 1603605066, 1),
(60, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"16\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605091, 1603605091, 1),
(61, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"16\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605093, 1603605093, 1),
(62, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605135, 1603605135, 1),
(63, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"17\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605154, 1603605154, 1),
(64, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"17\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605156, 1603605156, 1),
(65, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605198, 1603605198, 1),
(66, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605208, 1603605208, 1),
(67, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"18\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605213, 1603605213, 1),
(68, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"18\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605216, 1603605216, 1),
(69, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605325, 1603605325, 1),
(70, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605339, 1603605339, 1),
(71, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/addon/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605341, 1603605341, 1),
(72, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"19\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605345, 1603605345, 1),
(73, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"19\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605348, 1603605348, 1),
(74, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605394, 1603605394, 1),
(75, 'backend', 1, 'admin', 'POST', 'modify', '/ZtWXfUSehw.php/addon/modify.html', '{\"name\":\"cms\",\"id\":\"20\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605591, 1603605591, 1),
(76, 'backend', 1, 'admin', 'POST', 'Uninstall', '/ZtWXfUSehw.php/addon/uninstall.html', '{\"name\":\"cms\",\"id\":\"20\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603605593, 1603605593, 1),
(77, 'backend', 1, 'admin', 'POST', 'Install', '/ZtWXfUSehw.php/addon/install.html', '{\"name\":\"cms\",\"id\":\"undefined\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603606017, 1603606017, 1),
(78, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613225, 1603613225, 1),
(79, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613674, 1603613674, 1),
(80, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613718, 1603613718, 1),
(81, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613788, 1603613788, 1),
(82, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613820, 1603613820, 1),
(83, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603613846, 1603613846, 1),
(84, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"path\":\"upload\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603620092, 1603620092, 1),
(85, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"path\":\"upload\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603620251, 1603620251, 1),
(86, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"path\":\"upload\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603620269, 1603620269, 1),
(87, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"path\":\"upload\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.80 Safari/537.36 Edg/86.0.622.48', '127.0.0.1', 1603620282, 1603620282, 1),
(88, 'backend', 0, 'admin', 'POST', '[登录成功]', '/ZtWXfUSehw.php/login/index.html', '{\"__token__\":\"b3268060c9939620157d5d069e0e4d46\",\"username\":\"admin\",\"password\":\"123456\",\"captcha\":\"36\",\"rememberMe\":\"true\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714034, 1603714034, 1),
(89, 'backend', 0, 'admin', 'POST', '[登录成功]', '/ZtWXfUSehw.php/login/index.html', '{\"__token__\":\"4ef29a3e189baf531d7a0b7c5764ca5a\",\"username\":\"admin\",\"password\":\"123456\",\"captcha\":\"33\",\"rememberMe\":\"true\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714038, 1603714038, 1),
(90, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714041, 1603714041, 1),
(91, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714046, 1603714046, 1),
(92, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714082, 1603714082, 1),
(93, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.adminlog/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603714130, 1603714130, 1),
(94, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715071, 1603715071, 1),
(95, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715080, 1603715080, 1),
(96, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715085, 1603715085, 1),
(97, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715249, 1603715249, 1),
(98, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715523, 1603715523, 1),
(99, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715611, 1603715611, 1),
(100, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715618, 1603715618, 1),
(101, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715706, 1603715706, 1),
(102, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715708, 1603715708, 1),
(103, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715721, 1603715721, 1),
(104, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603715723, 1603715723, 1),
(105, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603716944, 1603716944, 1),
(106, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603716955, 1603716955, 1),
(107, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.authgroup/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603717714, 1603717714, 1),
(108, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603717841, 1603717841, 1),
(109, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603717861, 1603717861, 1),
(110, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603718140, 1603718140, 1),
(111, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720107, 1603720107, 1),
(112, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720115, 1603720115, 1),
(113, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720914, 1603720914, 1),
(114, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720933, 1603720933, 1),
(115, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720977, 1603720977, 1),
(116, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720987, 1603720987, 1),
(117, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603720999, 1603720999, 1),
(118, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721019, 1603721019, 1),
(119, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721024, 1603721024, 1),
(120, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721048, 1603721048, 1),
(121, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721097, 1603721097, 1),
(122, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721100, 1603721100, 1),
(123, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721142, 1603721142, 1),
(124, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721153, 1603721153, 1),
(125, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721156, 1603721156, 1),
(126, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721164, 1603721164, 1),
(127, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/auth.admin/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.51', '127.0.0.1', 1603721166, 1603721166, 1),
(128, 'backend', 0, 'admin', 'POST', '[登录成功]', '/ZtWXfUSehw.php/login/index.html', '{\"__token__\":\"fe67681432ec17e7ca8d028e9de2ebd6\",\"username\":\"admin\",\"password\":\"123456\",\"captcha\":\"20\",\"rememberMe\":\"true\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604121463, 1604121463, 1),
(129, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604121466, 1604121466, 1),
(130, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604130663, 1604130663, 1),
(131, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604130758, 1604130758, 1),
(132, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604131274, 1604131274, 1),
(133, 'backend', 1, 'admin', 'GET', 'Edit', '/ZtWXfUSehw.php/member.member/edit.html', '{\"id\":\"4\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604131278, 1604131278, 1),
(134, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604131284, 1604131284, 1),
(135, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.languages/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132896, 1604132896, 1),
(136, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.config/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132907, 1604132907, 1),
(137, 'backend', 1, 'admin', 'GET', 'Edit', '/ZtWXfUSehw.php/sys.config/edit.html', '{\"id\":\"32\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132919, 1604132919, 1),
(138, 'backend', 1, 'admin', 'GET', 'Edit', '/ZtWXfUSehw.php/sys.config/edit.html', '{\"id\":\"11\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132925, 1604132925, 1),
(139, 'backend', 1, 'admin', 'POST', 'Edit', '/ZtWXfUSehw.php/sys.config/edit.html', '{\"id\":\"11\",\"group\":\"site\",\"type\":\"radio\",\"code\":\"app_debug\",\"verfiy\":\"\",\"value\":\"1\",\"extra\":\"0\\n1\",\"remark\":\"\\u6d4b\\u8bd5\\u6a21\\u5f0f\",\"__token__\":\"82f715721a36ac5f300d8873b18e91ba\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132931, 1604132931, 1),
(140, 'backend', 1, 'admin', 'GET', 'Edit', '/ZtWXfUSehw.php/sys.config/edit.html', '{\"id\":\"11\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132932, 1604132932, 1),
(141, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132945, 1604132945, 1),
(142, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/sys.config/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604132948, 1604132948, 1),
(143, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133096, 1604133096, 1),
(144, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133195, 1604133195, 1),
(145, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133330, 1604133330, 1),
(146, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133523, 1604133523, 1),
(147, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133601, 1604133601, 1),
(148, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604133700, 1604133700, 1),
(149, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604134024, 1604134024, 1),
(150, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604134105, 1604134105, 1),
(151, 'backend', 1, 'admin', 'GET', '数据列表', '/ZtWXfUSehw.php/index/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604134766, 1604134766, 1),
(152, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '清除缓存|切换语言', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604143681, 1604143681, 1),
(153, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '清除缓存|切换语言', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604143729, 1604143729, 1),
(154, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '清除缓存|切换语言', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604143740, 1604143740, 1),
(155, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '清除缓存|切换语言', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604143765, 1604143765, 1),
(156, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"editor\":\"layedit\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604144318, 1604144318, 1),
(157, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"editor\":\"layedit\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604144485, 1604144485, 1),
(158, 'backend', 1, 'admin', 'POST', 'Uploads', '/ZtWXfUSehw.php/ajax/uploads.html', '{\"editor\":\"layedit\"}', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604144846, 1604144846, 1),
(159, 'backend', 1, 'admin', 'GET', 'List', '/ztwxfusehw.php/member.member/index.html', '菜单点击|刷新', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.111 Safari/537.36 Edg/86.0.622.56', '127.0.0.1', 1604150048, 1604150048, 1);

-- --------------------------------------------------------

--
-- 表的结构 `fun_attach`
--

CREATE TABLE `fun_attach` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL DEFAULT '0' COMMENT '管理员id',
  `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户表id',
  `original_name` varchar(255) DEFAULT NULL COMMENT '文件原名',
  `name` varchar(255) DEFAULT NULL COMMENT '文件名',
  `thumb` varchar(255) DEFAULT NULL COMMENT '缩略图',
  `path` varchar(255) DEFAULT NULL COMMENT '路径',
  `url` varchar(255) DEFAULT NULL COMMENT '完整地址',
  `ext` varchar(5) DEFAULT NULL COMMENT '后缀',
  `size` int(11) DEFAULT '0' COMMENT '大小',
  `width` varchar(30) DEFAULT '0' COMMENT '宽度',
  `height` varchar(30) DEFAULT '0' COMMENT '高度',
  `md5` char(32) DEFAULT NULL,
  `mime` varchar(80) DEFAULT NULL,
  `duration` varchar(50) DEFAULT '0' COMMENT '音视频时长',
  `driver` varchar(20) DEFAULT 'local',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `sort` int(5) NOT NULL DEFAULT '50'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_attach`
--

INSERT INTO `fun_attach` (`id`, `admin_id`, `user_id`, `original_name`, `name`, `thumb`, `path`, `url`, `ext`, `size`, `width`, `height`, `md5`, `mime`, `duration`, `driver`, `create_time`, `update_time`, `status`, `sort`) VALUES
(28, 1, 0, '1598258370953677.jpg', 'ec3310db159d25e82da591f740d89300.jpg', '\\storage\\uploads/20201015\\ec3310db159d25e82da591f740d89300.jpg', '\\storage\\uploads/20201015\\ec3310db159d25e82da591f740d89300.jpg', '\\storage\\uploads/20201015\\ec3310db159d25e82da591f740d89300.jpg', 'jpg', 159, '537', '852', '0e3893e089007c97b741b108324dcb4b', 'image/jpeg', '0', NULL, 1602723224, 1602723224, 1, 50),
(29, 1, 0, 'admin-ajax.png', '7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', 'png', 4, '115', '103', 'cb959c6185839156a4ebadd507b96f67', 'image/png', '0', NULL, 1602723370, 1602723370, 1, 50),
(30, 1, 0, 'logo.png', 'efc76aa0ba4a3ee23c96035049d0b96c.png', '\\storage\\upload/20201025\\efc76aa0ba4a3ee23c96035049d0b96c.png', '\\storage\\upload/20201025\\efc76aa0ba4a3ee23c96035049d0b96c.png', '\\storage\\upload/20201025\\efc76aa0ba4a3ee23c96035049d0b96c.png', 'png', 12, '800', '800', '4fb3d30dbbfb2ad6d7df97397f91d791', 'image/png', '0', NULL, 1603620282, 1603620282, 1, 50),
(31, 1, 0, '168_1535351333114_70495.jpg', 'a4ba32dd2c8369e7d3ecef9ff1e1a5e6.jpg', '\\storage\\uploads/20201031\\a4ba32dd2c8369e7d3ecef9ff1e1a5e6.jpg', '\\storage\\uploads/20201031\\a4ba32dd2c8369e7d3ecef9ff1e1a5e6.jpg', '\\storage\\uploads/20201031\\a4ba32dd2c8369e7d3ecef9ff1e1a5e6.jpg', 'jpg', 120, '3840', '800', '8094d152873eb7269bbe39f9dfcf8260', 'image/jpeg', '0', NULL, 1604143741, 1604143741, 1, 50),
(32, 1, 0, '未标题-1.png', 'e3ab4366e1c42b44892756b0a2b91444.png', '\\storage\\uploads/20201031\\e3ab4366e1c42b44892756b0a2b91444.png', '\\storage\\uploads/20201031\\e3ab4366e1c42b44892756b0a2b91444.png', '\\storage\\uploads/20201031\\e3ab4366e1c42b44892756b0a2b91444.png', 'png', 13, '800', '600', 'eead77ffb36d17b267b598a236673823', 'image/png', '0', NULL, 1604144846, 1604144846, 1, 50);

-- --------------------------------------------------------

--
-- 表的结构 `fun_auth_group`
--

CREATE TABLE `fun_auth_group` (
  `id` smallint(8) UNSIGNED NOT NULL COMMENT '分组id',
  `pid` int(8) DEFAULT '0' COMMENT '父级',
  `title` char(100) NOT NULL DEFAULT '' COMMENT '标题',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态',
  `rules` longtext COMMENT '规则',
  `create_time` int(11) DEFAULT NULL COMMENT '添加时间',
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员分组';

--
-- 转存表中的数据 `fun_auth_group`
--

INSERT INTO `fun_auth_group` (`id`, `pid`, `title`, `status`, `rules`, `create_time`, `update_time`) VALUES
(1, 0, '超级管理员', 1, '1,44,36,24,43,25,41,29,30,26,27,28,42,32,33,34,35,31,37,38,39,40,2,9,10,11,12,13,14,15,16,17,18,19,20,21,23,3,6,7,8,5,22,45,46,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,', 1554298659, 1599903527),
(3, 1, '其他', 1, '1,44,36,24,25,43,41,30,26,27,28,29,42,32,33,34,35,31,37,38,39,40,66,71,67,68,69,70,2,9,10,11,12,13,14,15,16,17,18,19,20,21,23,3,6,7,8,5,4,22,45,46,51,49,50,52,53,54,55,56,57,58,59,60,61,62,63,', 1554298659, 1603437871);

-- --------------------------------------------------------

--
-- 表的结构 `fun_auth_rule`
--

CREATE TABLE `fun_auth_rule` (
  `id` mediumint(8) UNSIGNED NOT NULL,
  `module` char(50) NOT NULL DEFAULT 'backend',
  `target` varchar(50) DEFAULT '_self' COMMENT '默认窗口',
  `href` char(150) NOT NULL DEFAULT '' COMMENT '链接',
  `title` char(20) NOT NULL DEFAULT '' COMMENT '名字',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型 1菜单，2 非菜单',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态1 可以用，0 所有禁止使用',
  `auth_verfiy` tinyint(2) NOT NULL DEFAULT '1' COMMENT '1验证 0不验证',
  `menu_status` tinyint(1) DEFAULT '0' COMMENT '0 不显示，1 显示',
  `icon` varchar(50) DEFAULT NULL COMMENT '图标样式',
  `condition` char(100) DEFAULT '' COMMENT '条件',
  `pid` int(5) NOT NULL DEFAULT '0' COMMENT '父栏目ID',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限节点';

--
-- 转存表中的数据 `fun_auth_rule`
--

INSERT INTO `fun_auth_rule` (`id`, `module`, `target`, `href`, `title`, `type`, `status`, `auth_verfiy`, `menu_status`, `icon`, `condition`, `pid`, `sort`, `create_time`, `update_time`) VALUES
(1, 'backend', '_self', 'sys', 'Sys', 1, 1, 1, 1, 'layui-icon layui-icon-home', '', 0, 0, 1446535750, 1600398287),
(2, 'backend', '_self', 'auth', 'Auth', 1, 1, 1, 1, 'layui-icon layui-icon-auz', '', 0, 1, 0, 1599889603),
(3, 'backend', '_self', 'auth.auth', 'Auth', 1, 1, 1, 1, 'layui-icon layui-icon-face-smile-fine', '', 2, 50, 1599889618, 1599904964),
(4, 'backend', '_self', 'auth.auth/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-set-fill', '', 3, 2, 0, 1599889664),
(5, 'backend', '_self', 'auth.auth/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 3, 1, 0, 1599889830),
(6, 'backend', '_self', 'auth.auth/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 3, 0, 0, 1599889809),
(7, 'backend', '_self', 'auth.auth/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 3, 0, 0, 1599889818),
(8, 'backend', '_self', 'auth.auth/add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 3, 0, 0, 1599889824),
(9, 'backend', '_self', 'auth.authgroup', 'AuthGroup', 1, 1, 1, 1, 'layui-icon layui-icon-list', '', 2, 0, 0, 1599892083),
(10, 'backend', '_self', 'auth.authgroup/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 9, 0, 0, 1601290654),
(11, 'backend', '_self', 'auth.authgroup/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 9, 0, 0, 1599888854),
(12, 'backend', '_self', 'auth.authgroup/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 9, 0, 0, 1601290636),
(13, 'backend', '_self', 'auth.authgroup/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 9, 0, 0, 1599888865),
(14, 'backend', '_self', 'auth.authgroup/access', 'Access', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 9, 0, 0, 1599888872),
(15, 'backend', '_self', 'auth.authgroup/index', 'List', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 9, 50, 1599888556, 1599888556),
(16, 'backend', '_self', 'auth.admin', 'Admin', 1, 1, 1, 1, 'layui-icon layui-icon-user', '', 2, 1, 1599888969, 1599892086),
(17, 'backend', '_self', 'auth.admin/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-username', '', 16, 0, 1, 1599889517),
(18, 'backend', '_self', 'auth.admin/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 16, 0, 0, 1599889526),
(19, 'backend', '_self', 'auth.admin/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 16, 0, 0, 1599889534),
(20, 'backend', '_self', 'auth.admin/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 16, 0, 0, 1599889542),
(21, 'backend', '_self', 'auth.admin/password', 'Password', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 16, 0, 0, 1599887271),
(22, 'backend', '_self', 'auth.auth/child', 'AddChild', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 3, 50, 1595081813, 1599889875),
(23, 'backend', '_self', 'auth.admin/edit', 'Edit', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 16, 50, 1595508612, 1599903908),
(24, 'backend', '_self', 'sys.adminlog', 'Log', 1, 1, 1, 1, 'layui-icon layui-icon-log', '', 1, 35, 0, 1601290805),
(25, 'backend', '_self', 'sys.adminlog/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 24, 50, 1566264200, 1601289435),
(26, 'backend', '_self', 'sys.config/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-align-center', '', 41, 0, 0, 1599888580),
(27, 'backend', '_self', 'sys.config/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 41, 0, 0, 1599888585),
(28, 'backend', '_self', 'sys.config/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 41, 0, 0, 1599888591),
(29, 'backend', '_self', 'sys.config/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 41, 0, 0, 1599888599),
(30, 'backend', '_self', 'sys.config/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 41, 0, 0, 1599888609),
(31, 'backend', '_self', 'sys.configGroup/index', 'list', 0, 1, 1, 0, 'layui-icon layui-icon-list', '', 42, 5, 0, 1599888205),
(32, 'backend', '_self', 'sys.configGroup/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 42, 0, 0, 1599888314),
(33, 'backend', '_self', 'sys.configGroup/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 42, 0, 0, 1599888304),
(34, 'backend', '_self', 'sys.configGroup/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 42, 0, 0, 1599888294),
(35, 'backend', '_self', 'sys.configGroup/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 42, 0, 0, 1599888254),
(36, 'backend', '_self', 'sys.config/set', 'ConfigSet', 0, 1, 1, 0, 'layui-icon layui-icon-set-sm', '', 1, 5, 1581588960, 1599887056),
(37, 'backend', '_self', 'sys.attach', 'Attach', 1, 1, 1, 1, 'layui-icon layui-icon-picture-fine', '', 1, 50, 1581588790, 1601290492),
(38, 'backend', '_self', 'sys.attach/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-list', '', 37, 50, 1581588855, 1602817404),
(39, 'backend', '_self', 'sys.attach/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 37, 50, 1581588904, 1599888342),
(40, 'backend', '_self', 'sys.attach/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 37, 50, 1581588934, 1599888349),
(41, 'backend', '_self', 'sys.config', 'Config', 1, 1, 1, 1, 'layui-icon layui-icon-face-smile-fine', '', 1, 50, 1599887301, 1600398695),
(42, 'backend', '_self', 'sys.configGroup', 'configGroup', 1, 1, 1, 1, 'layui-icon layui-icon-face-smile-fine', '', 1, 50, 1599888082, 1600396158),
(43, 'backend', '_self', 'sys.adminlog/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-list', '', 24, 50, 1599888429, 1602809070),
(44, 'backend', '_self', 'ajax/uploads', 'Uploads', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 1, 0, 0, 1599887263),
(45, 'backend', '_self', 'member', 'Member', 1, 1, 1, 1, 'layui-icon layui-icon-user', '', 0, 100, 1567327942, 1599892089),
(46, 'backend', '_self', 'member.member', 'Member', 1, 1, 1, 1, 'layui-icon layui-icon-username', '', 45, 1, 1599889321, 1602831544),
(47, 'backend', '_self', 'member.member/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-user', '', 46, 50, 1567327992, 1599889408),
(48, 'backend', '_self', 'member.member/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 46, 0, 0, 1599889767),
(49, 'backend', '_self', 'member.member/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 46, 0, 0, 1599889777),
(50, 'backend', '_self', 'member.member/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 46, 0, 0, 1599889783),
(51, 'backend', '_self', 'member.member/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 46, 0, 0, 1599889788),
(52, 'backend', '_self', 'member.memberLevel', 'MemberLevel', 1, 1, 1, 1, 'layui-icon layui-icon-diamond', '', 45, 50, 1567563846, 1599892226),
(53, 'backend', '_self', 'member.memberLevel/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 52, 50, 1567563846, 1599884256),
(54, 'backend', '_self', 'member.memberLevel/modify', 'Modify', 0, 1, 1, 0, '', '', 52, 50, 1567568251, 1599884255),
(55, 'backend', '_self', 'member.memberLevel/delete', 'Delete', 0, 1, 1, 0, '', '', 52, 50, 1567568283, 1599884255),
(56, 'backend', '_self', 'member.memberLevel/Add', 'Add', 0, 1, 1, 0, '', '', 52, 50, 1567568305, 1599884254),
(57, 'backend', '_self', 'member.memberLevel/edit', 'Edit', 0, 1, 1, 0, '', '', 52, 50, 1567568357, 1599884254),
(58, 'backend', '_self', 'member.memberGroup', 'memberGroup', 1, 1, 1, 1, 'layui-icon layui-icon-face-smile-fine', '', 45, 50, 1599889050, 1599892092),
(59, 'backend', '_self', 'member.memberGroup/index', 'List', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 58, 50, 1567563846, 1599889082),
(60, 'backend', '_self', 'member.memberGroup/modify', 'Modify', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 58, 50, 1567568251, 1599884253),
(61, 'backend', '_self', 'member.memberGroup/delete', 'Delete', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 58, 50, 1567568283, 1599884252),
(62, 'backend', '_self', 'member.memberGroup/Add', 'Add', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 58, 50, 1567568305, 1599884252),
(63, 'backend', '_self', 'member.memberGroup/edit', 'Edit', 0, 1, 1, 0, 'layui-icon layui-icon-diamond', '', 58, 50, 1567568357, 1599884251),
(64, 'backend', '_self', 'addon', 'Addon', 1, 1, 1, 1, 'layui-icon layui-icon-app', '', 0, 501, 1580880615, 1599892096),
(65, 'backend', '_self', 'addon/index', 'List', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(66, 'backend', '_self', 'sys.languages', 'languages', 1, 1, 1, 1, 'layui-icon layui-icon-rate', '', 1, 50, 1603427312, 1603428082),
(67, 'backend', '_self', 'sys.languages/index', 'List', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(68, 'backend', '_self', 'sys.languages/delete', 'delete', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(69, 'backend', '_self', 'sys.languages/modify', 'Modify', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(70, 'backend', '_self', 'sys.languages/add', 'Add', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(71, 'backend', '_self', 'sys.languages/edit', 'Edit', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(72, 'backend', '_self', 'addon/install', 'Install', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(73, 'backend', '_self', 'addon/modify', 'modify', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(74, 'backend', '_self', 'addon/config', 'Config', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(75, 'backend', '_self', 'addon/uninstall', 'Uninstall', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(76, 'addon', '_self', 'database', '数据库', 1, 1, 1, 1, 'fa fa-database', '', 0, 0, 1603601991, 1603601991),
(77, 'addon', '_self', 'addons/database/backend/index/index', '数据列表', 1, 1, 1, 1, 'fa fa-comments-o', '', 76, 0, 1603601991, 1603601991),
(78, 'addon', '_self', 'addons/database/backend/index//optimize', '数据优化', 1, 1, 1, 0, NULL, '', 77, 0, 1603601991, 1603601991),
(79, 'addon', '_self', 'addons/database/backend/index/repair', '数据修复', 1, 1, 1, 0, NULL, '', 77, 0, 1603601991, 1603601991),
(80, 'addon', '_self', 'addons/database/backend/index/backup', '数据备份', 1, 1, 1, 0, NULL, '', 77, 0, 1603601991, 1603601991),
(81, 'addon', '_self', 'addons/database/backend/index/restore', '备份列表', 1, 1, 1, 1, 'fa fa-comments-o', '', 76, 0, 1603601991, 1603601991),
(82, 'addon', '_self', 'addons/database/backend/index/import', '导入数据', 1, 1, 1, 0, NULL, '', 81, 0, 1603601991, 1603601991),
(83, 'addon', '_self', 'addons/database/backend/index/downFile', '下载数据', 1, 1, 1, 0, NULL, '', 81, 0, 1603601991, 1603601991),
(84, 'addon', '_self', 'addons/database/backend/index/delSqlFiles', '删除数据', 1, 1, 1, 0, NULL, '', 81, 0, 1603601991, 1603601991),
(353, 'addon', '_self', 'cms', 'cms管理', 1, 1, 1, 1, 'layui-icon layui-icon-component', '', 0, 0, 1603606017, 1603606017),
(354, 'addon', '_self', 'addons/cms/backend/cmsCategory', 'Category', 1, 1, 1, 1, 'layui-icon-template-1', '', 353, 0, 1603606017, 1603606017),
(355, 'addon', '_self', 'addons/cms/backend/cmsCategory/index', '栏目', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(356, 'addon', '_self', 'addons/cms/backend/cmsCategory/add', '添加分类', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(357, 'addon', '_self', 'addons/cms/backend/cmsCategory/edit', '编辑分类', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(358, 'addon', '_self', 'addons/cms/backend/cmsCategory/delete', '删除分类', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(359, 'addon', '_self', 'addons/cms/backend/cmsCategory/modify', '分类状态', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(360, 'addon', '_self', 'addons/cms/backend/cmsCategory/content', '栏目内容', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(361, 'addon', '_self', 'addons/cms/backend/cmsCategory/addinfo', '添加内容', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(362, 'addon', '_self', 'addons/cms/backend/cmsCategory/contentDel', '内容删除', 1, 1, 1, 0, NULL, '', 354, 0, 1603606017, 1603606017),
(363, 'addon', '_self', 'addons/cms/backend/cmsCategory/flashCache', '清除缓存', 1, 1, 1, 0, NULL, '', 354, 0, 1603606018, 1603606018),
(364, 'addon', '_self', 'addons/cms/backend/cmsCategory/list', 'Categorylist', 1, 1, 1, 1, 'layui-icon-template-1', '', 353, 0, 1603606018, 1603606018),
(365, 'addon', '_self', 'addons/cms/backend/cmsCategory/board', '栏目面板', 1, 1, 1, 0, NULL, '', 364, 0, 1603606018, 1603606018),
(366, 'addon', '_self', 'addons/cms/backend/cmsCategory/contentState', '栏目内容状态', 1, 1, 1, 0, NULL, '', 364, 0, 1603606018, 1603606018),
(367, 'addon', '_self', 'addons/cms/backend/cmsModule', 'Module', 1, 1, 1, 1, 'layui-icon-template-1', '', 353, 0, 1603606018, 1603606018),
(368, 'addon', '_self', 'addons/cms/backend/cmsModule/index', 'list', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(369, 'addon', '_self', 'addons/cms/backend/cmsModule/add', 'add', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(370, 'addon', '_self', 'addons/cms/backend/cmsModule/edit', 'edit', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(371, 'addon', '_self', 'addons/cms/backend/cmsModule/modify', 'modify', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(372, 'addon', '_self', 'addons/cms/backend/cmsModule/delete', 'delete', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(373, 'addon', '_self', 'addons/cms/backend/cmsModule/field', 'field', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(374, 'addon', '_self', 'addons/cms/backend/cmsModule/fieldAdd', 'fieldadd', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(375, 'addon', '_self', 'addons/cms/backend/cmsModule/fieldEdit', 'fieldedit', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(376, 'addon', '_self', 'addons/cms/backend/cmsModule/fielddelete', 'fielddelete', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(377, 'addon', '_self', 'addons/cms/backend/cmsModule/fieldmodify', 'fieldmodify', 1, 1, 1, 0, NULL, '', 367, 0, 1603606018, 1603606018),
(378, 'addon', '_self', 'addons/cms/backend/cmsLink', 'Link', 1, 1, 1, 1, 'layui-icon layui-icon-unlink', '', 353, 0, 1603606019, 1603606019),
(379, 'addon', '_self', 'addons/cms/backend/cmsLink/index', 'List', 1, 1, 1, 0, NULL, '', 378, 0, 1603606019, 1603606019),
(380, 'addon', '_self', 'addons/cms/backend/cmsLink/add', 'Add', 1, 1, 1, 0, NULL, '', 378, 0, 1603606019, 1603606019),
(381, 'addon', '_self', 'addons/cms/backend/cmsLink/edit', 'Edit', 1, 1, 1, 0, NULL, '', 378, 0, 1603606019, 1603606019),
(382, 'addon', '_self', 'addons/cms/backend/cmsLink/modify', 'modify', 1, 1, 1, 0, NULL, '', 378, 0, 1603606019, 1603606019),
(383, 'addon', '_self', 'addons/cms/backend/cmsLink/delete', 'delete', 1, 1, 1, 0, NULL, '', 378, 0, 1603606019, 1603606019),
(384, 'addon', '_self', 'addons/cms/backend/cmsAdv/index', 'Adv', 1, 1, 1, 1, 'layui-icon layui-icon-component', '', 353, 0, 1603606019, 1603606019),
(385, 'addon', '_self', 'addons/cms/backend/cmsAdv/add', '添加广告', 1, 1, 1, 0, NULL, '', 384, 0, 1603606019, 1603606019),
(386, 'addon', '_self', 'addons/cms/backend/cmsAdv/edit', '编辑广告', 1, 1, 1, 0, NULL, '', 384, 0, 1603606019, 1603606019),
(387, 'addon', '_self', 'addons/cms/backend/cmsAdv/modify', '广告状态', 1, 1, 1, 0, NULL, '', 384, 0, 1603606019, 1603606019),
(388, 'addon', '_self', 'addons/cms/backend/cmsAdv/delete', '删除广告', 1, 1, 1, 0, NULL, '', 384, 0, 1603606019, 1603606019),
(389, 'addon', '_self', 'addons/cms/backend/cmsAdvPos', 'Advpos', 1, 1, 1, 1, 'layui-icon layui-icon-unlink\r\n', '', 353, 0, 1603606019, 1603606019),
(390, 'addon', '_self', 'addons/cms/backend/cmsAdvPos/index', 'List', 1, 1, 1, 0, NULL, '', 389, 0, 1603606019, 1603606019),
(391, 'addon', '_self', 'addons/cms/backend/cmsAdvPos/add', 'add', 1, 1, 1, 0, NULL, '', 389, 0, 1603606019, 1603606019),
(392, 'addon', '_self', 'addons/cms/backend/cmsAdvPos/edit', 'edit', 1, 1, 1, 0, NULL, '', 389, 0, 1603606019, 1603606019),
(393, 'addon', '_self', 'addons/cms/backend/cmsAdvPos/modify', 'modify', 1, 1, 1, 0, NULL, '', 389, 0, 1603606019, 1603606019),
(394, 'addon', '_self', 'addons/cms/backend/cmsAdvPos/delete', 'Delete', 1, 1, 1, 0, NULL, '', 389, 0, 1603606019, 1603606019),
(395, 'addon', '_self', 'addons/cms/backend/cmsDebris', 'Debris', 1, 1, 1, 1, 'layui-icon-list', '', 353, 0, 1603606020, 1603606020),
(396, 'addon', '_self', 'addons/cms/backend/cmsDebris/index', 'List', 1, 1, 1, 0, NULL, '', 395, 0, 1603606020, 1603606020),
(397, 'addon', '_self', 'addons/cms/backend/cmsDebris/add', 'add', 1, 1, 1, 0, NULL, '', 395, 0, 1603606020, 1603606020),
(398, 'addon', '_self', 'addons/cms/backend/cmsDebris/edit', 'edit', 1, 1, 1, 0, NULL, '', 395, 0, 1603606020, 1603606020),
(399, 'addon', '_self', 'addons/cms/backend/cmsDebris/modify', 'modify', 1, 1, 1, 0, NULL, '', 395, 0, 1603606020, 1603606020),
(400, 'addon', '_self', 'addons/cms/backend/cmsDebris/delete', 'delete', 1, 1, 1, 0, NULL, '', 395, 0, 1603606020, 1603606020),
(401, 'addon', '_self', 'addons/cms/backend/cmsDebrisPos', 'DebrisPosition', 1, 1, 1, 1, 'layui-icon layui-icon-location', '', 353, 0, 1603606020, 1603606020),
(402, 'addon', '_self', 'addons/cms/backend/cmsDebrisPos/add', 'add', 1, 1, 1, 0, NULL, '', 401, 0, 1603606020, 1603606020),
(403, 'addon', '_self', 'addons/cms/backend/cmsDebrisPos/edit', 'edit', 1, 1, 1, 0, NULL, '', 401, 0, 1603606020, 1603606020),
(404, 'addon', '_self', 'addons/cms/backend/cmsDebrisPos/modify', 'modify', 1, 1, 1, 0, NULL, '', 401, 0, 1603606020, 1603606020),
(405, 'addon', '_self', 'addons/cms/backend/cmsDebrisPos/delete', 'delete', 1, 1, 1, 0, NULL, '', 401, 0, 1603606020, 1603606020),
(406, 'addon', '_self', 'addons/cms/backend/cmsTags/index', 'Tags', 1, 1, 1, 1, 'layui-icon layui-icon-face-smile', '', 353, 0, 1603606020, 1603606020),
(407, 'addon', '_self', 'addons/cms/backend/cmsTags/add', 'add', 1, 1, 1, 0, NULL, '', 406, 0, 1603606020, 1603606020),
(408, 'addon', '_self', 'addons/cms/backend/cmsTags/edit', 'edit', 1, 1, 1, 0, NULL, '', 406, 0, 1603606020, 1603606020),
(409, 'addon', '_self', 'addons/cms/backend/cmsTags/delete', 'delete', 1, 1, 1, 0, NULL, '', 406, 0, 1603606020, 1603606020),
(410, 'addon', '_self', 'admin/cms.cmsDiyform/index', 'Diyform', 1, 1, 1, 1, 'layui-icon layui-icon-form', '', 353, 0, 1603606020, 1603606020),
(411, 'addon', '_self', 'admin/cms.cmsDiyform/add', 'add', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(412, 'addon', '_self', 'admin/cms.cmsDiyform/edit', 'edit', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(413, 'addon', '_self', 'admin/cms.cmsDiyform/delete', 'delete', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(414, 'addon', '_self', 'admin/cms.cmsDiyform/modify', 'modify', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(415, 'addon', '_self', 'admin/cms.cmsDiyform/datalist', 'datalist', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(416, 'addon', '_self', 'admin/cms.cmsDiyform/datadel', 'datadel', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(417, 'addon', '_self', 'admin/cms.cmsDiyform/field', 'field', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(418, 'addon', '_self', 'admin/cms.cmsDiyform/fieldadd', 'fieldadd', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021),
(419, 'addon', '_self', 'admin/cms.cmsDiyform/fielddel', 'fielddel', 1, 1, 1, 0, NULL, '', 410, 0, 1603606021, 1603606021);

-- --------------------------------------------------------

--
-- 表的结构 `fun_config`
--

CREATE TABLE `fun_config` (
  `id` smallint(5) NOT NULL,
  `code` varchar(30) NOT NULL,
  `default_value` varchar(50) DEFAULT NULL COMMENT '默认值',
  `extra` varchar(500) DEFAULT NULL COMMENT '配置值',
  `value` mediumtext,
  `remark` varchar(100) DEFAULT '解释,备注',
  `verfiy` varchar(30) DEFAULT '',
  `type` varchar(30) DEFAULT 'text' COMMENT 'text',
  `group` varchar(20) DEFAULT 'site',
  `status` tinyint(1) DEFAULT '1',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配置参数表';

--
-- 转存表中的数据 `fun_config`
--

INSERT INTO `fun_config` (`id`, `code`, `default_value`, `extra`, `value`, `remark`, `verfiy`, `type`, `group`, `status`, `create_time`, `update_time`) VALUES
(1, 'site_name', '', '', 'FUN管理系统', '网站名称', '0', 'text', 'site', 1, 0, 1602808542),
(2, 'site_phone', '', '', '3', '网站客服服务电话', '0', 'text', 'site', 1, 0, 1581831391),
(3, 'site_state', '', '', '1', '状态', '0', 'radio', 'site', 1, 0, 1581825436),
(4, 'site_logo', '', '', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', '网站logo图1', '0', 'image', 'site', 1, 0, 1603438560),
(5, 'site_mobile_logo', '', '', 'site_mobile_logo.png', '默认网站手机端logo', '0', 'image', 'site', 1, 0, 1583583460),
(6, 'site_logowx', '', '', 'site_logowx.jpg', '微信网站二维码', '0', 'image', 'site', 1, 0, 1583583460),
(7, 'site_icp', '', '', '2', 'ICP备案号', '0', 'text', 'site', 1, 0, 1583583461),
(8, 'site_tel400', '', '', '40002541852', '解释,备注', '0', 'text', 'site', 1, 0, 0),
(9, 'site_email', '', '', '15151711601@qq.com', '电子邮件', '0', 'text', 'site', 1, 0, 0),
(10, 'site_copyright', '', '', '© 2020 FunAdmin.com - 版权所有FunAdmin', '底部版权信息', '0', 'text', 'site', 1, 0, 1603435866),
(11, 'app_debug', '', '0\n1', '1', '测试模式', '', 'radio', 'site', 1, 0, 1604132931),
(18, 'email_addr', '', '', '994927909@qq.com', '邮箱发件人地址', '0', 'text', 'email', 1, 0, 0),
(19, 'email_id', '', '', '994927909@qq.com', '身份验证用户名', '0', 'text', 'email', 1, 0, 0),
(20, 'email_pass', '', '', '11211', '用户名密码', '0', 'text', 'email', 1, 0, 0),
(21, 'email_secure', '', '', 'smtp', '邮箱发送协议', '0', 'text', 'email', 1, 0, 0),
(22, 'upload_file_type', '', '', 'mp4,mp3,png,gif,jpg,jpeg,webp', '图片上传保存方式', '0', 'text', 'upload', 1, 0, 1602723793),
(24, 'alioss_accessid', '', '', '', 'accessid', '0', 'text', 'alioss', 1, 0, 0),
(25, 'alioss_accesssecret', '', '', '', 'oss_accesssecret', '0', 'text', 'alioss', 1, 0, 0),
(26, 'alioss_bucket', '', '', '', 'oss_bucket', '0', 'text', 'alioss', 1, 0, 0),
(27, 'alioss_endpoint', '', '', '', 'oss_endpoint', '0', 'text', 'alioss', 1, 0, 0),
(28, 'aliendpoint_type', '', '', '0', 'aliendpoint_type', '0', 'text', 'alioss', 1, 0, 0),
(31, 'qq_isuse', '', '', '1', '是否使用QQ互联', '0', 'text', 'qq', 1, 0, 0),
(32, 'qq_appid', '', '', '', 'qq互联id', '0', 'text', 'qq', 1, 0, 0),
(33, 'qq_appkey', '', '', '', 'qq秘钥', '0', 'text', 'qq', 1, 0, 0),
(34, 'sina_isuse', '', '', '1', '是的使用微博登录', '0', 'text', 'sina', 1, 0, 0),
(35, 'sina_wb_akey', '', '', '', '新浪id', '0', 'text', 'sina', 1, 0, 0),
(36, 'sina_wb_skey', '', '', '', '新浪秘钥', '0', 'text', 'sina', 1, 0, 0),
(37, 'sms_register', '', '', '0', '是否手机注册', '0', 'text', 'mobile', 1, 0, 0),
(38, 'sms_login', '', '', '0', '是否手机登录', '0', 'text', 'mobile', 1, 0, 0),
(39, 'sms_password', '', '', '0', '是否手机找回密码', '0', 'text', 'mobile', 1, 0, 0),
(44, 'site_licence', '', '', '', '营业执照', '0', 'text', 'site', 1, 0, 1595419166),
(45, 'site_domain', '', '', 'https://www.FunAdmin.com', '网站地址', '0', 'text', 'site', 1, 0, 0),
(46, 'upload_file_max', '', '', '2048', '最大文件上传大小', '0', 'text', 'upload', 1, 0, 0),
(47, 'site_seo_title', '', '', 'FunAdmin', '首页标题', '0', 'textarea', 'site', 1, 0, 0),
(48, 'site_seo_keywords', '', '', 'FunAdmin,LAYUI,THINKPHP6', '首页关键词', '0', 'textarea', 'site', 1, 0, 1603266121),
(49, 'site_seo_desc', '', '', 'FunAdmin,LAYUI,THINKPHP6,Require', '首页描述', '', 'textarea', 'site', 1, 0, 1601288743),
(50, 'upload_water', '', '', '', '水印开始关闭', '0', 'image', 'upload', 1, 0, 1601287987),
(51, 'upload_water_position', '', '', '', '水印位置', '0', 'text', 'upload', 1, 0, 0),
(59, 'upload_driver', 'local', '', 'alioss', '上传配置', '0', 'text', 'upload', 1, 1594213311, 1595419144),
(60, 'site_version', '1.2', '', '1.0', '版本', '0', 'text', 'site', 1, 0, 1600828560),
(61, 'qiniuoss_accesskey', '', '', '', '解释,备注', '0', 'textarea', 'qiniuoss', 1, 0, 1603266108),
(62, 'qiniuoss_accesssecret', '', '', '', '解释,备注', '0', 'textarea', 'qiniuoss', 1, 0, 0),
(63, 'qiniuoss_bucket', '', '', '', '解释,备注', '0', 'textarea', 'qiniuoss', 1, 0, 0),
(64, 'qiniuoss_cdn_domain', '', '', '', '解释,备注', '0', 'text', 'qiniuoss', 1, 0, 0),
(65, 'tecoss_region', '', '', '', '解释,备注', '0', 'textarea', 'teccos', 1, 0, 0),
(66, 'tecoss_secretId', '', '', '', '解释,备注', '0', 'textarea', 'teccos', 1, 0, 0),
(67, 'tecoss_secretKey', '', '', '', '解释,备注', '0', 'textarea', 'teccos', 1, 0, 0),
(68, 'tecoss_bucket', '1', '1:是\n2:否\n3:其他', '2', '解释,备注\n1:是\n2:否\n3:其他', '', 'radio', 'teccos', 1, 0, 1601279971),
(69, 'tecoss_cdn_domain', '0', '1:是\n2:否', '2020-09-01 00:00:00', '解释,备注  中间用分隔符分割', '', 'text', 'teccos', 1, 0, 1602299856);

-- --------------------------------------------------------

--
-- 表的结构 `fun_config_group`
--

CREATE TABLE `fun_config_group` (
  `id` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `title` varchar(60) NOT NULL,
  `status` tinyint(1) DEFAULT '1' COMMENT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_config_group`
--

INSERT INTO `fun_config_group` (`id`, `name`, `title`, `status`) VALUES
(1, 'site', '网站', 1),
(2, 'qq', 'qq', 1),
(3, 'sms', '短信', 1),
(4, 'email', '邮箱', 1),
(5, 'alioss', '阿里oss', 1),
(6, 'sina', '新浪', 1),
(8, 'upload', '上传', 1),
(9, 'mobile', '手机', 1),
(10, 'baidu', '百度配置', 1),
(11, 'teccos', '腾讯oss', 1),
(12, 'qiniuoss', '七牛oss', 1);

-- --------------------------------------------------------

--
-- 表的结构 `fun_field_type`
--

CREATE TABLE `fun_field_type` (
  `id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL COMMENT '字段类型',
  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '中文类型名',
  `sort` int(4) NOT NULL DEFAULT '0' COMMENT '排序',
  `default_define` varchar(128) NOT NULL DEFAULT '' COMMENT '默认定义',
  `isoption` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否需要设置选项',
  `istring` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否自由字符',
  `rules` varchar(256) NOT NULL DEFAULT '' COMMENT '验证规则'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COMMENT='字段类型表';

--
-- 转存表中的数据 `fun_field_type`
--

INSERT INTO `fun_field_type` (`id`, `name`, `title`, `sort`, `default_define`, `isoption`, `istring`, `rules`) VALUES
(1, 'text', '输入框', 1, 'varchar(255) NOT NULL DEFAULT \'\'', 0, 1, ''),
(2, 'checkbox', '复选框', 2, 'varchar(50) NOT NULL DEFAULT \'\'', 1, 0, ''),
(3, 'textarea', '多行文本', 3, 'varchar(255) NOT NULL DEFAULT \'\'', 0, 1, ''),
(4, 'radio', '单选按钮', 4, 'char(10) NOT NULL DEFAULT \'\'', 1, 0, ''),
(5, 'switch', '开关', 5, 'tinyint(2) UNSIGNED NOT NULL DEFAULT \'0\'', 0, 0, 'isBool'),
(6, 'array', '数组', 6, 'varchar(512) NOT NULL DEFAULT \'\'', 0, 0, ''),
(7, 'select', '下拉框', 7, 'varchar(10) NOT NULL DEFAULT \'\'', 1, 0, ''),
(8, 'image', '单张图', 8, 'varchar(255) NOT NULL DEFAULT \'\'', 0, 0, ''),
(9, 'tags', '标签', 10, 'varchar(255) NOT NULL DEFAULT \'\'', 0, 1, ''),
(10, 'number', '数字', 11, 'int(11) UNSIGNED NOT NULL DEFAULT \'0\'', 0, 0, 'isNumber'),
(11, 'datetime', '日期', 12, 'int(11) UNSIGNED NOT NULL DEFAULT \'0\'', 0, 0, ''),
(12, 'ueditor', '百度编辑器', 13, 'longtext NOT NULL  DEFAULT \'\'', 0, 1, ''),
(13, 'images', '多张图', 9, 'varchar(256) NOT NULL DEFAULT \'\'', 0, 0, ''),
(14, 'color', '颜色值', 17, 'varchar(7) NOT NULL DEFAULT \'\'', 0, 0, ''),
(15, 'file', '单文件', 15, 'varcgar(255) NOT NULL DEFAULT \' \'', 0, 0, ''),
(16, 'files', '多文件', 16, 'varchar(255) NOT NULL DEFAULT \' \'', 0, 0, ''),
(17, 'wangEditor', 'wang编辑器', 0, 'longtext NOT NULL  DEFAULT \'\'', 0, 0, ''),
(18, 'tags', '标签', 0, 'varchar(255) NOT NULL DEFAULT \' \'', 0, 0, ''),
(19, 'hidden', '隐藏域', 0, 'varchar(255) NOT NULL DEFAULT \' \'', 0, 0, ''),
(21, 'range', '日期范围', 0, 'varchar(255) NOT NULL DEFAULT \' \'', 0, 0, '');

-- --------------------------------------------------------

--
-- 表的结构 `fun_field_verfiy`
--

CREATE TABLE `fun_field_verfiy` (
  `verfiy` varchar(50) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_field_verfiy`
--

INSERT INTO `fun_field_verfiy` (`verfiy`, `title`) VALUES
('required', '必须'),
('title', '标题'),
('required|phone', '手机'),
('email', '邮箱'),
('required|number', '数字'),
('date', '日期'),
('url', '地址'),
('identity', '身份证'),
('pass', '密码');

-- --------------------------------------------------------

--
-- 表的结构 `fun_languages`
--

CREATE TABLE `fun_languages` (
  `id` int(11) UNSIGNED NOT NULL,
  `name` varchar(20) NOT NULL,
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_languages`
--

INSERT INTO `fun_languages` (`id`, `name`, `create_time`, `update_time`, `is_default`) VALUES
(1, 'zh-cn', 0, 0, 1),
(2, 'en-us', 0, 0, 0),
(3, 'zh_tw', 1603434544, 1603434599, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_member`
--

CREATE TABLE `fun_member` (
  `id` mediumint(8) UNSIGNED NOT NULL COMMENT '表id',
  `merchant_id` int(11) DEFAULT '1',
  `group_id` int(11) DEFAULT '1',
  `email` varchar(60) NOT NULL DEFAULT '' COMMENT '邮件',
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(100) NOT NULL DEFAULT '' COMMENT '密码',
  `paypwd` varchar(32) DEFAULT NULL COMMENT '支付密码',
  `sex` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '0 保密 1 男 2 女',
  `birthday` int(11) NOT NULL DEFAULT '0' COMMENT '生日',
  `underling_number` int(5) DEFAULT '0' COMMENT '用户下线总数',
  `address_id` mediumint(8) UNSIGNED NOT NULL DEFAULT '0' COMMENT '默认收货地址',
  `create_time` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '注册时间',
  `update_time` int(11) DEFAULT NULL,
  `last_login` int(11) UNSIGNED NOT NULL DEFAULT '0' COMMENT '最后登录时间',
  `login_num` int(11) DEFAULT '0' COMMENT '登录次数',
  `last_ip` varchar(15) NOT NULL DEFAULT '' COMMENT '最后登录ip',
  `qq` varchar(20) NOT NULL DEFAULT '' COMMENT 'QQ',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号码',
  `mobile_validated` tinyint(3) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否验证手机',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `province` int(6) DEFAULT '0' COMMENT '省份',
  `city` int(6) DEFAULT '0' COMMENT '市区',
  `district` int(6) DEFAULT '0' COMMENT '县',
  `email_validated` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' COMMENT '是否验证电子邮箱',
  `realname` varchar(50) DEFAULT NULL,
  `nickname` varchar(50) DEFAULT NULL COMMENT '第三方返回昵称',
  `level_id` tinyint(1) DEFAULT '1' COMMENT '会员等级',
  `discount` decimal(10,2) DEFAULT '1.00' COMMENT '会员折扣，默认1不享受',
  `status` tinyint(1) DEFAULT '1' COMMENT '是否被锁定冻结 0 冻结，1 正常',
  `is_distribut` tinyint(1) DEFAULT '0' COMMENT '是否为分销商 0 否 1 是',
  `first_leader` int(11) DEFAULT '0' COMMENT '第一个上级',
  `second_leader` int(11) DEFAULT '0' COMMENT '第二个上级',
  `third_leader` int(11) DEFAULT '0' COMMENT '第三个上级',
  `token` varchar(64) DEFAULT '' COMMENT '用于app 授权类似于session_id',
  `message_mask` tinyint(1) NOT NULL DEFAULT '63' COMMENT '消息掩码',
  `push_id` varchar(30) NOT NULL DEFAULT '' COMMENT '推送id',
  `distribut_level` tinyint(2) DEFAULT '0' COMMENT '分销商等级',
  `is_vip` tinyint(1) DEFAULT '0' COMMENT '是否为VIP ：0不是，1是',
  `min_qrcode` varchar(255) DEFAULT NULL COMMENT '小程序专属二维码',
  `poster` varchar(255) DEFAULT NULL COMMENT '专属推广海报'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

--
-- 转存表中的数据 `fun_member`
--

INSERT INTO `fun_member` (`id`, `merchant_id`, `group_id`, `email`, `username`, `password`, `paypwd`, `sex`, `birthday`, `underling_number`, `address_id`, `create_time`, `update_time`, `last_login`, `login_num`, `last_ip`, `qq`, `mobile`, `mobile_validated`, `avatar`, `province`, `city`, `district`, `email_validated`, `realname`, `nickname`, `level_id`, `discount`, `status`, `is_distribut`, `first_leader`, `second_leader`, `third_leader`, `token`, `message_mask`, `push_id`, `distribut_level`, `is_vip`, `min_qrcode`, `poster`) VALUES
(4, 1, 1, '994927909@qq.com', '15647244355', '', '', 1, 0, 0, 0, 1596181549, 1603266096, 0, 0, '', '', '18397423845', 0, '\\storage\\avatar/20200731\\779df29efafbb6585286e64e0561e3d6.png', 0, 0, 0, 0, '', '', 1, '1.00', 1, 0, 0, 0, 0, '', 63, '', 0, 0, '', '');

-- --------------------------------------------------------

--
-- 表的结构 `fun_member_account`
--

CREATE TABLE `fun_member_account` (
  `id` int(10) UNSIGNED NOT NULL,
  `merchant_id` int(10) UNSIGNED DEFAULT '1' COMMENT '商户id',
  `member_id` int(10) UNSIGNED DEFAULT '0' COMMENT '用户id',
  `level` int(11) DEFAULT '-1' COMMENT '会员等级',
  `user_money` decimal(10,2) DEFAULT '0.00' COMMENT '当前余额',
  `accumulate_money` decimal(10,2) DEFAULT '0.00' COMMENT '累计余额',
  `give_money` decimal(10,2) DEFAULT '0.00' COMMENT '累计赠送余额',
  `consume_money` decimal(10,2) DEFAULT '0.00' COMMENT '累计消费金额',
  `frozen_money` decimal(10,2) DEFAULT '0.00' COMMENT '冻结金额',
  `user_integral` int(11) DEFAULT '0' COMMENT '当前积分',
  `accumulate_integral` int(11) DEFAULT '0' COMMENT '累计积分',
  `give_integral` int(11) DEFAULT '0' COMMENT '累计赠送积分',
  `consume_integral` decimal(10,2) DEFAULT '0.00' COMMENT '累计消费积分',
  `frozen_integral` int(11) DEFAULT '0' COMMENT '冻结积分',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态[-1:删除;0:禁用;1启用]'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员_账户统计表';

-- --------------------------------------------------------

--
-- 表的结构 `fun_member_address`
--

CREATE TABLE `fun_member_address` (
  `id` int(10) NOT NULL COMMENT '主键',
  `merchant_id` int(10) UNSIGNED DEFAULT '1' COMMENT '商户id',
  `member_id` int(11) UNSIGNED DEFAULT '0' COMMENT '用户id',
  `province_id` int(10) UNSIGNED DEFAULT '0' COMMENT '省id',
  `city_id` int(10) UNSIGNED DEFAULT '0' COMMENT '市id',
  `area_id` int(10) UNSIGNED DEFAULT '0' COMMENT '区id',
  `district_id` int(10) DEFAULT NULL,
  `address_name` varchar(200) DEFAULT '' COMMENT '地址',
  `address_details` varchar(200) DEFAULT '' COMMENT '详细地址',
  `is_default` tinyint(4) UNSIGNED DEFAULT '0' COMMENT '默认地址',
  `zip_code` int(10) UNSIGNED DEFAULT '0' COMMENT '邮编',
  `consignee` varchar(100) DEFAULT NULL COMMENT '收件人',
  `realname` varchar(100) DEFAULT '' COMMENT '真实姓名',
  `home_phone` varchar(20) DEFAULT '' COMMENT '家庭号码',
  `mobile` varchar(20) DEFAULT '' COMMENT '手机号码',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态(-1:已删除,0:禁用,1:正常)',
  `created_at` int(10) UNSIGNED DEFAULT '0' COMMENT '创建时间',
  `updated_at` int(10) UNSIGNED DEFAULT '0' COMMENT '修改时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户_收货地址表';

-- --------------------------------------------------------

--
-- 表的结构 `fun_member_group`
--

CREATE TABLE `fun_member_group` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) DEFAULT '' COMMENT '组名',
  `rules` mediumtext COMMENT '权限节点',
  `create_time` int(10) DEFAULT NULL COMMENT '添加时间',
  `update_time` int(10) DEFAULT NULL COMMENT '更新时间',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='会员组表' ROW_FORMAT=COMPACT;

--
-- 转存表中的数据 `fun_member_group`
--

INSERT INTO `fun_member_group` (`id`, `name`, `rules`, `create_time`, `update_time`, `status`) VALUES
(1, '默认组', '', 1515386468, 1599289143, 1),
(2, '通栏', NULL, 1599289199, 1600400823, 1);

-- --------------------------------------------------------

--
-- 表的结构 `fun_member_level`
--

CREATE TABLE `fun_member_level` (
  `id` smallint(4) UNSIGNED NOT NULL COMMENT '表id',
  `name` varchar(30) DEFAULT NULL COMMENT '头衔名称',
  `amount` decimal(10,2) DEFAULT NULL COMMENT '等级必要金额',
  `discount` smallint(4) DEFAULT '100' COMMENT '折扣',
  `status` tinyint(1) DEFAULT '1',
  `sort` int(5) DEFAULT '0',
  `description` varchar(200) DEFAULT NULL COMMENT '头街 描述',
  `create_time` int(11) DEFAULT NULL,
  `update_time` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户等级表';

--
-- 转存表中的数据 `fun_member_level`
--

INSERT INTO `fun_member_level` (`id`, `name`, `amount`, `discount`, `status`, `sort`, `description`, `create_time`, `update_time`) VALUES
(1, '倔强青铜', '0.00', 100, 1, 0, '', 0, 1599304408),
(2, '秩序白银', '1000.00', 99, 1, 0, '', 0, 1597653767),
(3, '荣耀黄金', '3000.00', 94, 1, 0, '', 0, 1597652473),
(4, '尊贵铂金', '10000.00', 95, 1, 0, '', 0, 1595411224),
(5, '永恒钻石', '50000.00', 93, 1, 0, '', 0, 1597652927),
(7, '默认', '11.00', 100, 1, 0, '', 1599307077, 1602818058);

-- --------------------------------------------------------

--
-- 表的结构 `fun_member_third`
--

CREATE TABLE `fun_member_third` (
  `id` int(10) UNSIGNED NOT NULL COMMENT 'ID',
  `member_id` int(10) UNSIGNED NOT NULL DEFAULT '1' COMMENT '会员ID',
  `platform` varchar(30) NOT NULL DEFAULT '' COMMENT '第三方应用 weixin /qq /sina ',
  `unionid` varchar(80) DEFAULT NULL COMMENT 'unionid',
  `openid` varchar(50) NOT NULL DEFAULT '' COMMENT '第三方唯一ID',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '第三方会员昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `sex` tinyint(1) DEFAULT '-1' COMMENT '性别',
  `birthday` date DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL COMMENT '国家',
  `province` varchar(100) DEFAULT NULL COMMENT '省',
  `city` varchar(100) DEFAULT NULL COMMENT '市',
  `access_token` varchar(255) NOT NULL DEFAULT '' COMMENT 'AccessToken',
  `refresh_token` varchar(255) NOT NULL DEFAULT 'RefreshToken',
  `expires_in` int(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '有效期',
  `create_time` int(10) UNSIGNED DEFAULT NULL COMMENT '创建时间',
  `update_time` int(10) UNSIGNED DEFAULT NULL COMMENT '更新时间',
  `login_time` int(10) UNSIGNED DEFAULT NULL COMMENT '登录时间',
  `expiretime` int(10) UNSIGNED DEFAULT NULL COMMENT '过期时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='第三方登录表';

-- --------------------------------------------------------

--
-- 表的结构 `fun_oauth2_client`
--

CREATE TABLE `fun_oauth2_client` (
  `id` int(11) UNSIGNED NOT NULL,
  `merchant_id` int(10) UNSIGNED DEFAULT '1' COMMENT '商户id',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `appid` varchar(64) NOT NULL,
  `appsecret` varchar(150) NOT NULL,
  `redirect_uri` varchar(2000) NOT NULL DEFAULT '' COMMENT '回调Url',
  `remark` varchar(200) DEFAULT NULL COMMENT '备注',
  `group` varchar(30) DEFAULT '' COMMENT '组别',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态[0:禁用;1启用]',
  `create_time` int(10) UNSIGNED DEFAULT '0' COMMENT '创建时间',
  `update_time` int(10) UNSIGNED DEFAULT '0' COMMENT '修改时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='oauth2_授权客户端';

--
-- 转存表中的数据 `fun_oauth2_client`
--

INSERT INTO `fun_oauth2_client` (`id`, `merchant_id`, `title`, `appid`, `appsecret`, `redirect_uri`, `remark`, `group`, `status`, `create_time`, `update_time`) VALUES
(1, 1, 'FunAdmin', 'FunAdmin', '123456', '', '', '', 1, 0, 0);

-- --------------------------------------------------------

--
-- 表的结构 `fun_region`
--

CREATE TABLE `fun_region` (
  `id` int(11) UNSIGNED NOT NULL COMMENT '表id',
  `name` varchar(32) DEFAULT NULL COMMENT '地区名称',
  `level` tinyint(4) DEFAULT '0' COMMENT '地区等级 分省市县区',
  `pid` int(10) DEFAULT NULL COMMENT '父id'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 转存表中的数据 `fun_region`
--

INSERT INTO `fun_region` (`id`, `name`, `level`, `pid`) VALUES
(1, '北京市', 1, 0),
(2, '市辖区', 2, 1),
(3, '东城区', 3, 2),
(4, '东华门街道', 4, 3),
(5, '景山街道', 4, 3),
(6, '交道口街道', 4, 3),
(7, '安定门街道', 4, 3),
(8, '北新桥街道', 4, 3),
(9, '东四街道', 4, 3),
(10, '朝阳门街道', 4, 3),
(11, '建国门街道', 4, 3),
(12, '东直门街道', 4, 3),
(13, '和平里街道', 4, 3),
(14, '西城区', 3, 2),
(15, '西长安街街道', 4, 14),
(16, '新街口街道', 4, 14),
(17, '月坛街道', 4, 14),
(18, '展览路街道', 4, 14),
(19, '德胜街道', 4, 14),
(20, '金融街街道', 4, 14),
(21, '什刹海街道', 4, 14),
(22, '崇文区', 3, 2),
(23, '前门街道', 4, 22),
(24, '崇文门外街道', 4, 22),
(25, '东花市街道', 4, 22),
(26, '龙潭街道', 4, 22),
(27, '体育馆路街道', 4, 22),
(28, '天坛街道', 4, 22),
(29, '永定门外街道', 4, 22),
(30, '宣武区', 3, 2),
(31, '大栅栏街道', 4, 30),
(32, '天桥街道', 4, 30),
(33, '椿树街道', 4, 30),
(34, '陶然亭街道', 4, 30),
(35, '广安门内街道', 4, 30),
(36, '牛街街道', 4, 30),
(37, '白纸坊街道', 4, 30),
(38, '广安门外街道', 4, 30),
(39, '朝阳区', 3, 2),
(40, '建外街道', 4, 39),
(41, '朝外街道', 4, 39),
(42, '呼家楼街道', 4, 39),
(43, '三里屯街道', 4, 39),
(44, '左家庄街道', 4, 39),
(45, '香河园街道', 4, 39),
(46, '和平街街道', 4, 39),
(47, '安贞街道', 4, 39),
(48, '亚运村街道', 4, 39),
(49, '小关街道', 4, 39),
(50, '酒仙桥街道', 4, 39),
(51, '麦子店街道', 4, 39),
(52, '团结湖街道', 4, 39),
(53, '六里屯街道', 4, 39),
(54, '八里庄街道', 4, 39),
(55, '双井街道', 4, 39),
(56, '劲松街道', 4, 39),
(57, '潘家园街道', 4, 39),
(58, '垡头街道', 4, 39),
(59, '南磨房地区', 4, 39),
(60, '高碑店地区', 4, 39),
(61, '将台地区', 4, 39),
(62, '太阳宫地区', 4, 39),
(63, '大屯街道', 4, 39),
(64, '望京街道', 4, 39),
(65, '小红门地区', 4, 39),
(66, '十八里店地区', 4, 39),
(67, '平房地区', 4, 39),
(68, '东风地区', 4, 39),
(69, '奥运村地区', 4, 39),
(70, '来广营地区', 4, 39),
(71, '常营回族地区', 4, 39),
(72, '三间房地区', 4, 39),
(73, '管庄地区', 4, 39),
(74, '金盏地区', 4, 39);

--
-- 转储表的索引
--

--
-- 表的索引 `fun_addon`
--
ALTER TABLE `fun_addon`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `status` (`status`);

--
-- 表的索引 `fun_addons_cms_adv`
--
ALTER TABLE `fun_addons_cms_adv`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enabled` (`status`) USING BTREE,
  ADD KEY `position_id` (`pid`) USING BTREE;

--
-- 表的索引 `fun_addons_cms_adv_position`
--
ALTER TABLE `fun_addons_cms_adv_position`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_id` (`id`);

--
-- 表的索引 `fun_addons_cms_article`
--
ALTER TABLE `fun_addons_cms_article`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`id`,`status`,`sort`),
  ADD KEY `cateid` (`id`,`cateid`,`status`),
  ADD KEY `sort` (`id`,`cateid`,`status`,`sort`);

--
-- 表的索引 `fun_addons_cms_category`
--
ALTER TABLE `fun_addons_cms_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listorder` (`sort`),
  ADD KEY `pid` (`pid`);

--
-- 表的索引 `fun_addons_cms_debris`
--
ALTER TABLE `fun_addons_cms_debris`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_debris_type`
--
ALTER TABLE `fun_addons_cms_debris_type`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_diyform`
--
ALTER TABLE `fun_addons_cms_diyform`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_field`
--
ALTER TABLE `fun_addons_cms_field`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_link`
--
ALTER TABLE `fun_addons_cms_link`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_message`
--
ALTER TABLE `fun_addons_cms_message`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- 表的索引 `fun_addons_cms_module`
--
ALTER TABLE `fun_addons_cms_module`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_addons_cms_picture`
--
ALTER TABLE `fun_addons_cms_picture`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`id`,`status`,`sort`),
  ADD KEY `cateid` (`id`,`cateid`,`status`),
  ADD KEY `sort` (`id`,`cateid`,`status`,`sort`);

--
-- 表的索引 `fun_addons_cms_product`
--
ALTER TABLE `fun_addons_cms_product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`id`,`status`,`sort`),
  ADD KEY `cateid` (`id`,`cateid`,`status`),
  ADD KEY `sort` (`id`,`cateid`,`status`,`sort`);

--
-- 表的索引 `fun_addons_cms_tags`
--
ALTER TABLE `fun_addons_cms_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- 表的索引 `fun_addon_spshop_adv`
--
ALTER TABLE `fun_addon_spshop_adv`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enabled` (`status`) USING BTREE,
  ADD KEY `position_id` (`pid`) USING BTREE;

--
-- 表的索引 `fun_addon_spshop_adv_position`
--
ALTER TABLE `fun_addon_spshop_adv_position`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `position_id` (`id`);

--
-- 表的索引 `fun_addon_spshop_goods`
--
ALTER TABLE `fun_addon_spshop_goods`
  ADD UNIQUE KEY `idb` (`id`) USING BTREE,
  ADD KEY `cate_id` (`cate_id`),
  ADD KEY `is_hot` (`is_hot`),
  ADD KEY `is_new` (`is_hot`);

--
-- 表的索引 `fun_addon_spshop_goods_category`
--
ALTER TABLE `fun_addon_spshop_goods_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`pid`);

--
-- 表的索引 `fun_admin`
--
ALTER TABLE `fun_admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_username` (`username`);

--
-- 表的索引 `fun_admin_log`
--
ALTER TABLE `fun_admin_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`) USING BTREE,
  ADD KEY `admin_id` (`admin_id`);

--
-- 表的索引 `fun_attach`
--
ALTER TABLE `fun_attach`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_auth_group`
--
ALTER TABLE `fun_auth_group`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`) USING BTREE,
  ADD UNIQUE KEY `title` (`title`);

--
-- 表的索引 `fun_auth_rule`
--
ALTER TABLE `fun_auth_rule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `href` (`href`) USING BTREE;

--
-- 表的索引 `fun_config`
--
ALTER TABLE `fun_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- 表的索引 `fun_config_group`
--
ALTER TABLE `fun_config_group`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_field_type`
--
ALTER TABLE `fun_field_type`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_field_verfiy`
--
ALTER TABLE `fun_field_verfiy`
  ADD UNIQUE KEY `verfiy` (`verfiy`);

--
-- 表的索引 `fun_languages`
--
ALTER TABLE `fun_languages`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_member`
--
ALTER TABLE `fun_member`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `underling_number` (`underling_number`),
  ADD KEY `mobile` (`mobile_validated`);

--
-- 表的索引 `fun_member_account`
--
ALTER TABLE `fun_member_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- 表的索引 `fun_member_address`
--
ALTER TABLE `fun_member_address`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- 表的索引 `fun_member_group`
--
ALTER TABLE `fun_member_group`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `fun_member_level`
--
ALTER TABLE `fun_member_level`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- 表的索引 `fun_member_third`
--
ALTER TABLE `fun_member_third`
  ADD PRIMARY KEY (`id`,`member_id`),
  ADD UNIQUE KEY `platform` (`platform`,`openid`) USING BTREE,
  ADD KEY `member_id` (`member_id`,`platform`) USING BTREE,
  ADD KEY `id` (`id`);

--
-- 表的索引 `fun_oauth2_client`
--
ALTER TABLE `fun_oauth2_client`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`appid`);

--
-- 表的索引 `fun_region`
--
ALTER TABLE `fun_region`
  ADD PRIMARY KEY (`id`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `fun_addon`
--
ALTER TABLE `fun_addon`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键', AUTO_INCREMENT=22;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_adv`
--
ALTER TABLE `fun_addons_cms_adv`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '广告id', AUTO_INCREMENT=9;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_adv_position`
--
ALTER TABLE `fun_addons_cms_adv_position`
  MODIFY `id` int(3) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_article`
--
ALTER TABLE `fun_addons_cms_article`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID', AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_category`
--
ALTER TABLE `fun_addons_cms_category`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_debris`
--
ALTER TABLE `fun_addons_cms_debris`
  MODIFY `id` int(6) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_debris_type`
--
ALTER TABLE `fun_addons_cms_debris_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_diyform`
--
ALTER TABLE `fun_addons_cms_diyform`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_field`
--
ALTER TABLE `fun_addons_cms_field`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_link`
--
ALTER TABLE `fun_addons_cms_link`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_message`
--
ALTER TABLE `fun_addons_cms_message`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_module`
--
ALTER TABLE `fun_addons_cms_module`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_picture`
--
ALTER TABLE `fun_addons_cms_picture`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID', AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_product`
--
ALTER TABLE `fun_addons_cms_product`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID', AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `fun_addons_cms_tags`
--
ALTER TABLE `fun_addons_cms_tags`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用表AUTO_INCREMENT `fun_addon_spshop_adv`
--
ALTER TABLE `fun_addon_spshop_adv`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '广告id', AUTO_INCREMENT=95;

--
-- 使用表AUTO_INCREMENT `fun_addon_spshop_adv_position`
--
ALTER TABLE `fun_addon_spshop_adv_position`
  MODIFY `id` int(3) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `fun_addon_spshop_goods`
--
ALTER TABLE `fun_addon_spshop_goods`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品id';

--
-- 使用表AUTO_INCREMENT `fun_addon_spshop_goods_category`
--
ALTER TABLE `fun_addon_spshop_goods_category`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '商品分类id', AUTO_INCREMENT=55;

--
-- 使用表AUTO_INCREMENT `fun_admin`
--
ALTER TABLE `fun_admin`
  MODIFY `id` tinyint(4) NOT NULL AUTO_INCREMENT COMMENT '管理员ID', AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_admin_log`
--
ALTER TABLE `fun_admin_log`
  MODIFY `id` bigint(16) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=160;

--
-- 使用表AUTO_INCREMENT `fun_attach`
--
ALTER TABLE `fun_attach`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- 使用表AUTO_INCREMENT `fun_auth_group`
--
ALTER TABLE `fun_auth_group`
  MODIFY `id` smallint(8) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分组id', AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_auth_rule`
--
ALTER TABLE `fun_auth_rule`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=420;

--
-- 使用表AUTO_INCREMENT `fun_config`
--
ALTER TABLE `fun_config`
  MODIFY `id` smallint(5) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- 使用表AUTO_INCREMENT `fun_config_group`
--
ALTER TABLE `fun_config_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- 使用表AUTO_INCREMENT `fun_field_type`
--
ALTER TABLE `fun_field_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- 使用表AUTO_INCREMENT `fun_languages`
--
ALTER TABLE `fun_languages`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_member`
--
ALTER TABLE `fun_member`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=5;

--
-- 使用表AUTO_INCREMENT `fun_member_account`
--
ALTER TABLE `fun_member_account`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `fun_member_address`
--
ALTER TABLE `fun_member_address`
  MODIFY `id` int(10) NOT NULL AUTO_INCREMENT COMMENT '主键';

--
-- 使用表AUTO_INCREMENT `fun_member_group`
--
ALTER TABLE `fun_member_group`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `fun_member_level`
--
ALTER TABLE `fun_member_level`
  MODIFY `id` smallint(4) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=8;

--
-- 使用表AUTO_INCREMENT `fun_member_third`
--
ALTER TABLE `fun_member_third`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID';

--
-- 使用表AUTO_INCREMENT `fun_oauth2_client`
--
ALTER TABLE `fun_oauth2_client`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `fun_region`
--
ALTER TABLE `fun_region`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id', AUTO_INCREMENT=75;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
