import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');

describe('统一表单发布引擎契约', () => {
  it('提供发布状态迁移与模型 JSON 配置', () => {
    const migrationPath = resolve(root, 'admin/database/core/migrations/archive/066_form_publish_engine.sql');
    expect(existsSync(migrationPath)).toBe(true);
    const migration = readFileSync(migrationPath, 'utf8');
    for (const column of ['publish_config', 'publish_status', 'published_at', 'crud_generation_id', 'published_definition_hash']) {
      expect(migration).toContain(column);
    }
    expect(read('admin/app/admin/form/model/Form.php')).toContain("'publish_config'");
  });

  it('通过独立工厂把表单元数据转换为 CRUD Definition', () => {
    const factoryPath = resolve(root, 'admin/app/admin/development/service/FormCrudDefinitionFactory.php');
    expect(existsSync(factoryPath)).toBe(true);
    const factory = readFileSync(factoryPath, 'utf8');
    expect(factory).toContain('final class FormCrudDefinitionFactory');
    expect(factory).toContain('public function create(');
    for (const key of ['generationTargets', 'permissionPrefix', 'listFormatter', 'listWidth', 'layoutSchema']) {
      expect(factory).toContain(`'${key}'`);
    }
    expect(factory).toContain("managedField('id', 'bigint unsigned', true)");
    expect(factory).toContain("foreach (['created_at', 'updated_at', 'deleted_at'] as $managed)");
    for (const layer of ['model', 'validate', 'service', 'controller']) expect(factory).toContain(`/${layer}/{$class}`);
    expect(factory).toContain('app/admin/business/');
    expect(factory).toContain("'enabled' => (bool) $config['dataScopeEnabled']");
  });

  it('动态发布与正式生成统一由 Business API 暴露', () => {
    const dynamic = read('admin/app/admin/form/service/FormPublishService.php');
    const business = read('admin/app/admin/development/controller/Business.php');
    const orchestration = read('admin/app/admin/development/service/BusinessDevelopmentService.php');
    expect(dynamic).toContain('public function previewDynamic(');
    expect(dynamic).toContain('public function publishDynamic(');
    expect(dynamic).toContain('applyDynamicDdl($payload)');
    expect(dynamic).not.toContain('preflightGeneration(');
    expect(business).toContain('$this->business->previewPublish(');
    expect(business).toContain('$this->business->publish(');
    expect(orchestration).toContain('ManagedGenerationService');
    expect(orchestration).toContain('previewFormalGeneration(');
    expect(orchestration).toContain('formalGeneration(');
  });

  it('多级表单控制器发布权限可被 nodeAccess 正确解析', () => {
    const authorization = read('admin/app/admin/authorization/service/AdminAuthorizationService.php');
    expect(authorization).toContain("preg_match('/^[a-z][a-z0-9_.-]*$/', $object)");
    const migration = read('admin/database/core/migrations/archive/066_form_publish_engine.sql');
    for (const code of ['console/form.designer:previewpublish', 'console/form.designer:publish', 'console/form.designer:publishstatus', 'console/form.designer:retryresources', 'form:publish:overwrite', 'form:publish:apply-resources']) {
      expect(migration).toContain(code);
    }
    expect(migration).toContain('CONVERT(X\'');
    expect(migration).not.toMatch(/'[\u4e00-\u9fff]+(?:[\u4e00-\u9fff/ ]*)'/);
    const fullMigration = read('admin/database/core/migrations/archive/079_form_full_publish_permissions.sql');
    for (const action of ['preview', 'publish', 'status', 'generation', 'retryresources']) {
      expect(fullMigration).toContain(`console/form.full-publish:${action}`);
    }
  });

  it('CRUD Definition 接受完整筛选、格式化和布局元数据', () => {
    const validator = read('admin/app/common/crud/DefinitionValidator.php');
    for (const key of ['listFormatter', 'listWidth', 'placeholder', 'controlProps']) expect(validator).toContain(`'${key}'`);
    for (const operator of ['ne', 'not_like', 'starts_with', 'ends_with', 'gt', 'lt', 'not_in', 'is_null', 'not_null']) {
      expect(validator).toContain(`'${operator}'`);
    }
    const definition = read('admin/app/common/crud/CrudDefinition.php');
    expect(definition).toContain("'layoutSchema'");
  });

  it('表单设置（编辑生成信息）提供所属菜单选择，并将选择纳入发布配置', () => {
    // 设计器「基本信息」页签已并入「⚙ 表单设置」打开的 EditGenerationDialog → ConfigTab。
    const config = read('web/src/admin/views/development/business/codegen/tabs/ConfigTab.vue');
    const editor = read('web/src/admin/views/development/business/codegen/useGenerationEditor.ts');
    const generationConfig = read('web/src/admin/views/development/business/codegen/generationConfig.ts');
    expect(config).toContain("t('business.codegen.edit.parentMenu', '所属菜单')");
    expect(config).toContain(':model-value="publish.parentSourceName || undefined"');
    expect(config).toContain("emitPublish({ parentSourceName: String(value ?? ''), parentId: null })");
    expect(editor).toContain('menuApi.tree()');
    expect(generationConfig).toContain('sourceType');
    expect(read('web/src/admin/views/form/designer/index.vue')).toContain('<EditGenerationDialog');
  });

  it('设计器隔离动态发布与正式生成，并对冲突 fail closed', () => {
    // 三步「生成正式模块」弹窗已移除：设计器的预览代码 / 生成 / 动态发布统一由代码生成的 CodePreviewDialog 承担。
    const designer = read('web/src/admin/views/form/designer/index.vue');
    const preview = read('web/src/admin/views/development/business/codegen/CodePreviewDialog.vue');
    const formal = read('web/src/admin/views/development/business/codegen/formalGeneration.ts');
    expect(designer).toContain('<CodePreviewDialog');
    expect(designer).not.toContain('publishVisible');
    for (const label of ['动态发布', '生成到项目', '与本地对比', '重新预览']) expect(preview).toContain(label);
    expect(preview).toContain('businessDevelopmentApi.previewPublish');
    expect(preview).toContain('businessDevelopmentApi.previewFormalGeneration');
    expect(formal).toContain('businessDevelopmentApi.formalGeneration');
    expect(preview).toContain(':disabled="!canConfirmGeneration"');
    expect(formal).toContain('preview.plan.blocked || previewConflictCount(preview) > 0');
    expect(preview).toContain('confirmToken');
    for (const source of [designer, preview, formal]) {
      expect(source).not.toContain('allowOverwrite');
      expect(source).not.toContain('formFullPublishApi.');
    }
  });

  it('表单设计器仅保留基础模式，移除高级模式专属入口和状态', () => {
    const designer = read('web/src/admin/views/form/designer/index.vue');
    expect(designer).not.toContain('designerMode');
    expect(designer).not.toContain('高级模式');
    expect(designer).not.toContain('SchemaJsonEditor');
    expect(designer).not.toContain('SchemaStructurePanel');
    // 版本历史按改版设计放入顶栏「⋯」菜单（与编辑生成信息弹窗共用同一 VersionHistoryDrawer）。
    expect(designer).toContain('<VersionHistoryDrawer v-if="canViewVersions && moduleId"');
    expect(designer).not.toContain('buildDesignerDebugState');
    expect(designer).not.toContain('buildSchemaDebugSummary');
  });

  it('正式生成复用服务端 operation，并在响应不确定时按 generationId 收敛', () => {
    const designer = read('web/src/admin/views/form/designer/index.vue');
    const preview = read('web/src/admin/views/development/business/codegen/CodePreviewDialog.vue');
    const formal = read('web/src/admin/views/development/business/codegen/formalGeneration.ts');
    // 每次预览使用新的客户端 nonce；下载 zip 复用同一 nonce，不让打开中的计划被 supersede。
    expect(preview).toContain('const nonce = crypto.randomUUID();');
    expect(preview).toContain('previewFormalGeneration(module.id, nonce)');
    expect(preview).toContain('formalGenerationArchive(module.id, nonce)');
    expect(formal).toContain('businessDevelopmentApi.generation(generationId)');
    expect(formal).toContain("generation.status === 'completed' && generation.result");
    expect(formal).toContain("{ kind: 'pending' }");
    expect(preview).toContain('生成状态尚未确认，请到生成记录查看结果');
    expect(preview).toContain('useBusinessMenuRefresh');
    expect(preview).toContain("t('business.codegen.preview.menuRefreshFailed', '操作成功，菜单刷新失败')");
    expect(designer).toContain('useBusinessMenuRefresh');
    expect(read('admin/app/admin/development/service/ManagedGenerationService.php')).toContain("'parentSourceName'");
    expect(read('admin/app/admin/development/service/GenerationResourceTransaction.php')).toContain("'parentSourceName'");
    expect(designer).not.toContain('permissionStore.fetchMenus()');
  });

  it('正式生成按钮维持 generate 与 apply-resources 权限边界', () => {
    const designer = read('web/src/admin/views/form/designer/index.vue');
    const preview = read('web/src/admin/views/development/business/codegen/CodePreviewDialog.vue');
    const formal = read('web/src/admin/views/development/business/codegen/formalGeneration.ts');
    // 设计器顶栏「生成」需要预览 + 生成权限；真正写入项目的「生成到项目」另需 formal-generation 与 apply-resources。
    expect(designer).toContain('const canGenerate = computed(() => hasPermission([PERM_PREVIEW, PERM_GENERATE], \'all\'));');
    expect(formal).toContain("export const PERM_PREVIEW = 'development:business:preview-formal-generation';");
    expect(formal).toContain("export const PERM_GENERATE = 'development:business:generate';");
    expect(formal).toContain("export const PERMS_FORMAL_GENERATION = ['development:business:formal-generation', PERM_GENERATE, 'development:business:apply-resources'];");
    expect(preview).toMatch(/v-if="canGenerateToProject"[\s\S]*?data-action="generate"/);
    expect(preview).toContain("const canGenerateToProject = computed(() => hasPermission(PERMS_FORMAL_GENERATION, 'all'));");
  });

  it('生成菜单优先独立源码并使用预置发布宿主兜底', () => {
    const router = read('web/src/admin/router/dynamic.ts');
    expect(router).toContain("normalized.startsWith('business/')");
    expect(router).toContain("import('@/views/form/published.vue')");
    expect(router).toContain("import { resolveMenuPath, adminRoutePath } from '@/utils/route'");
    expect(existsSync(resolve(root, 'web/src/admin/views/form/published.vue'))).toBe(true);
  });

  it('动态发布 routePath 可命中发布宿主并传递 formKey 路径参数', () => {
    const routes = read('web/src/admin/router/routes.ts');
    const published = read('web/src/admin/views/form/published.vue');
    expect(routes).toContain("path: 'development/business/runtime/:formKey'");
    expect(routes).toContain("name: 'PublishedFormRuntime'");
    expect(routes).toContain("import('@/views/form/published.vue')");
    expect(published).toContain('route.params.formKey');
  });

  it('发布兜底宿主严格使用已发布 canonical FormSchema v2 运行态', () => {
    const published = read('web/src/admin/views/form/published.vue');
    const api = read('web/src/admin/api/formData.ts');
    expect(published).toContain('SchemaRenderer');
    expect(published).not.toContain('SchemaForm');
    expect(published).toContain(':schema="meta.schema"');
    expect(published).toContain('const schemaHash = meta.value.schemaHash');
    expect(published).toContain('setFieldErrors(mapFieldErrors(errors))');
    expect(published).toContain('source?.children');
    expect(published).toContain('buildSubmissionPayload');
    expect(api).toContain('schema: FormSchemaDocument');
    expect(api).toContain('schemaHash: string');
    expect(api).toContain('etag: string');
    expect(api).toContain('{ data, include, schemaHash }');
  });

});
