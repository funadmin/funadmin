import { i18n } from '@/locales';

const zhCN = {
  pluginOss: {
    page: {
      title: 'OSS 存储', subtitle: '管理统一公开附件的对象存储', noPermission: '无权查看存储配置', failed: '操作失败，请重试',
      managed: '正在接管新上传', unmanaged: '未配置，尚未接管',
      currentSummary: '当前存储：{name} · {provider} · {bucket}。统一公开附件的新上传将使用此配置。',
      idleSummary: '插件已启用。添加配置并设为当前存储后，自动接管统一公开附件的新上传。',
      hint: '切换当前配置只影响新上传；历史附件仍使用原配置。测试连接会上传并删除随机测试对象。'
    },
    providers: { aliyun: '阿里云 OSS', tencent: '腾讯云 COS', qiniu: '七牛云 Kodo', s3: 'S3 兼容（AWS S3 / MinIO / R2）' },
    list: {
      add: '添加配置', recycle: '回收站', empty: '暂无存储配置', profile: '配置', provider: '厂商', location: '存储位置', domain: '访问域名 / 目录',
      credentials: '凭据', actions: '操作', current: '当前存储', standby: '历史存储 / 备用配置', root: '根目录', set: '已设置', unset: '未设置',
      edit: '编辑', activate: '设为当前存储', test: '测试连接', delete: '删除', pathStyle: 'path-style',
      deleteTitle: '删除存储配置', deleteBody: '删除配置「{name}」？配置会移入回收站，可以恢复；已有附件仍按此配置读取和删除。', deleted: '已删除，可在回收站恢复'
    },
    form: {
      addTitle: '添加存储配置', editTitle: '编辑存储配置', required: '请填写所有必填项',
      locked: '存储目标不可修改；如需更换桶、域名或目录，请添加新配置。密钥留空保留现有凭据。',
      name: '配置名称', provider: '云厂商', bucket: 'Bucket', endpoint: 'Endpoint', endpointOptional: 'Endpoint（AWS 可留空）', region: 'Region', qiniuRegion: '上传区域',
      domain: '公开访问域名', prefix: '目录前缀', prefixPlaceholder: 'public；留空使用根目录', pathStyle: '使用 path-style 访问',
      pathStyleHelp: 'MinIO 等自建服务一般需要开启；AWS S3 与 Cloudflare R2 通常关闭。',
      s3Help: 'Endpoint 只接受 HTTPS；AWS 留空按 Region 使用官方地址，MinIO、R2 等填写服务地址（R2 的 Region 填 auto）。',
      accessKey: '访问密钥 ID', secretKey: '访问密钥', keepSecret: '留空保留现有值', accessPlaceholder: '请输入访问密钥 ID', secretPlaceholder: '请输入访问密钥',
      cancel: '取消', save: '保存', test: '测试连接', testHint: '使用当前表单的值测试，无需先保存。'
    },
    test: {
      title: '连接测试', success: '连接测试通过（{ms} 毫秒）', failure: '连接测试未通过（{ms} 毫秒）',
      steps: { upload: '上传测试对象', read: '读取并校验内容', delete: '删除测试对象' },
      status: { ok: '成功', failed: '失败', skipped: '跳过' },
      reasons: { error: '请求失败，请检查配置、凭据与网络', mismatch: '读回的内容与上传的不一致', timeout: '接近超时，已跳过', upload_failed: '上传未成功，已跳过' }
    },
    recycle: {
      title: '配置回收站', empty: '回收站为空', deletedAt: '删除时间', restore: '恢复', destroy: '彻底删除', restored: '已恢复',
      destroyTitle: '彻底删除存储配置', destroyBody: '彻底删除配置「{name}」？配置及其加密凭据将被永久删除，此操作不可撤销。仍有附件（含回收站中的附件）使用此配置时不能彻底删除。云端对象不会被删除。',
      destroyed: '已彻底删除', cancel: '取消'
    },
    errors: {
      notFound: '存储配置不存在或已被删除，请刷新后重试', notInRecycle: '存储配置不在回收站中，请刷新后重试',
      current: '不能删除当前存储配置，请先把其他配置设为当前存储', inUse: '仍有附件（含回收站中的附件）使用此配置，不能彻底删除'
    }
  }
};

const enUS: typeof zhCN = {
  pluginOss: {
    page: {
      title: 'OSS storage', subtitle: 'Object storage for unified public attachments', noPermission: 'You do not have permission to view storage profiles', failed: 'The operation failed. Please try again.',
      managed: 'Taking over new uploads', unmanaged: 'Not configured; uploads are not taken over',
      currentSummary: 'Current storage: {name} · {provider} · {bucket}. New unified public attachment uploads use this profile.',
      idleSummary: 'The plugin is enabled. Add a profile and set it as current to take over new unified public attachment uploads.',
      hint: 'Switching the current profile only affects new uploads; existing attachments keep their original profile. A connection test uploads and deletes a random test object.'
    },
    providers: { aliyun: 'Alibaba Cloud OSS', tencent: 'Tencent Cloud COS', qiniu: 'Qiniu Kodo', s3: 'S3 compatible (AWS S3 / MinIO / R2)' },
    list: {
      add: 'Add profile', recycle: 'Recycle bin', empty: 'No storage profiles', profile: 'Profile', provider: 'Provider', location: 'Location', domain: 'Public domain / directory',
      credentials: 'Credentials', actions: 'Actions', current: 'Current storage', standby: 'Previous / standby profile', root: 'Root directory', set: 'Set', unset: 'Not set',
      edit: 'Edit', activate: 'Set as current', test: 'Test connection', delete: 'Delete', pathStyle: 'path-style',
      deleteTitle: 'Delete storage profile', deleteBody: 'Delete the profile “{name}”? It moves to the recycle bin and can be restored; existing attachments are still read and deleted through it.', deleted: 'Deleted. You can restore it from the recycle bin.'
    },
    form: {
      addTitle: 'Add storage profile', editTitle: 'Edit storage profile', required: 'Fill in all required fields',
      locked: 'The storage target cannot be changed; add a new profile to use another bucket, domain or directory. Leave the keys empty to keep the saved credentials.',
      name: 'Profile name', provider: 'Provider', bucket: 'Bucket', endpoint: 'Endpoint', endpointOptional: 'Endpoint (optional for AWS)', region: 'Region', qiniuRegion: 'Upload region',
      domain: 'Public domain', prefix: 'Directory prefix', prefixPlaceholder: 'public; leave empty for the root directory', pathStyle: 'Use path-style addressing',
      pathStyleHelp: 'Usually required by self-hosted services such as MinIO; usually off for AWS S3 and Cloudflare R2.',
      s3Help: 'The endpoint must use HTTPS. Leave it empty for AWS to use the official endpoint of the region; enter the service URL for MinIO, R2 and others (use the region auto for R2).',
      accessKey: 'Access key ID', secretKey: 'Secret access key', keepSecret: 'Leave empty to keep the saved value', accessPlaceholder: 'Enter the access key ID', secretPlaceholder: 'Enter the secret access key',
      cancel: 'Cancel', save: 'Save', test: 'Test connection', testHint: 'Tests the values in this form; saving is not required.'
    },
    test: {
      title: 'Connection test', success: 'Connection test passed ({ms} ms)', failure: 'Connection test failed ({ms} ms)',
      steps: { upload: 'Upload test object', read: 'Read back and verify', delete: 'Delete test object' },
      status: { ok: 'OK', failed: 'Failed', skipped: 'Skipped' },
      reasons: { error: 'The request failed. Check the settings, credentials and network.', mismatch: 'The content read back differs from the upload', timeout: 'Skipped because the time limit was near', upload_failed: 'Skipped because the upload failed' }
    },
    recycle: {
      title: 'Profile recycle bin', empty: 'The recycle bin is empty', deletedAt: 'Deleted at', restore: 'Restore', destroy: 'Delete permanently', restored: 'Restored',
      destroyTitle: 'Permanently delete storage profile', destroyBody: 'Permanently delete the profile “{name}”? The profile and its encrypted credentials are removed for good. This cannot be undone. It cannot be deleted while any attachment, including those in the recycle bin, still uses it. Cloud objects are not deleted.',
      destroyed: 'Deleted permanently', cancel: 'Cancel'
    },
    errors: {
      notFound: 'The storage profile does not exist or has been deleted. Refresh and try again.', notInRecycle: 'The storage profile is not in the recycle bin. Refresh and try again.',
      current: 'The current storage profile cannot be deleted. Set another profile as current first.', inUse: 'Attachments (including those in the recycle bin) still use this profile, so it cannot be deleted permanently.'
    }
  }
};

export const ossMessages = { 'zh-CN': zhCN, 'en-US': enUS };

let registered = false;
/** 把 OSS 插件文案合并进宿主 i18n；只新增 pluginOss 命名空间，不覆盖宿主文案。 */
export function registerOssLocales(): void {
  if (registered) return;
  for (const [locale, messages] of Object.entries(ossMessages)) i18n.global.mergeLocaleMessage(locale, messages);
  registered = true;
}
