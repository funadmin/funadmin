import { qaApi } from '../api';
import type { QaScope, QaTrashedAnswer, QaTrashedQuestion } from '../types';
import type { RecycleSource } from '@/plugins/cms/components/recycle';
import { qaAllowed } from './permissions';

type Translate = (key: string, args?: Record<string, unknown>) => string;

/** 问答回收站页签（问题、回答），交给 CMS 通用回收站抽屉展示；CMS 投稿已删除的行不能恢复。 */
export function qaRecycleSources(scope: QaScope, site: (operation: string) => boolean, t: Translate): RecycleSource<any>[] {
  const status = (value: string) => (value ? t(`pluginQa.status.${value}`) : '');
  const questions: RecycleSource<QaTrashedQuestion> = {
    key: 'questions', label: t('pluginQa.recycle.questions'),
    canView: qaAllowed('recycle', site), canRestore: qaAllowed('restore', site), canDestroy: qaAllowed('destroy', site),
    load: async (page) => { const data = await qaApi.trashedQuestions(scope, page); return { rows: data.list, total: data.total, pageSize: data.pageSize }; },
    title: (row) => row.title || t('pluginQa.recycle.untitled'),
    meta: (row) => row.submission_missing ? t('pluginQa.recycle.missing') : t('pluginQa.recycle.questionMeta', { status: status(row.status), count: row.answers_count }),
    restore: (ids) => qaApi.restoreQuestions(scope, ids),
    destroy: (ids) => qaApi.destroyQuestions(scope, ids),
    destroyBody: (row) => t('pluginQa.recycle.destroyQuestionBody', { name: row.title || t('pluginQa.recycle.untitled') })
  };
  const answers: RecycleSource<QaTrashedAnswer> = {
    key: 'answers', label: t('pluginQa.recycle.answers'),
    canView: qaAllowed('answerRecycle', site), canRestore: qaAllowed('answerRestore', site), canDestroy: qaAllowed('answerDestroy', site),
    load: async (page) => { const data = await qaApi.trashedAnswers(scope, page); return { rows: data.list, total: data.total, pageSize: data.pageSize }; },
    title: (row) => row.excerpt || t('pluginQa.recycle.untitled'),
    meta: (row) => row.submission_missing ? t('pluginQa.recycle.missing') : t('pluginQa.recycle.answerMeta', { question: row.question_title || row.question_id }),
    restore: (ids) => qaApi.restoreAnswers(scope, ids),
    destroy: (ids) => qaApi.destroyAnswers(scope, ids),
    destroyBody: () => t('pluginQa.recycle.destroyAnswerBody')
  };
  return [questions, answers];
}
