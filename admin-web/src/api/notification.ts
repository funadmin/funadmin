import http from '@/utils/http';

export type NoticeType = 'notice' | 'message' | 'todo';

export interface NoticeItem {
  id: number;
  type: NoticeType;
  title: string;
  desc: string;
  time: string;
  icon: string;
  color: string;
  read: boolean;
}

const PREFIX = '/notification';

export const notificationApi = {
  list: () => http.get<NoticeItem[]>(`${PREFIX}/list`),
  unread: () => http.get<{ notice: number; message: number; todo: number; total: number }>(`${PREFIX}/unread`),
  read: (id: number) => http.post<void>(`${PREFIX}/${id}/read`),
  readAll: () => http.post<void>(`${PREFIX}/read-all`),
  clear: () => http.post<void>(`${PREFIX}/clear`)
};
