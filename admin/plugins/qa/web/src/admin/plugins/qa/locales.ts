import { i18n } from '@/locales';

const zhCN = {
  pluginQa: {
    tabs: { moderation: '投稿审核', questions: '问题管理' },
    status: { pending: '待审核', published: '已发布', rejected: '已驳回', withdrawn: '已撤回', hidden: '已下线' },
    list: { search: '搜索问题标题', allStatus: '全部状态', refresh: '刷新', empty: '没有问题', pinned: '置顶', closed: '已关闭', answers: '{n} 个回答', member: '提问：{name}', detail: '详情', delete: '删除', deleteTitle: '删除问题', deleteBody: '删除问题「{name}」？问题会移入回收站，可以恢复；回答随问题一起不再显示。', deleted: '已删除，可在回收站恢复', failed: '操作失败，请稍后重试' },
    detail: { title: '问题详情', member: '提问会员', status: '审核状态', state: '问答状态', open: '开放中', closed: '已关闭', body: '问题内容', attachments: '附件', reviewReason: '审核意见', answers: '回答（{n}）', capped: '只显示前 {n} 条回答', noAnswers: '还没有回答', accepted: '已采纳', hidden: '已隐藏', hide: '隐藏', unhide: '取消隐藏', deleteAnswer: '删除', deleteAnswerTitle: '删除回答', deleteAnswerBody: '删除这条回答？回答会移入回收站，可以恢复。', close: '关闭问题', reopen: '重新开放', pin: '置顶', unpin: '取消置顶', delete: '删除问题', saved: '已保存', conflict: '问题已被修改，已刷新为最新内容' },
    recycle: { questions: '问题', answers: '回答', untitled: '（无标题）', questionMeta: '{status} · {count} 个回答', answerMeta: '问题：{question}', missing: '对应的 CMS 投稿已删除，无法恢复', destroyQuestionBody: '彻底删除问题「{name}」？问答侧的问题记录及其全部回答状态将被永久删除，此操作不可撤销。CMS 投稿记录保留。', destroyAnswerBody: '彻底删除这条回答？问答侧的回答记录将被永久删除，此操作不可撤销。CMS 投稿记录保留。' }
  }
};

const enUS: typeof zhCN = {
  pluginQa: {
    tabs: { moderation: 'Submission review', questions: 'Questions' },
    status: { pending: 'Pending review', published: 'Published', rejected: 'Rejected', withdrawn: 'Withdrawn', hidden: 'Unpublished' },
    list: { search: 'Search question titles', allStatus: 'All statuses', refresh: 'Refresh', empty: 'No questions', pinned: 'Pinned', closed: 'Closed', answers: '{n} answers', member: 'Asked by {name}', detail: 'Details', delete: 'Delete', deleteTitle: 'Delete question', deleteBody: 'Delete the question “{name}”? It moves to the recycle bin and can be restored; its answers are hidden with it.', deleted: 'Deleted. You can restore it from the recycle bin.', failed: 'The operation failed. Please try again later.' },
    detail: { title: 'Question details', member: 'Asked by', status: 'Review status', state: 'Q&A state', open: 'Open', closed: 'Closed', body: 'Question', attachments: 'Attachments', reviewReason: 'Review comment', answers: 'Answers ({n})', capped: 'Showing the first {n} answers', noAnswers: 'No answers yet', accepted: 'Accepted', hidden: 'Hidden', hide: 'Hide', unhide: 'Unhide', deleteAnswer: 'Delete', deleteAnswerTitle: 'Delete answer', deleteAnswerBody: 'Delete this answer? It moves to the recycle bin and can be restored.', close: 'Close question', reopen: 'Reopen', pin: 'Pin', unpin: 'Unpin', delete: 'Delete question', saved: 'Saved', conflict: 'The question was changed elsewhere and has been reloaded.' },
    recycle: { questions: 'Questions', answers: 'Answers', untitled: '(untitled)', questionMeta: '{status} · {count} answers', answerMeta: 'Question: {question}', missing: 'The CMS submission was deleted; it cannot be restored', destroyQuestionBody: 'Permanently delete the question “{name}”? The Q&A record and all of its answer states are removed for good. This cannot be undone. The CMS submissions are kept.', destroyAnswerBody: 'Permanently delete this answer? Its Q&A record is removed for good. This cannot be undone. The CMS submission is kept.' }
  }
};

export const qaMessages = { 'zh-CN': zhCN, 'en-US': enUS };

let registered = false;
/** 把问答插件文案合并进宿主 i18n；只新增 pluginQa 命名空间，不覆盖宿主文案。 */
export function registerQaLocales(): void {
  if (registered) return;
  for (const [locale, messages] of Object.entries(qaMessages)) i18n.global.mergeLocaleMessage(locale, messages);
  registered = true;
}
