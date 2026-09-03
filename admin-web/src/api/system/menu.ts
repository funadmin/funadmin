import http from '@/utils/http';

const PREFIX = '/system/menu';

export const menuApi = {
  /** 树形菜单 */
  tree: () => http.get<API.MenuItem[]>(`${PREFIX}/tree`),
  detail: (id: number) => http.get<API.MenuItem>(`${PREFIX}/${id}`),
  create: (data: Partial<API.MenuItem>) =>
    http.post<API.MenuItem>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (
    id: number,
    data: Partial<API.MenuItem>,
    config?: {
      requestOptions?: { showSuccessMsg?: boolean; showErrorMsg?: boolean };
    },
  ) =>
    http.put<API.MenuItem>(`${PREFIX}/${id}`, data, {
      ...config,
      requestOptions: { showSuccessMsg: true, ...config?.requestOptions },
    }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  /** 批量删除（树形：调用方需保证父子关系一致性） */
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}`, { ids }, { requestOptions: { showSuccessMsg: true } })
};
