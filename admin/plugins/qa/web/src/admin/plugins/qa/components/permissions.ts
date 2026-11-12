import { hasPermission } from '@/utils/permission';

/**
 * 问答后台操作权限：宿主权限码（每个接口一个）且本站具有对应的 CMS 投稿站点权限
 * （读取 cms:submission:list，写操作 cms:submission:review），与后端判断一致。
 */
export type QaAction = 'index' | 'detail' | 'status' | 'pin' | 'delete' | 'recycle' | 'restore' | 'destroy' | 'answerVisibility' | 'answerDelete' | 'answerRecycle' | 'answerRestore' | 'answerDestroy';
const HOST: Record<QaAction, string> = {
  index: 'qa:questions:index', detail: 'qa:questions:detail', status: 'qa:questions:status', pin: 'qa:questions:pin', delete: 'qa:questions:delete',
  recycle: 'qa:questions:recycle', restore: 'qa:questions:restore', destroy: 'qa:questions:destroy',
  answerVisibility: 'qa:answers:visibility', answerDelete: 'qa:answers:delete', answerRecycle: 'qa:answers:recycle', answerRestore: 'qa:answers:restore', answerDestroy: 'qa:answers:destroy'
};
const READ: QaAction[] = ['index', 'detail', 'recycle', 'answerRecycle'];

export function qaAllowed(action: QaAction, site: (operation: string) => boolean): boolean {
  return hasPermission(HOST[action]) && site(READ.includes(action) ? 'cms:submission:list' : 'cms:submission:review');
}
