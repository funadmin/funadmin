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

export const previewConflictCount = (preview: BusinessFormalGenerationPreview): number => preview.conflicts.length;

/** 计划被阻断或存在冲突文件时不能生成。 */
export const isPreviewBlocked = (preview: BusinessFormalGenerationPreview): boolean =>
  Boolean(preview.plan.blocked || previewConflictCount(preview) > 0);

export type FormalGenerationOutcome =
  | { kind: 'done'; result: BusinessFormalGenerationResult }
  /** 服务端明确拒绝（确认令牌已消费、计划失效等）：需要重新预览。 */
  | { kind: 'rejected'; error: BusinessApiErrorResponse }
  /** 网络中断等未知结果，且生成记录尚未确认完成：不得重复提交。 */
  | { kind: 'pending' };

/**
 * 用预览返回的 generationId 与 confirmToken 提交正式生成。
 * 结果未知时查询一次生成记录，避免重复提交。
 */
export async function confirmFormalGeneration(moduleId: number, generationId: number, confirmToken: string): Promise<FormalGenerationOutcome> {
  try {
    return { kind: 'done', result: await businessDevelopmentApi.formalGeneration(moduleId, generationId, confirmToken) };
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
