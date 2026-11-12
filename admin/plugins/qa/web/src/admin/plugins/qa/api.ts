import http from '@/utils/http';
import type { Id, QaQuestionDetail, QaQuestionList, QaRecycleList, QaRecycleResult, QaScope, QaTrashedAnswer, QaTrashedQuestion } from './types';

const base = '/plugins/qa';
/** 问答后台接口（1.2.0）。每个接口有自己的权限码，站点内复用 CMS 投稿站点权限。 */
export const qaApi = {
  questions: (scope: QaScope, params: { status?: string; q?: string; page?: number }) => http.get<QaQuestionList>(`${base}/questions`, { ...scope, ...params }),
  detail: (scope: QaScope, id: Id) => http.get<QaQuestionDetail>(`${base}/questions/detail`, { ...scope, id }),
  status: (scope: QaScope, id: Id, question_version: number, closed: boolean) => http.post<QaQuestionDetail>(`${base}/questions/status`, { ...scope, id, question_version, closed }),
  pin: (scope: QaScope, id: Id, question_version: number, pinned: boolean) => http.post<QaQuestionDetail>(`${base}/questions/pin`, { ...scope, id, question_version, pinned }),
  deleteQuestion: (scope: QaScope, id: Id) => http.post<{ count: number }>(`${base}/questions/delete`, { ...scope, id }),
  trashedQuestions: (scope: QaScope, page: number, pageSize = 20) => http.get<QaRecycleList<QaTrashedQuestion>>(`${base}/questions/recycle`, { ...scope, page, pageSize }),
  restoreQuestions: (scope: QaScope, ids: Id[]) => http.post<QaRecycleResult>(`${base}/questions/restore`, { ...scope, ids }),
  destroyQuestions: (scope: QaScope, ids: Id[]) => http.post<QaRecycleResult>(`${base}/questions/destroy`, { ...scope, ids }),
  answerVisibility: (scope: QaScope, id: Id, hidden: boolean) => http.post<QaQuestionDetail>(`${base}/answers/visibility`, { ...scope, id, hidden }),
  deleteAnswer: (scope: QaScope, id: Id) => http.post<{ count: number }>(`${base}/answers/delete`, { ...scope, id }),
  trashedAnswers: (scope: QaScope, page: number, pageSize = 20) => http.get<QaRecycleList<QaTrashedAnswer>>(`${base}/answers/recycle`, { ...scope, page, pageSize }),
  restoreAnswers: (scope: QaScope, ids: Id[]) => http.post<QaRecycleResult>(`${base}/answers/restore`, { ...scope, ids }),
  destroyAnswers: (scope: QaScope, ids: Id[]) => http.post<QaRecycleResult>(`${base}/answers/destroy`, { ...scope, ids })
};
