-- 会员 API v2 刷新令牌会话：只存 refresh 令牌哈希，支持一次性轮换、重放检测与登出吊销。
CREATE TABLE IF NOT EXISTS `fun_member_api_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `session_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '会话标识，写入 access token 的 sid',
  `member_id` bigint unsigned NOT NULL,
  `refresh_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '当前有效 refresh 令牌的 SHA-256',
  `refresh_expires_at` bigint NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `rotated_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_member_api_session` (`session_id`),
  UNIQUE KEY `uk_member_api_refresh` (`refresh_hash`),
  KEY `idx_member_api_session_member` (`member_id`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员 API 会话';
-- 已轮换的旧 refresh 令牌哈希：再次出现即判定重放并吊销整个会话。
CREATE TABLE IF NOT EXISTS `fun_member_api_session_used` (
  `refresh_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `session_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `used_at` datetime NOT NULL,
  PRIMARY KEY (`refresh_hash`),
  KEY `idx_member_api_used_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员 API 已使用的 refresh 令牌';
