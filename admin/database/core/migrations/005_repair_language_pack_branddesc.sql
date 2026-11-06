-- 005：修复 fun_language_line 中 en-us/login.brandDesc 间隔号的双重编码乱码。
-- 乱码形态：'·'(U+00B7, UTF-8 C2B7) 被按 cp1252 解读后再次编码为 C382C2B7（显示为 Â·）。
-- 守卫式替换：仅修复仍含乱码 token 的行，幂等可重跑。
UPDATE `fun_language_line`
SET `value` = REPLACE(`value`, UNHEX('C382C2B7'), UNHEX('C2B7')), `updated_at` = NOW()
WHERE `locale` = 'en-us'
  AND `key` = 'login.brandDesc'
  AND HEX(`value`) LIKE '%C382C2B7%';
