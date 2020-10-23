-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- 主机： localhost:3306
-- 生成日期： 2020-10-23 16:58:56
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
-- 数据库： `www_fun_com`
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
(1, '数据库管理', 'database', '', '', '数据库插件-FunAdmin数据库管理插件', 'yuege', '0.1', '0.1', '', 0, 1, 1599878742, 1599878742);

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
(1, 'admin', '$2y$12$jJNSWOS.8he.z3s17YCRtesZ1v6F6Ck3zUGBhniRDr2LNHfUUwH5.', '1,3', '994927909@qq.com', '', '18397423845', '127.0.0.1', '3bf4b7158724d2bd326630ff0651f777cec4a604', '0', 1, '\\storage\\site/20200723\\045256245ee708a40a2ac1b567bc481a.png', 1482132862, 1603423906),
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
(29, 1, 0, 'admin-ajax.png', '7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', '\\storage\\uploads/20201015\\7f6bd5320eaec793f6c3ae855c7a2be0.png', 'png', 4, '115', '103', 'cb959c6185839156a4ebadd507b96f67', 'image/png', '0', NULL, 1602723370, 1602723370, 1, 50);

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
(64, 'backend', '_self', 'addon.addon', 'Addon', 1, 1, 1, 1, 'layui-icon layui-icon-app', '', 0, 501, 1580880615, 1599892096),
(65, 'backend', '_self', 'addon/addon/index', 'List', 0, 1, 1, 0, 'layui-icon-face-smile-fine', '', 64, 50, 1599889019, 1599889019),
(66, 'backend', '_self', 'sys.languages', 'languages', 1, 1, 1, 1, 'layui-icon layui-icon-rate', '', 1, 50, 1603427312, 1603428082),
(67, 'backend', '_self', 'sys.languages/index', 'List', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(68, 'backend', '_self', 'sys.languages/delete', 'delete', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(69, 'backend', '_self', 'sys.languages/modify', 'Modify', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(70, 'backend', '_self', 'sys.languages/add', 'Add', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524),
(71, 'backend', '_self', 'sys.languages/edit', 'Edit', 0, 1, 1, 0, 'layui-icon-rate', '', 66, 50, 1603427492, 1603427524);

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
(11, 'app_debug', '', '0,1', '0', '测试模式', '', 'radio', 'site', 1, 0, 1602315188),
(18, 'email_addr', '', '', '994927909@qq.com', '邮箱发件人地址', '0', 'text', 'email', 1, 0, 0),
(19, 'email_id', '', '', '994927909@qq.com', '身份验证用户名', '0', 'text', 'email', 1, 0, 0),
(20, 'email_pass', '', '', '11211', '用户名密码', '0', 'text', 'email', 1, 0, 0),
(21, 'email_secure', '', '', 'smtp', '邮箱发送协议', '0', 'text', 'email', 1, 0, 0),
(22, 'upload_file_type', '', '', 'mp4', '图片上传保存方式', '0', 'text', 'upload', 1, 0, 1602723793),
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '主键', AUTO_INCREMENT=2;

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
  MODIFY `id` bigint(16) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '表id';

--
-- 使用表AUTO_INCREMENT `fun_attach`
--
ALTER TABLE `fun_attach`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- 使用表AUTO_INCREMENT `fun_auth_group`
--
ALTER TABLE `fun_auth_group`
  MODIFY `id` smallint(8) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分组id', AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `fun_auth_rule`
--
ALTER TABLE `fun_auth_rule`
  MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

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
