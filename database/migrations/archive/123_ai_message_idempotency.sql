-- 仅前向迁移：会话所属管理员由会话行锁校验，键按会话隔离。
ALTER TABLE `fun_ai_message`
    ADD COLUMN `idempotency_key` varchar(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ADD COLUMN `payload_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ADD UNIQUE KEY `uk_ai_message_client_key` (`conversation_id`, `idempotency_key`);
