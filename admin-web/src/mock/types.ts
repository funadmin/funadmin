export type MockMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';

export interface MockContext {
  url: string;
  method: MockMethod;
  params: Record<string, any>;
  body: Record<string, any>;
  pathParams: Record<string, string>;
  headers: Record<string, any>;
}

export type MockHandler = (ctx: MockContext) => any | Promise<any>;

export interface MockRoute {
  method: MockMethod;
  /** 字符串匹配（精确）或正则（支持路径参数） */
  url: string | RegExp;
  /** 路径参数名列表，与正则捕获组顺序一致 */
  paramNames?: string[];
  handler: MockHandler;
}

/** 统一返回工具 */
export const ok = <T = any>(data: T, msg = 'success') => ({
  code: 200,
  msg,
  data,
  time: Date.now()
});

export const fail = (msg = '请求失败', code = 400, data: any = null) => ({
  code,
  msg,
  data,
  time: Date.now()
});

export const page = <T = any>(list: T[], total = list.length, p = 1, pageSize = 10) => ({
  list,
  total,
  page: p,
  pageSize
});
