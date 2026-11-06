-- 列表动作持久幂等占位：仅新增，历史迁移与业务表不变。
-- scope 为完整幂等作用域摘要，主键保证跨连接只能有一个占位者。
-- 专用自动提交连接写入，不参与业务事务；记录不设置 TTL、不自动释放未知或失败状态。
-- fun_ 由核心迁移机制替换；自定义前缀时同步配置完整物理表名。
CREATE TABLE IF NOT EXISTS `fun_form_list_action_execution` (
  `scope` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `digest` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `state` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `result` longtext DEFAULT NULL,
  PRIMARY KEY (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
