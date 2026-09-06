/**
 * 通用 CSV 工具：导出 / 导入 / 下载，零依赖。
 *
 * 设计原则：
 * - 只支持 UTF-8（写入时带 BOM，避免 Excel 中文乱码）
 * - 解析实现 RFC 4180 子集：双引号转义、字段内逗号、换行
 * - 不引入第三方库，体积友好；如需 xlsx 真二进制再升级
 */

export interface CsvColumn<T = any> {
  /** 字段 key（对应数据对象属性 / CSV header） */
  key: keyof T & string;
  /** 表头展示文案（不传则用 key） */
  label?: string;
  /** 导出时把行值映射成可读字符串 */
  formatter?: (row: T) => string | number | boolean | null | undefined;
  /** 导入时把字符串解析回业务值 */
  parser?: (raw: string) => any;
}

/** 把单元格值转成 CSV 安全字符串（必要时加引号 + 双引号转义） */
function escapeCell(value: unknown): string {
  if (value === null || value === undefined) return '';
  const text = String(value);
  const safeText = /^[=+\-@\t\r\n]/.test(text) ? `'${text}` : text;
  if (/[",\r\n]/.test(safeText)) {
    return `"${safeText.replace(/"/g, '""')}"`;
  }
  return safeText;
}

/**
 * 把对象数组转成 CSV 字符串（带表头）。
 * @example
 * toCsv(users, [
 *   { key: 'id', label: 'ID' },
 *   { key: 'username', label: '账号' },
 *   { key: 'status', label: '状态', formatter: r => r.status === 1 ? '启用' : '禁用' }
 * ])
 */
export function toCsv<T extends Record<string, any>>(rows: T[], columns: CsvColumn<T>[]): string {
  const header = columns.map((c) => escapeCell(c.label ?? c.key)).join(',');
  const lines = rows.map((row) =>
    columns.map((c) => escapeCell(c.formatter ? c.formatter(row) : row[c.key])).join(',')
  );
  return [header, ...lines].join('\r\n');
}

/**
 * 解析 CSV 文本为对象数组（首行作为表头）。
 * - 自动忽略 UTF-8 BOM
 * - 支持双引号转义、字段内换行
 */
export function parseCsv<T extends Record<string, any>>(text: string, columns: CsvColumn<T>[]): T[] {
  if (!text) return [];
  const cleaned = text.replace(/^\uFEFF/, '');
  const matrix = parseMatrix(cleaned);
  if (!matrix.length) return [];

  const headers = matrix[0].map((h) => h.trim());
  // 表头 → 列定义映射（同时支持 label 与 key 命中）
  const colByHeader = new Map<string, CsvColumn<T>>();
  columns.forEach((c) => {
    colByHeader.set((c.label ?? c.key).trim(), c);
    colByHeader.set(c.key, c);
  });

  return matrix.slice(1)
    .filter((row) => row.some((cell) => cell !== ''))
    .map((row) => {
      const obj: any = {};
      headers.forEach((header, idx) => {
        const col = colByHeader.get(header);
        if (!col) return;
        const raw = row[idx] ?? '';
        obj[col.key] = col.parser ? col.parser(raw) : raw;
      });
      return obj as T;
    });
}

/** 状态机解析 CSV 文本到二维字符串数组 */
function parseMatrix(text: string): string[][] {
  const rows: string[][] = [];
  let row: string[] = [];
  let cell = '';
  let inQuotes = false;

  for (let i = 0; i < text.length; i++) {
    const ch = text[i];
    if (inQuotes) {
      if (ch === '"') {
        if (text[i + 1] === '"') {
          cell += '"';
          i++;
        } else {
          inQuotes = false;
        }
      } else {
        cell += ch;
      }
      continue;
    }

    if (ch === '"') {
      inQuotes = true;
    } else if (ch === ',') {
      row.push(cell);
      cell = '';
    } else if (ch === '\r') {
      // 跳过 \r，等下一个 \n 或单独一个 \r 都视为换行
      if (text[i + 1] === '\n') i++;
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else if (ch === '\n') {
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else {
      cell += ch;
    }
  }
  // 收尾
  if (cell !== '' || row.length > 0) {
    row.push(cell);
    rows.push(row);
  }
  return rows;
}

/** 触发浏览器下载 CSV 文件（自动追加 UTF-8 BOM 兼容 Excel） */
export function downloadCsv(filename: string, content: string): void {
  const bom = '\uFEFF';
  const blob = new Blob([bom + content], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

/** File → 文本（默认 UTF-8） */
export function readFileAsText(file: File, encoding = 'utf-8'): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(reader.error);
    reader.readAsText(file, encoding);
  });
}
