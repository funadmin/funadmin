-- 004：修复 fun_language_line 中 en-us/layout.langZh 的双重编码乱码。
-- 乱码形态：UTF-8 字节被按 cp1252 解读后再次 UTF-8 编码（C3A7C2AE...）。
-- 守卫式更新：仅当值仍为乱码形态时修复，幂等可重跑。
SET @mojibake_hex = 'C3A7C2AEE282ACC3A4C2BDE2809CC3A4C2B8C2ADC3A6E28093E280A1';
SET @target_hex = 'E7AE80E4BD93E4B8ADE69687';

UPDATE `fun_language_line`
SET `value` = UNHEX(@target_hex), `updated_at` = NOW()
WHERE `locale` = 'en-us'
  AND `key` = 'layout.langZh'
  AND HEX(`value`) = @mojibake_hex;
