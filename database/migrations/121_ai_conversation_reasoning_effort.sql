-- 会话推理覆盖：NULL 继承档案；只影响后续任务快照。
ALTER TABLE `fun_ai_conversation`
    ADD COLUMN `reasoning_effort` varchar(10) NULL DEFAULT NULL;
