-- 120 会话选择档案；删除档案不清空引用，以便后续任务 fail closed。
ALTER TABLE `fun_ai_conversation`
    ADD COLUMN `profile_id` bigint unsigned NULL,
    ADD KEY `idx_ai_conversation_profile` (`profile_id`);
