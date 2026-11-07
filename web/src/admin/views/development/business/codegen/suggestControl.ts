/** 按字段名与物理类型推荐页面控件（纯前端规则）；无匹配返回 null。 */
export const suggestControl = (field: string, columnType: string): string | null => {
  const name = field.trim().toLowerCase();
  const type = columnType.trim().toLowerCase();
  if (/(^|_)(cover|image|avatar)$/.test(name)) return 'image';
  if (/(^|_)content$/.test(name) && /^(tiny|medium|long)?text\b/.test(type)) return 'richtext';
  if (/(^|_)status$/.test(name)) return 'radio';
  if (/_at$/.test(name) || /^(datetime|timestamp)\b/.test(type)) return 'datetime';
  return null;
};
