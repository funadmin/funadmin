import http from '@/utils/http';

/** 最近操作日志摘要（不含请求参数与请求体）。 */
export interface DashboardLogSummary {
  id: number;
  username: string;
  name: string;
  action: string;
  method: string;
  url: string;
  ip: string;
  status: 0 | 1;
  createdAt: string;
}

/**
 * 仪表盘系统概览。每块数据只在当前管理员拥有对应模块查看权限时返回，没有权限的块不出现。
 * 后端按管理员缓存 60 秒，generatedAt 为统计生成时间。
 */
export interface DashboardOverview {
  generatedAt: string;
  admins?: { total: number };
  members?: { total: number; today: number };
  /** size 为字节数 */
  attachments?: { total: number; size: number };
  logs?: {
    today: number;
    /** 近 7 天（含今天），按日期升序 */
    trend: { date: string; count: number }[];
    latest: DashboardLogSummary[];
  };
  plugins?: { installed: number; enabled: number };
}

const PREFIX = '/dashboard';

export const dashboardApi = {
  /** 失败时由页面内联展示错误，不再弹出全局提示。 */
  overview: () => http.get<DashboardOverview>(`${PREFIX}/overview`, undefined, {
    requestOptions: { showErrorMsg: false }
  })
};
