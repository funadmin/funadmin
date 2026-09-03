import { describe, it, expect } from 'vitest';
import { toCsv, parseCsv, type CsvColumn } from '@/utils/csv';

interface Row {
  id: number;
  name: string;
  status: number;
}

const cols: CsvColumn<Row>[] = [
  { key: 'id', label: 'ID' },
  { key: 'name', label: '名称' },
  {
    key: 'status',
    label: '状态',
    formatter: (r) => (r.status === 1 ? '启用' : '禁用'),
    parser: (s) => (s === '启用' ? 1 : 0)
  }
];

describe('utils/csv', () => {
  it('toCsv 基本导出 + 表头', () => {
    const csv = toCsv<Row>([{ id: 1, name: 'admin', status: 1 }], cols);
    expect(csv).toBe('ID,名称,状态\r\n1,admin,启用');
  });

  it('toCsv 转义包含逗号/引号/换行的单元格', () => {
    const csv = toCsv<Row>([{ id: 2, name: 'a,b"c\nd', status: 0 }], cols);
    expect(csv).toContain('"a,b""c\nd"');
  });

  it('parseCsv 还原对象，BOM 自动忽略，parser 生效', () => {
    const text = '\uFEFFID,名称,状态\r\n1,admin,启用\r\n2,"a,b",禁用';
    const rows = parseCsv<Row>(text, cols);
    expect(rows).toHaveLength(2);
    expect(rows[0]).toEqual({ id: '1', name: 'admin', status: 1 });
    expect(rows[1].name).toBe('a,b');
    expect(rows[1].status).toBe(0);
  });

  it('parseCsv 空文本返回空数组', () => {
    expect(parseCsv<Row>('', cols)).toEqual([]);
  });

  it('parseCsv 跳过完全空行', () => {
    const text = 'ID,名称,状态\r\n1,admin,启用\r\n,,';
    const rows = parseCsv<Row>(text, cols);
    expect(rows).toHaveLength(1);
  });
});
