/** 问答后台类型（1.3.0）：问题管理、详情与回答（含会员附件）、回收站。 */
export type Id = string;
export interface QaScope { site_id: Id; locale: string }
export interface QaMember { id: Id; username: string; nickname: string }
export interface QaAttachment { id: Id; name: string; mime: string; size: number; created_at: string }
export interface QaQuestionRow { id: Id; title: string; status: string; lock_version: number; review_reason: string; created_at: string; member: QaMember; accepted_answer_id: Id | null; closed: boolean; pinned: boolean; question_version: number; answers_count: number }
export interface QaQuestionList { items: QaQuestionRow[]; total: number; page: number; limit: number }
export interface QaAnswer { id: Id; body_html: string; status: string; lock_version: number; review_reason: string; created_at: string; member: QaMember; attachments: QaAttachment[]; hidden: boolean; accepted: boolean }
export interface QaQuestionDetail extends Omit<QaQuestionRow, 'answers_count'> { body_html: string; attachments: QaAttachment[]; answers: QaAnswer[]; answers_total: number }
export interface QaTrashedQuestion { id: Id; deleted_at: string; title: string; member_id: Id | null; status: string; answers_count: number; submission_missing: boolean }
export interface QaTrashedAnswer { id: Id; deleted_at: string; question_id: Id; question_title: string; excerpt: string; member_id: Id | null; status: string; submission_missing: boolean }
export interface QaRecycleList<T> { list: T[]; total: number; page: number; pageSize: number }
export interface QaRecycleResult { count: number; skipped?: number }
