import type { AnyExtension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { common, createLowlight } from 'lowlight';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { Callout, Mermaid } from './nodes';
import { sanitizeDocumentUrl } from './url';

/**
 * 编辑器、预览与 Markdown 转换共用的同一份 schema。
 * 与后端 ContentValidator 的节点白名单保持一致。
 */
/** highlight.js 常用语言集，编辑器与预览共用。 */
export const documentLowlight = createLowlight(common);

export function documentExtensions(): AnyExtension[] {
  return [
    StarterKit.configure({
      heading: { levels: [1, 2, 3, 4, 5, 6] },
      link: {
        openOnClick: false,
        autolink: true,
        isAllowedUri: (url) => sanitizeDocumentUrl(url) !== null,
        shouldAutoLink: (url) => sanitizeDocumentUrl(url) !== null
      },
      trailingNode: false,
      // 用带语法高亮的代码块替换默认代码块；节点名仍是 codeBlock，与后端白名单一致。
      codeBlock: false
    }),
    CodeBlockLowlight.configure({ lowlight: documentLowlight, defaultLanguage: null }),
    Image.configure({ inline: false, allowBase64: false }),
    TableKit.configure({ table: { resizable: false } }),
    TaskList,
    TaskItem.configure({ nested: true }),
    Callout,
    Mermaid
  ];
}
