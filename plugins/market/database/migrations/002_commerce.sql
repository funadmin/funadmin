ALTER TABLE `market_plugin`
  ADD COLUMN `price_perpetual` int unsigned NOT NULL DEFAULT 0 COMMENT '买断价（分），0 表示不提供买断' AFTER `license_type`,
  ADD COLUMN `price_yearly` int unsigned NOT NULL DEFAULT 0 COMMENT '年费（分），0 表示不提供按年' AFTER `price_perpetual`,
  ADD COLUMN `cover` varchar(500) NOT NULL DEFAULT '' AFTER `price_yearly`,
  ADD COLUMN `homepage` varchar(500) NOT NULL DEFAULT '' AFTER `cover`;

ALTER TABLE `market_grant`
  ADD COLUMN `source` varchar(16) NOT NULL DEFAULT 'admin' COMMENT 'admin 后台授予；order 订单购买' AFTER `status`,
  ADD COLUMN `plan` varchar(16) NOT NULL DEFAULT '' COMMENT 'perpetual 买断；yearly 按年' AFTER `source`,
  ADD COLUMN `order_id` bigint unsigned NULL AFTER `plan`;

CREATE TABLE IF NOT EXISTS `market_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL,
  `member_id` int unsigned NOT NULL,
  `plugin_id` int unsigned NOT NULL,
  `plan` varchar(16) NOT NULL COMMENT 'perpetual 买断；yearly 按年',
  `amount` int unsigned NOT NULL COMMENT '应付金额（分）',
  `subject` varchar(200) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending' COMMENT 'pending 待支付；paid 已支付；closed 已关闭',
  `channel` varchar(16) NOT NULL DEFAULT '' COMMENT 'alipay / wechat / mock / manual',
  `trade_no` varchar(64) NULL COMMENT '支付渠道交易号',
  `paid_amount` int unsigned NOT NULL DEFAULT 0,
  `paid_at` datetime NULL,
  `expire_at` datetime NOT NULL COMMENT '待支付订单过期时间',
  `grant_id` int unsigned NULL,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `client_ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_order_no` (`order_no`),
  UNIQUE KEY `uk_market_order_trade` (`channel`, `trade_no`),
  KEY `idx_market_order_member` (`member_id`, `status`),
  KEY `idx_market_order_plugin` (`plugin_id`, `status`),
  KEY `idx_market_order_paid` (`status`, `paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场订单';

CREATE TABLE IF NOT EXISTS `market_payment_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL DEFAULT '',
  `channel` varchar(16) NOT NULL,
  `event` varchar(16) NOT NULL COMMENT 'notify 异步通知；query 主动查询；manual 人工确认',
  `verified` tinyint NOT NULL DEFAULT 0,
  `result` varchar(255) NOT NULL DEFAULT '',
  `payload` mediumtext NULL,
  `created_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_market_payment_log_order` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场支付回调与查询日志';

CREATE TABLE IF NOT EXISTS `market_site` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` char(36) NOT NULL,
  `member_id` int unsigned NOT NULL DEFAULT 0 COMMENT '最近一次上报时登录的市场账号',
  `platform_version` varchar(32) NOT NULL DEFAULT '',
  `php_version` varchar(32) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `first_seen_at` datetime NULL,
  `last_seen_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场客户端站点';

CREATE TABLE IF NOT EXISTS `market_site_plugin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` char(36) NOT NULL,
  `plugin_id` int unsigned NOT NULL,
  `version` varchar(64) NOT NULL DEFAULT '',
  `state` varchar(16) NOT NULL COMMENT 'enabled 已启用；disabled 已安装未启用；uninstalled 已卸载',
  `installed_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_site_plugin` (`site_id`, `plugin_id`),
  KEY `idx_market_site_plugin_state` (`plugin_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='各站点插件当前状态';

CREATE TABLE IF NOT EXISTS `market_install_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `site_id` char(36) NOT NULL,
  `plugin_id` int unsigned NOT NULL,
  `event` varchar(16) NOT NULL COMMENT 'install / update / uninstall / enable / disable',
  `version` varchar(64) NOT NULL DEFAULT '',
  `from_version` varchar(64) NOT NULL DEFAULT '',
  `member_id` int unsigned NOT NULL DEFAULT 0,
  `occurred_at` datetime NOT NULL,
  `created_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_market_install_event_plugin` (`plugin_id`, `event`, `occurred_at`),
  KEY `idx_market_install_event_time` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件安装统计事件';

CREATE INDEX `idx_market_download_time` ON `market_download` (`created_at`);
