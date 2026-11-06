import { mergeAttributes, Node, type JSONContent } from '@tiptap/core';

export const CALLOUT_TONES = ['info', 'tip', 'warning', 'danger'] as const;
export type CalloutTone = (typeof CALLOUT_TONES)[number];

/** Markdown 中以 GitHub 风格提示块表示：`> [!WARNING]`。 */
const TONE_TO_ALERT: Record<CalloutTone, string> = { info: 'NOTE', tip: 'TIP', warning: 'WARNING', danger: 'CAUTION' };
const ALERT_TO_TONE: Record<string, CalloutTone> = { NOTE: 'info', INFO: 'info', TIP: 'tip', IMPORTANT: 'tip', WARNING: 'warning', CAUTION: 'danger', DANGER: 'danger' };
const ALERT_PATTERN = /^ {0,3}>[ \t]?\[!(NOTE|INFO|TIP|IMPORTANT|WARNING|CAUTION|DANGER)\][ \t]*\n((?: {0,3}>.*(?:\n|$))*)/i;

export const Callout = Node.create({
  name: 'callout',
  group: 'block',
  content: 'block+',
  defining: true,

  addAttributes() {
    return {
      tone: {
        default: 'info',
        parseHTML: (el) => {
          const tone = el.getAttribute('data-tone');
          return CALLOUT_TONES.includes(tone as CalloutTone) ? tone : 'info';
        },
        renderHTML: (attrs) => ({ 'data-tone': attrs.tone })
      }
    };
  },

  parseHTML() {
    return [{ tag: 'div[data-callout]' }];
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-callout': '', class: 'doc-callout' }), 0];
  },

  markdownTokenName: 'callout',
  markdownTokenizer: {
    name: 'callout',
    level: 'block',
    start: (src: string) => {
      const index = src.search(/^ {0,3}>[ \t]?\[!/im);
      return index < 0 ? -1 : index;
    },
    tokenize(src, _tokens, lexer) {
      const match = ALERT_PATTERN.exec(src);
      if (!match) return undefined;
      const body = match[2].split('\n').map((line) => line.replace(/^ {0,3}>[ \t]?/, '')).join('\n');
      return { type: 'callout', raw: match[0], tone: ALERT_TO_TONE[match[1].toUpperCase()] || 'info', tokens: lexer.blockTokens(body) };
    }
  },
  parseMarkdown(token, helpers) {
    const children = (helpers.parseBlockChildren ?? helpers.parseChildren)(token.tokens || []);
    return helpers.createNode('callout', { tone: token.tone || 'info' }, children.length ? children : [helpers.createNode('paragraph')]);
  },
  renderMarkdown(node, h) {
    const tone = (node.attrs?.tone as CalloutTone) || 'info';
    const body = h.renderChildren(node.content || [], '\n\n');
    const lines = body.split('\n').map((line) => (line.trim() === '' ? '>' : `> ${line}`));
    return [`> [!${TONE_TO_ALERT[tone] || 'NOTE'}]`, ...lines].join('\n');
  }
});

/** Mermaid 只保存源码；图形在预览时由前端渲染，不保存 SVG。 */
export const Mermaid = Node.create({
  name: 'mermaid',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      source: {
        default: '',
        // 源码只以 <code> 文本携带：放进属性时含 `-->` 的源码会被 HTML 净化器整体丢弃。
        parseHTML: (el) => el.querySelector('code')?.textContent ?? el.textContent ?? '',
        renderHTML: () => ({})
      }
    };
  },

  parseHTML() {
    return [{ tag: 'div[data-mermaid]' }];
  },

  renderHTML({ node, HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-mermaid': '', class: 'doc-mermaid' }), ['pre', {}, ['code', {}, node.attrs.source]]];
  },

  markdownTokenName: 'mermaid',
  markdownTokenizer: {
    name: 'mermaid',
    level: 'block',
    start: (src: string) => {
      const index = src.search(/^ {0,3}(`{3,}|~{3,})[ \t]*mermaid[ \t]*$/im);
      return index < 0 ? -1 : index;
    },
    tokenize(src) {
      const match = /^ {0,3}(`{3,}|~{3,})[ \t]*mermaid[ \t]*\n([\s\S]*?)\n {0,3}\1[ \t]*(?:\n|$)/i.exec(src);
      if (!match) return undefined;
      return { type: 'mermaid', raw: match[0], text: match[2] };
    }
  },
  parseMarkdown(token, helpers) {
    return helpers.createNode('mermaid', { source: String(token.text || '') });
  },
  renderMarkdown(node: JSONContent) {
    return ['```mermaid', String(node.attrs?.source || ''), '```'].join('\n');
  }
});
