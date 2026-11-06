export default {
    states: { uploading: '上传中', ready: '已就绪', failed: '上传失败' }, sendHint: 'Enter 发送 · Shift + Enter 换行',
    approval: '审批模式', attach: '添加附件', attachment: '私有附件', retry: '重试', remove: '移除', download: '安全下载',
    privacy: '消息和附件中的敏感材料将发送给当前档案的 Provider。仅支持 PNG/JPEG/WebP（每图 5MiB）和 UTF-8 代码文本（128KiB），最多 4 个，总计 12MiB。',
    invalid: '附件类型、名称、大小或数量不符合限制，已明确拒绝。', uploadFailed: '附件上传失败或不是有效 UTF-8 文本，请重试或移除；不会忽略失败附件。',
    removeFailed: '附件移除失败，请重试。', sendFailed: '发送失败，请重试。已创建的消息与任务幂等标识将保留，避免重复消息。',
    incompatible: '当前模型或备用模型未声明兼容这些图片，请调整档案能力或移除图片后发送。', downloadFailed: '私有附件读取失败，请检查权限并重试。',
    imageInput: '允许图片输入（管理员声明）', maxImages: '最大图片数量', imageTokens: '每图固定 Token 预算'
  };
