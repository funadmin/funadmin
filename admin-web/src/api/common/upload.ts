import http from '@/utils/http';

/** 上传成功后的统一返回结构 */
export interface UploadResult {
  /** 文件可访问 URL（mock 环境为 base64 / blob URL） */
  url: string;
  /** 原始文件名 */
  name: string;
  /** 文件大小（字节） */
  size: number;
  /** 扩展名（小写，不含点） */
  ext: string;
  /** 附件记录实际所属分组 */
  groupId: number;
  /** 实际使用的存储驱动 */
  driver: string;
  /** 是否复用了已有同内容附件 */
  reused: boolean;
  /** 上传时间戳（毫秒） */
  uploadedAt: number;
}

/**
 * 通用上传：把单个 File 走 multipart/form-data 上传
 * 后端契约：字段名 `file`，可选业务分类 `bizType`
 */
export const uploadApi = {
  upload(file: File, bizType: 'image' | 'file' | 'avatar' = 'file', groupId?: number): Promise<UploadResult> {
    const fd = new FormData();
    fd.append('file', file);
    fd.append('bizType', bizType);
    if (groupId) fd.append('groupId', String(groupId));
    return http.upload<UploadResult>('/upload', fd);
  }
};

export default uploadApi;
