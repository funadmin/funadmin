/**
 * 通用上传 Mock：
 * - axios 上传 multipart/form-data 时，前端 FormData 会被原样挂在 config.data 上
 * - mock 适配器在 parseBody 中无法 JSON 化 FormData，因此 ctx.body 会是 FormData 实例
 * - 这里直接读取 file 字段，针对图片走 FileReader -> base64 让前端立即可预览
 */
import { fail, ok, type MockRoute } from '../types';

const MAX_BYTES = 5 * 1024 * 1024; // 5MB

function getExt(name: string): string {
  const i = name.lastIndexOf('.');
  return i < 0 ? '' : name.slice(i + 1).toLowerCase();
}

function readAsDataURL(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ''));
    reader.onerror = () => reject(reader.error || new Error('读取失败'));
    reader.readAsDataURL(file);
  });
}

export const uploadMockHandlers: MockRoute[] = [
  {
    method: 'POST',
    url: '/upload',
    handler: async ({ body }) => {
      // axios 把 FormData 原样挂到 data 上；自定义 parseBody 不会破坏它
      const fd: FormData | undefined = body instanceof FormData ? body : undefined;

      let file: File | null = null;
      let bizType = 'file';

      if (fd) {
        file = fd.get('file') as File | null;
        bizType = String(fd.get('bizType') || 'file');
      } else if (body && typeof body === 'object') {
        // 极少数环境下 body 已被 qs.parse；保底兜一层
        file = (body.file as File) || null;
        bizType = String(body.bizType || 'file');
      }

      if (!file || !(file instanceof File)) {
        return fail('未接收到上传文件');
      }
      if (file.size > MAX_BYTES) {
        return fail(`文件大小超过 ${MAX_BYTES / 1024 / 1024}MB`);
      }

      const ext = getExt(file.name);
      const isImage = file.type.startsWith('image/');

      // 图片走 base64，方便前端立即预览；其他文件给假地址即可
      const url = isImage
        ? await readAsDataURL(file)
        : `https://mock.local/${bizType}/${Date.now()}-${encodeURIComponent(file.name)}`;

      return ok(
        {
          url,
          name: file.name,
          size: file.size,
          ext,
          uploadedAt: Date.now()
        },
        '上传成功'
      );
    }
  }
];
