import { i18n } from '@/locales';

const zhCN = {
  pluginDocument: {
    all: '全部文档',
    scope: { framework: 'FunAdmin 主框架', plugin_dev: '插件开发', official_plugin: '官方插件', third_party: '第三方插件' },
    status: { draft: '草稿', published: '已发布', archived: '已归档' },
    type: { guide: '指南', reference: '参考', api: 'API', architecture: '架构设计', deployment: '部署运维', adr: '技术决策（ADR）', changelog: '更新日志' },
    search: '搜索标题、摘要或正文',
    newDoc: '新建文档',
    count: '共 {n} 篇',
    empty: '暂无文档',
    pick: '从左侧选择一篇文档',
    appliesTo: '适用 {v}',
    revision: '第 {n} 版',
    mode: { edit: '编辑', split: '分栏', preview: '预览' },
    save: '保存',
    saving: '保存中…',
    saved: '已保存',
    unsaved: '有未保存的修改',
    conflictState: '保存冲突',
    publish: '发布',
    more: '更多',
    info: '文档信息',
    history: '版本历史',
    importMd: '导入 Markdown',
    exportMd: '导出 Markdown',
    exportHtml: '导出 HTML',
    archive: '归档',
    remove: '删除',
    conflictTitle: '这篇文档已被他人修改，你的修改尚未保存。',
    conflictHint: '可以先导出 Markdown 备份当前内容，再',
    reload: '重新载入',
    conflictTail: '最新版本。',
    discardBody: '当前文档有未保存的修改，继续将丢失这些修改。',
    discardTitle: '未保存的修改',
    discardOk: '放弃修改',
    discardCancel: '继续编辑',
    publishPrompt: '简要说明本次发布的变更（可选）',
    publishTitle: '发布文档',
    publishPlaceholder: '如：补充权限配置示例',
    published: '已发布',
    archiveBody: '归档后文档不再出现在默认列表中，历史版本保留，之后编辑会重新变为草稿。',
    archiveTitle: '归档文档',
    removeBody: '删除「{title}」？',
    removeTitle: '删除文档',
    removed: '已删除',
    saveFailed: '保存失败',
    tooLarge: '文件超过 2 MB',
    importBody: '用「{name}」的内容替换当前草稿？',
    imported: '已导入，记得保存'
  }
};

const enUS: typeof zhCN = {
  pluginDocument: {
    all: 'All documents',
    scope: { framework: 'FunAdmin framework', plugin_dev: 'Plugin development', official_plugin: 'Official plugins', third_party: 'Third-party plugins' },
    status: { draft: 'Draft', published: 'Published', archived: 'Archived' },
    type: { guide: 'Guide', reference: 'Reference', api: 'API', architecture: 'Architecture', deployment: 'Deployment', adr: 'Decision record (ADR)', changelog: 'Changelog' },
    search: 'Search title, summary or body',
    newDoc: 'New document',
    count: '{n} documents',
    empty: 'No documents yet',
    pick: 'Select a document on the left',
    appliesTo: 'For {v}',
    revision: 'Revision {n}',
    mode: { edit: 'Edit', split: 'Split', preview: 'Preview' },
    save: 'Save',
    saving: 'Saving…',
    saved: 'Saved',
    unsaved: 'Unsaved changes',
    conflictState: 'Save conflict',
    publish: 'Publish',
    more: 'More',
    info: 'Document info',
    history: 'Revision history',
    importMd: 'Import Markdown',
    exportMd: 'Export Markdown',
    exportHtml: 'Export HTML',
    archive: 'Archive',
    remove: 'Delete',
    conflictTitle: 'Someone else changed this document; your edits are not saved.',
    conflictHint: 'Export Markdown to back up your version first, then',
    reload: 'reload',
    conflictTail: 'the latest version.',
    discardBody: 'This document has unsaved changes. Continuing will discard them.',
    discardTitle: 'Unsaved changes',
    discardOk: 'Discard',
    discardCancel: 'Keep editing',
    publishPrompt: 'Briefly describe what changed (optional)',
    publishTitle: 'Publish document',
    publishPlaceholder: 'e.g. Added permission examples',
    published: 'Published',
    archiveBody: 'Archived documents leave the default list. Revisions are kept; editing makes it a draft again.',
    archiveTitle: 'Archive document',
    removeBody: 'Delete “{title}”?',
    removeTitle: 'Delete document',
    removed: 'Deleted',
    saveFailed: 'Save failed',
    tooLarge: 'File is larger than 2 MB',
    importBody: 'Replace the current draft with “{name}”?',
    imported: 'Imported — remember to save'
  }
};

export const documentMessages = { 'zh-CN': zhCN, 'en-US': enUS };

let registered = false;
/** 把插件文案合并进宿主 i18n；只新增 pluginDocument 命名空间，不覆盖宿主文案。 */
export function registerDocumentLocales(): void {
  if (registered) return;
  for (const [locale, messages] of Object.entries(documentMessages)) i18n.global.mergeLocaleMessage(locale, messages);
  registered = true;
}
