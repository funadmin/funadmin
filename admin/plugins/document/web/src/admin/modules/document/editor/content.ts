import type { JSONContent } from '@tiptap/core';
import { generateHTML } from '@tiptap/core';
import { MarkdownManager } from '@tiptap/markdown';
import DOMPurify from 'dompurify';
import { documentExtensions } from './extensions';
import { sanitizeDocumentUrl } from './url';

export { sanitizeDocumentUrl };

/**
 * 原始 HTML 不作为正文：Markdown 里的 HTML 片段一律当作字面文本保留，
 * 不交给 HTML 解析。不能对整段 Markdown 源码做 HTML 净化——那会把
 * 代码块里的 `<?php`、`<template>` 和 `>` 引用一起破坏。
 * （parseHTMLToken 在类型声明中是私有的，因此以实例方式覆盖。）
 */
type HtmlToken = { text?: string; raw?: string; block?: boolean };
function htmlAsText(token: HtmlToken): JSONContent | JSONContent[] | null {
  const html = token.text || token.raw || '';
  if (!html.trim()) return null;
  const text: JSONContent = { type: 'text', text: html };
  return token.block ? [{ type: 'paragraph', content: [text] }] : [text];
}

let manager: MarkdownManager | null = null;
function markdown(): MarkdownManager {
  if (!manager) {
    manager = new MarkdownManager({ extensions: documentExtensions() });
    (manager as unknown as { parseHTMLToken: typeof htmlAsText }).parseHTMLToken = htmlAsText;
  }
  return manager;
}

/** 去掉危险链接和图片，保留文字；与后端白名单行为一致。 */
function scrub(node: JSONContent): JSONContent | null {
  if (node.type === 'image' && sanitizeDocumentUrl(node.attrs?.src) === null) return null;
  const clean: JSONContent = { ...node };
  if (node.marks) {
    clean.marks = node.marks.filter((mark) => mark.type !== 'link' || sanitizeDocumentUrl(mark.attrs?.href) !== null);
    if (!clean.marks.length) delete clean.marks;
  }
  if (node.content) clean.content = node.content.map(scrub).filter((child): child is JSONContent => child !== null);
  return clean;
}

/**
 * Markdown → Tiptap JSON。原始 HTML 只作为字面文本（见 htmlAsText），
 * 解析结果再剔除 schema 外的危险链接与图片；后端白名单是最终边界。
 */
export function parseImportedMarkdown(source: string): JSONContent {
  const parsed = markdown().parse(source);
  return scrub(parsed) ?? { type: 'doc', content: [] };
}

export function exportDocumentMarkdown(content: JSONContent): string {
  return markdown().serialize(content);
}

/** 由 JSON 生成 HTML 并再次净化，用于预览与下载导出。 */
export function exportDocumentHtml(content: JSONContent): string {
  const html = generateHTML(scrub(content) ?? { type: 'doc', content: [] }, documentExtensions());
  const clean = DOMPurify.sanitize(html, {
    ALLOWED_URI_REGEXP: /^(?:https?:|mailto:|\/(?!\/)|#)/i,
    // 自定义节点的标记属性；Mermaid 源码以转义后的 <code> 文本携带，不放进属性。
    ADD_ATTR: ['data-tone', 'data-callout', 'data-mermaid', 'data-type', 'data-checked']
  });
  return restoreTaskCheckboxes(clean);
}

/**
 * 净化器会去掉 <input> 的 type/checked。不放宽净化规则，而是净化后
 * 依据可信的 data-checked 重建只读复选框（不接受任何其他属性）。
 */
function restoreTaskCheckboxes(html: string): string {
  if (!html.includes('data-type="taskItem"') || typeof document === 'undefined') return html;
  const host = document.createElement('div');
  host.innerHTML = html;
  host.querySelectorAll<HTMLElement>('li[data-type="taskItem"]').forEach((item) => {
    const label = item.querySelector(':scope > label');
    if (!label) return;
    const box = document.createElement('input');
    box.type = 'checkbox';
    box.disabled = true;
    if (item.getAttribute('data-checked') === 'true') box.setAttribute('checked', '');
    label.replaceChildren(box);
  });
  return host.innerHTML;
}

export function extractPlainText(content: JSONContent): string {
  const parts: string[] = [];
  const walk = (node: JSONContent) => {
    if (node.type === 'text' && node.text) parts.push(node.text);
    if (node.type === 'mermaid' && node.attrs?.source) parts.push(String(node.attrs.source));
    node.content?.forEach(walk);
    if (['paragraph', 'heading', 'codeBlock', 'listItem', 'taskItem', 'tableCell', 'tableHeader'].includes(node.type || '')) parts.push('\n');
  };
  walk(content);
  return parts.join('').replace(/\n{2,}/g, '\n').trim();
}
