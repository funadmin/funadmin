-- 093 Retire the legacy_form Business module origin without changing Form or Form Schema data.
UPDATE `fun_business_module` m
LEFT JOIN `fun_form` f ON f.`id`=m.`form_id`
SET m.`origin`=CASE WHEN f.`source_type`='adopted' THEN 'database' ELSE 'visual' END,
    m.`metadata`=CASE
      WHEN JSON_TYPE(m.`metadata`)='OBJECT' THEN JSON_REMOVE(m.`metadata`,'$.legacyFormId')
      ELSE m.`metadata`
    END,
    m.`updated_at`=NOW()
WHERE m.`origin`='legacy_form';

ALTER TABLE `fun_business_module`
MODIFY COLUMN `origin` enum('visual','database') NOT NULL DEFAULT 'visual';
