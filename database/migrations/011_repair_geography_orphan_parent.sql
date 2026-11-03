-- 修正基线地区孤儿：不存在的 839000 父级挂到已有台湾省 830000。
-- 只处理缺父级且父省存在的明确记录，不修改历史基线或删除地区。
UPDATE `fun_provinces` AS child
LEFT JOIN `fun_provinces` AS missing_parent ON missing_parent.`id` = child.`pid`
JOIN `fun_provinces` AS province ON province.`id` = 830000 AND province.`pid` = 0 AND province.`name` = '台湾省'
SET child.`pid` = province.`id`
WHERE child.`pid` = 839000 AND child.`id` BETWEEN 839001 AND 839013
  AND missing_parent.`id` IS NULL;

UPDATE `fun_region` AS child
LEFT JOIN `fun_region` AS missing_parent ON missing_parent.`id` = child.`pid`
JOIN `fun_region` AS province ON province.`id` = 830000 AND province.`pid` = 0 AND province.`name` = '台湾省'
SET child.`pid` = province.`id`
WHERE child.`pid` = 839000 AND child.`id` BETWEEN 839001 AND 839013
  AND missing_parent.`id` IS NULL;
