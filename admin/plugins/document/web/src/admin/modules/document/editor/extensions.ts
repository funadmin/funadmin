import type { AnyExtension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { TableKit } from '@tiptap/extension-table';
import { TaskItem, TaskList } from '@tiptap/extension-list';
import { Callout, Mermaid } from './nodes';
import { sanitizeDocumentUrl } from './url';

/**
 * 编辑器、预览与 Markdown 转换共用的同一份 schema。
 * 与后端 ContentValidator 的节点白名单保持一致。
 */
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
      trailingNode: false
    }),
    Image.configure({ inline: false, allowBase64: false }),
    TableKit.configure({ table: { resizable: false } }),
    TaskList,
    TaskItem.configure({ nested: true }),
    Callout,
    Mermaid
  ];
}
