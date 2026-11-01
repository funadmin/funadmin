import http from '@/utils/http';

const PREFIX = '/system/attachment';
const GROUP_PREFIX = '/system/attachment-group';

export interface AttachmentModel {
  id: number;
  groupId: number;
  name: string;
  originalName: string;
  path: string;
  url: string;
  thumb: string;
  ext: string;
  size: number;
  mime: string;
  driver: string;
  width: number;
  height: number;
  status: 0 | 1;
  createdAt: string;
  updatedAt: string;
}

export interface AttachmentQuery {
  page: number;
  pageSize: number;
  keyword?: string;
  groupId?: number;
  mimeType?: '' | 'image' | 'video' | 'audio' | 'document' | 'archive';
}

export interface AttachmentGroupModel {
  id: number;
  parentId: number;
  name: string;
  thumb: string;
  status: 0 | 1;
  sort: number;
  isDefault: 0 | 1;
  createdAt: string;
  updatedAt: string;
  children?: AttachmentGroupModel[];
}

export type AttachmentGroupPayload = Pick<AttachmentGroupModel, 'parentId' | 'name' | 'thumb' | 'status' | 'sort'>;

export const attachmentApi = {
  list: (params: AttachmentQuery) => http.get<API.PageResult<AttachmentModel>>(PREFIX, { params }),
  detail: (id: number) => http.get<AttachmentModel>(`${PREFIX}/${id}`),
  rename: (id: number, name: string) =>
    http.put<AttachmentModel>(`${PREFIX}/${id}/name`, { name }, { requestOptions: { showSuccessMsg: true } }),
  move: (ids: number[], groupId: number) =>
    http.post<{ moved: number }>(`${PREFIX}/move`, { ids, groupId }, { requestOptions: { showSuccessMsg: true } }),
  remove: (ids: number[]) =>
    http.delete<{ removed: number }>(PREFIX, { ids }, { requestOptions: { showSuccessMsg: true } })
};

export const attachmentGroupApi = {
  tree: () => http.get<AttachmentGroupModel[]>(`${GROUP_PREFIX}/tree`),
  detail: (id: number) => http.get<AttachmentGroupModel>(`${GROUP_PREFIX}/${id}`),
  create: (data: AttachmentGroupPayload) =>
    http.post<AttachmentGroupModel>(GROUP_PREFIX, data, { requestOptions: { showSuccessMsg: true } }),
  update: (id: number, data: AttachmentGroupPayload) =>
    http.put<AttachmentGroupModel>(`${GROUP_PREFIX}/${id}`, data, { requestOptions: { showSuccessMsg: true } }),
  remove: (id: number) =>
    http.delete<void>(`${GROUP_PREFIX}/${id}`, undefined, { requestOptions: { showSuccessMsg: true } })
};
