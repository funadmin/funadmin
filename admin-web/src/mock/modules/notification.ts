/**
 * 消息中心 Mock：通知 / 消息 / 待办
 */
import { ok, type MockRoute } from '../types';

interface NoticeItem {
  id: number;
  type: 'notice' | 'message' | 'todo';
  title: string;
  desc: string;
  time: string;
  icon: string;
  color: string;
  read: boolean;
}

let list: NoticeItem[] = [
  {
    id: 1,
    type: 'notice',
    title: 'Admin Console v1.0 发布',
    desc: '新版本带来全新视觉系统、动态路由、丰富 mock 数据',
    time: '5 分钟前',
    icon: 'i-ep-bell',
    color: '#6366f1',
    read: false
  },
  {
    id: 2,
    type: 'notice',
    title: '系统维护通知',
    desc: '今晚 23:00-24:00 进行系统维护，请及时保存数据',
    time: '1 小时前',
    icon: 'i-ep-warning',
    color: '#f59e0b',
    read: false
  },
  {
    id: 3,
    type: 'message',
    title: '@产品经理 评论了您',
    desc: '"这个交互很顺，先这样上线"',
    time: '20 分钟前',
    icon: 'i-ep-chat-line-square',
    color: '#10b981',
    read: false
  },
  {
    id: 4,
    type: 'message',
    title: '@张三 发起了视频会议',
    desc: '邀请您参与"周三需求评审"',
    time: '今天 09:30',
    icon: 'i-ep-video-camera',
    color: '#0ea5e9',
    read: true
  },
  {
    id: 5,
    type: 'todo',
    title: '任务：API 联调',
    desc: '截止时间 2026-04-25',
    time: '明天到期',
    icon: 'i-ep-document',
    color: '#ef4444',
    read: false
  },
  {
    id: 6,
    type: 'todo',
    title: '任务：迭代回顾',
    desc: '产品 / 设计 / 研发参与',
    time: '本周五 18:00',
    icon: 'i-ep-calendar',
    color: '#8b5cf6',
    read: true
  }
];

function unreadCount() {
  const counter = { notice: 0, message: 0, todo: 0, total: 0 };
  list.forEach((i) => {
    if (!i.read) {
      counter[i.type]++;
      counter.total++;
    }
  });
  return counter;
}

export const notificationMockHandlers: MockRoute[] = [
  { method: 'GET', url: '/notification/list', handler: () => ok(list) },
  { method: 'GET', url: '/notification/unread', handler: () => ok(unreadCount()) },
  {
    method: 'POST',
    url: /^\/notification\/(\d+)\/read$/,
    paramNames: ['id'],
    handler: ({ pathParams }) => {
      const id = Number(pathParams.id);
      const item = list.find((i) => i.id === id);
      if (item) item.read = true;
      return ok(null, '已读');
    }
  },
  {
    method: 'POST',
    url: '/notification/read-all',
    handler: () => {
      list.forEach((i) => (i.read = true));
      return ok(null, '全部已读');
    }
  },
  {
    method: 'POST',
    url: '/notification/clear',
    handler: () => {
      list = [];
      return ok(null, '已清空');
    }
  }
];
