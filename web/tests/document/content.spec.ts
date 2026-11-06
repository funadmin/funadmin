import { describe, expect, it } from 'vitest';
import type { JSONContent } from '@tiptap/core';
import {
  exportDocumentHtml,
  exportDocumentMarkdown,
  extractPlainText,
  parseImportedMarkdown,
  sanitizeDocumentUrl
} from '../../../admin/plugins/document/web/src/admin/modules/document/editor/content';

const find = (doc: JSONContent, type: string): JSONContent | undefined => {
  if (doc.type === type) return doc;
  for (const child of doc.content || []) {
    const hit = find(child, type);
    if (hit) return hit;
  }
  return undefined;
};

describe('sanitizeDocumentUrl', () => {
  it('只允许 http(s)、mailto、站内路径和锚点', () => {
    for (const ok of ['https://a.com', 'http://a.com/x', 'mailto:a@b.com', '/docs/x', '#intro']) {
      expect(sanitizeDocumentUrl(ok)).toBe(ok);
    }
    for (const bad of ['javascript:alert(1)', 'data:text/html,x', '//evil.com', ' https://a.com', 'JaVaScRiPt:x']) {
      expect(sanitizeDocumentUrl(bad)).toBeNull();
    }
  });
});

describe('parseImportedMarkdown', () => {
  it('把技术文档 Markdown 解析为结构化节点', () => {
    const doc = parseImportedMarkdown([
      '# 安装',
      '',
      '```php',
      'echo 1;',
      '```',
      '',
      '- [x] 已完成',
      '- [ ] 待办',
      '',
      '| 名称 | 说明 |',
      '| --- | --- |',
      '| a | b |'
    ].join('\n'));
    expect(doc.type).toBe('doc');
    expect(find(doc, 'heading')?.attrs?.level).toBe(1);
    expect(find(doc, 'codeBlock')?.attrs?.language).toBe('php');
    expect(find(doc, 'taskItem')?.attrs?.checked).toBe(true);
    expect(find(doc, 'table')).toBeTruthy();
  });

  it('Mermaid 代码块导入为只含源码的 mermaid 节点', () => {
    const doc = parseImportedMarkdown('```mermaid\ngraph TD; A-->B\n```');
    expect(find(doc, 'mermaid')?.attrs).toEqual({ source: 'graph TD; A-->B' });
  });

  it('含 < > & 的技术文档原样导入：代码块、引用和提示块都不被破坏', () => {
    const source = [
      '> 普通引用',
      '',
      '> [!WARNING]',
      '> 先备份',
      '',
      '```php',
      '<?php',
      'if ($a && $b) { echo "<b>x</b>"; }',
      '```',
      '',
      '```vue',
      '<template><div /></template>',
      '```',
      '',
      '```mermaid',
      'graph TD; A-->B',
      '```'
    ].join('\n');
    const doc = parseImportedMarkdown(source);
    const codes = (doc.content || []).filter((n) => n.type === 'codeBlock').map((n) => n.content?.[0]?.text);
    expect(codes).toEqual(['<?php\nif ($a && $b) { echo "<b>x</b>"; }', '<template><div /></template>']);
    expect(find(doc, 'blockquote')).toBeTruthy();
    expect(find(doc, 'callout')?.attrs?.tone).toBe('warning');
    expect(find(doc, 'mermaid')?.attrs?.source).toBe('graph TD; A-->B');
  });

  it('脚本与危险链接不会成为可执行内容', () => {
    const doc = parseImportedMarkdown('<script>alert(1)</script>\n\n<img src=x onerror=alert(1)>\n\n[点我](javascript:alert(1)) [官网](https://funadmin.com)');
    // 原始 HTML 只作为字面文本保留，正文里没有任何元素节点或危险链接。
    const types = new Set<string>();
    const walk = (n: JSONContent) => { types.add(n.type || ''); n.content?.forEach(walk); };
    walk(doc);
    expect([...types].every((t) => ['doc', 'paragraph', 'text'].includes(t))).toBe(true);
    const json = JSON.stringify(doc);
    expect(json).not.toContain('javascript:');
    expect(json).toContain('https://funadmin.com');
    expect(json).toContain('点我');
    // 渲染成 HTML 后，字面文本被转义，不会生成 script/img 元素。
    const host = document.createElement('div');
    host.innerHTML = exportDocumentHtml(doc);
    expect(host.querySelector('script, img, [onerror]')).toBeNull();
    expect(host.textContent).toContain('<script>alert(1)</script>');
  });
});

describe('导出', () => {
  const doc: JSONContent = {
    type: 'doc',
    content: [
      { type: 'heading', attrs: { level: 2 }, content: [{ type: 'text', text: '部署' }] },
      { type: 'codeBlock', attrs: { language: 'bash' }, content: [{ type: 'text', text: 'php start.php start' }] },
      { type: 'mermaid', attrs: { source: 'graph TD; A-->B' } },
      { type: 'callout', attrs: { tone: 'warning' }, content: [{ type: 'paragraph', content: [{ type: 'text', text: '注意备份' }] }] }
    ]
  };

  it('Markdown 往返保留结构（不要求字节一致）', () => {
    const markdown = exportDocumentMarkdown(doc);
    expect(markdown).toContain('## 部署');
    expect(markdown).toContain('```bash');
    expect(markdown).toContain('```mermaid');
    const back = parseImportedMarkdown(markdown);
    expect(find(back, 'codeBlock')?.attrs?.language).toBe('bash');
    expect(find(back, 'mermaid')?.attrs?.source).toBe('graph TD; A-->B');
    expect(find(back, 'callout')?.attrs?.tone).toBe('warning');
  });

  it('HTML 导出经过净化且不含可执行内容', () => {
    const html = exportDocumentHtml({
      type: 'doc',
      content: [{ type: 'paragraph', content: [{ type: 'text', text: 'x', marks: [{ type: 'link', attrs: { href: 'javascript:alert(1)' } }] }] }]
    });
    expect(html).not.toContain('javascript:');
    expect(exportDocumentHtml(doc)).toContain('<h2');
  });

  it('HTML 保留 Mermaid 源码属性和提示块语气，预览才能渲染', () => {
    const html = exportDocumentHtml({
      type: 'doc',
      content: [
        { type: 'mermaid', attrs: { source: 'graph TD; A-->B' } },
        { type: 'callout', attrs: { tone: 'danger' }, content: [{ type: 'paragraph', content: [{ type: 'text', text: '注意' }] }] }
      ]
    });
    const host = document.createElement('div');
    host.innerHTML = html;
    expect(host.querySelector('[data-mermaid] code')?.textContent).toBe('graph TD; A-->B');
    expect(host.querySelector('[data-callout]')?.getAttribute('data-tone')).toBe('danger');
  });

  it('任务列表在预览中是不可编辑的复选框，勾选状态保留', () => {
    const host = document.createElement('div');
    host.innerHTML = exportDocumentHtml({
      type: 'doc',
      content: [{ type: 'taskList', content: [
        { type: 'taskItem', attrs: { checked: true }, content: [{ type: 'paragraph', content: [{ type: 'text', text: '已完成' }] }] },
        { type: 'taskItem', attrs: { checked: false }, content: [{ type: 'paragraph', content: [{ type: 'text', text: '待办' }] }] }
      ] }]
    });
    const boxes = host.querySelectorAll<HTMLInputElement>('input');
    expect(boxes).toHaveLength(2);
    expect([...boxes].map((b) => b.type)).toEqual(['checkbox', 'checkbox']);
    expect([...boxes].map((b) => b.checked)).toEqual([true, false]);
    expect([...boxes].every((b) => b.disabled)).toBe(true);
  });
});

describe('extractPlainText', () => {
  it('提取正文、代码和 Mermaid 源码供搜索', () => {
    const text = extractPlainText({
      type: 'doc',
      content: [
        { type: 'paragraph', content: [{ type: 'text', text: '安装' }] },
        { type: 'mermaid', attrs: { source: 'Webman-->MySQL' } }
      ]
    });
    expect(text).toContain('安装');
    expect(text).toContain('Webman-->MySQL');
  });
});
