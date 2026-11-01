/** 极简 CSV 解析：支持双引号包裹、引号内转义双引号、CRLF/LF 与逗号分隔。 */
export function parseCsv(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let quoted = false;
  let index = 0;
  const pushCell = () => { row.push(cell); cell = ''; };
  const pushRow = () => { pushCell(); rows.push(row); row = []; };
  while (index < text.length) {
    const char = text[index];
    if (quoted) {
      if (char === '"') {
        if (text[index + 1] === '"') { cell += '"'; index += 2; continue; }
        quoted = false; index += 1; continue;
      }
      cell += char; index += 1; continue;
    }
    if (char === '"') { quoted = true; index += 1; continue; }
    if (char === ',') { pushCell(); index += 1; continue; }
    if (char === '\r') { index += 1; continue; }
    if (char === '\n') { pushRow(); index += 1; continue; }
    cell += char; index += 1;
  }
  if (cell !== '' || row.length) pushRow();
  return rows.filter(cells => cells.some(value => value.trim() !== ''));
}
