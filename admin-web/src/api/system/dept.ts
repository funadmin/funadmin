import http from '@/utils/http';

const PREFIX = '/system/dept';

export interface DeptModel {
  id: number;
  parentId: number;
  name: string;
  sort: number;
  status: 0 | 1;
  leader?: string;
  phone?: string;
  email?: string;
  children?: DeptModel[];
}

export const deptApi = {
  tree: () => http.get<DeptModel[]>(`${PREFIX}/tree`),
  detail: (id: number) => http.get<DeptModel>(`${PREFIX}/${id}`),
  create: (data: Partial<DeptModel>) =>
    http.post<DeptModel>(`${PREFIX}`, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: Partial<DeptModel>) =>
    http.put<DeptModel>(`${PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } }),
  /** 批量删除（接受 number[] ids） */
  removeMany: (ids: number[]) =>
    http.delete<{ removed: number }>(`${PREFIX}`, { ids }, { requestOptions: { showSuccessMsg: true } })
};
