import {
  businessDevelopmentApi,
  isBusinessApiError,
  type BusinessApiErrorResponse,
  type BusinessFormalGenerationPreview,
  type BusinessFormalGenerationResult
} from '@/api/development/business';

/** 预览代码与批量生成共用的正式生成权限与调用流程。 */
export const PERM_PREVIEW = 'development:business:preview-formal-generation';
export const PERM_GENERATE = 'development:business:generate';
export const PERMS_FORMAL_GENERATION = ['development:business:formal-generation', PERM_GENERATE, 'development:business:apply-resources'];

/** 后端收到重载信号后逐个重启 worker；等待片刻再刷新菜单、打开新页面，避免命中尚未注册新路由的旧 worker。 */
export const BACKEND_RELOAD_SETTLE_MS = 1500;

export const waitForBackendReload = (ms: number = BACKEND_RELOAD_SETTLE_MS): Promise<void> =>
  new Promise((resolve) => { setTimeout(resolve, ms); });

export const previewConflictCount = (preview: BusinessFormalGenerationPreview): number => preview.conflicts.length;

/** 计划被阻断或存在冲突文件时不能生成。 */
export const isPreviewBlocked = (preview: BusinessFormalGenerationPreview): boolean =>
  Boolean(preview.plan.blocked || previewConflictCount(preview) > 0);

/**
 * 正式生成读取「已发布」的 Schema；从未发布过的表单预览会被后端拒绝（FormSchemaRepository::published）。
 * 后端 BusinessApiErrorMapper 目前把 FormSchemaException 统一映射为 VALIDATION_ERROR 并透出原文「表单尚未发布」，
 * 这里同时兼容将来直接透传的 FORM_SCHEMA_NOT_PUBLISHED 错误码。
 */
export const FORM_SCHEMA_NOT_PUBLISHED = 'FORM_SCHEMA_NOT_PUBLISHED';
const FORM_SCHEMA_NOT_PUBLISHED_MESSAGE = '表单尚未发布';

export function isFormNotPublishedError(error: unknown): boolean {
  if (!isBusinessApiError(error)) return false;
  const code = error.data.error.code;
  return code === FORM_SCHEMA_NOT_PUBLISHED || (code === 'VALIDATION_ERROR' && error.msg.trim() === FORM_SCHEMA_NOT_PUBLISHED_MESSAGE);
}

export type FormalGenerationOutcome =
  | { kind: 'done'; result: BusinessFormalGenerationResult }
  /** 服务端明确拒绝（确认令牌已消费、计划失效等）：需要重新预览。 */
  | { kind: 'rejected'; error: BusinessApiErrorResponse }
  /** 网络中断等未知结果，且生成记录尚未确认完成：不得重复提交。 */
  | { kind: 'pending' };

/**
 * 用预览返回的 generationId 与 confirmToken 提交正式生成。
 * 结果未知时查询一次生成记录，避免重复提交。options.deferReload 仅批量生成使用。
 */
export async function confirmFormalGeneration(
  moduleId: number,
  generationId: number,
  confirmToken: string,
  options?: { deferReload?: boolean }
): Promise<FormalGenerationOutcome> {
  try {
    const result = options
      ? await businessDevelopmentApi.formalGeneration(moduleId, generationId, confirmToken, options)
      : await businessDevelopmentApi.formalGeneration(moduleId, generationId, confirmToken);
    return { kind: 'done', result };
  } catch (error) {
    if (isBusinessApiError(error)) return { kind: 'rejected', error };
  }
  try {
    const generation = await businessDevelopmentApi.generation(generationId);
    if (generation.status === 'completed' && generation.result) {
      return {
        kind: 'done',
        result: {
          generationId: generation.id,
          state: String(generation.result.state ?? generation.status),
          resourceApplyStatus: String(generation.result.resourceApplyStatus ?? ''),
          resourceApplyError: generation.result.resourceApplyError,
          routePath: generation.result.routePath ?? generation.routePath,
          definitionHash: generation.result.definitionHash ?? generation.definitionHash,
          schemaHash: generation.result.schemaHash ?? generation.schemaHash
        }
      };
    }
  } catch {
    // fall through to pending
  }
  return { kind: 'pending' };
}
