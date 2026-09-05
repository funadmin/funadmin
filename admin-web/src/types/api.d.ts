/**
 * 后端统一响应结构
 * 后端统一响应风格：{ code, msg, data, time }
 */
declare namespace API {
  interface Response<T = any> {
    code: number;
    msg: string;
    data: T;
    time?: number;
  }

  interface PageResult<T = any> {
    list: T[];
    total: number;
    page: number;
    pageSize: number;
  }

  interface PageQuery {
    page?: number;
    pageSize?: number;
    keyword?: string;
    [key: string]: any;
  }

  interface LoginParams {
    username: string;
    password: string;
    captcha: string;
    remember?: boolean;
  }

  interface LoginResult {
    authenticated: boolean;
  }

  interface UserInfo {
    id: number;
    username: string;
    nickname: string;
    avatar: string;
    email?: string;
    mobile?: string;
    roles: string[];
    permissions: string[];
  }

  interface MenuItem {
    id: number;
    parentId: number;
    routeName: string;
    path: string;
    component?: string;
    redirect?: string;
    type: 'M' | 'C' | 'B';
    icon?: string;
    name: string;
    sort: number;
    hidden: boolean;
    keepAlive: boolean;
    affix: boolean;
    permission?: string;
    children?: MenuItem[];
  }
}
