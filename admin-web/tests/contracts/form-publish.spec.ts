import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');

describe('统一表单发布引擎契约', () => {
  it('提供发布状态迁移与模型 JSON 配置', () => {
    const migrationPath = resolve(root, 'database/migrations/066_form_publish_engine.sql');
    expect(existsSync(migrationPath)).toBe(true);
    const migration = readFileSync(migrationPath, 'utf8');
    for (const column of ['publish_config', 'publish_status', 'published_at', 'crud_generation_id', 'published_definition_hash']) {
      expect(migration).toContain(column);
    }
    expect(read('app/console/model/Form.php')).toContain("'publish_config'");
  });

  it('通过独立工厂把表单元数据转换为 CRUD Definition', () => {
    const factoryPath = resolve(root, 'app/console/service/FormCrudDefinitionFactory.php');
    expect(existsSync(factoryPath)).toBe(true);
    const factory = readFileSync(factoryPath, 'utf8');
    expect(factory).toContain('final class FormCrudDefinitionFactory');
    expect(factory).toContain('public function create(');
    for (const key of ['generationTargets', 'permissionPrefix', 'listFormatter', 'listWidth', 'layoutSchema']) {
      expect(factory).toContain(`'${key}'`);
    }
    expect(factory).toContain("managedField('id', 'bigint unsigned', true)");
    expect(factory).toContain("foreach (['created_at', 'updated_at', 'deleted_at'] as $managed)");
  });

  it('动态发布 DDL 契约不要求迁移文件', () => {
    const api = read('admin-web/src/api/form.ts');
    expect(api).toMatch(/interface MigrationPreview[\s\S]*?file\?: string;/);
  });

  it('发布服务只保留纯动态 API 与兼容委托', () => {
    const service = read('app/console/service/FormPublishService.php');
    expect(service).toContain('public function previewDynamic(');
    expect(service).toContain('public function publishDynamic(');
    expect(service).toContain('return $this->previewDynamic($payload);');
    expect(service).toContain('return $this->publishDynamic($payload, $operator);');
    expect(service).toContain('applyDynamicDdl($payload)');
    expect(service).not.toContain('applyMigration($payload)');
    for (const forbidden of ['DevCrudService', 'FormCrudDefinitionFactory', 'CrudGenerator', 'AtomicWriter', 'preflightGeneration(', '->generate(', 'generation(', 'retryResources(']) {
      expect(service).not.toContain(forbidden);
    }
    expect(existsSync(resolve(root, 'app/console/service/FormFullPublishService.php'))).toBe(false);
  });

  it('表单控制器通过原端点调用纯动态发布服务', () => {
    const controller = read('app/console/controller/form/Designer.php');
    for (const route of ["#[Post('preview-publish')]", "#[Post('publish')]", "#[Get('publish-status/:id')]"]) {
      expect(controller).toContain(route);
    }
    expect(controller).toContain('$this->publisher->previewDynamic($this->payload())');
    expect(controller).toContain('$this->publisher->publishDynamic(');
    for (const forbidden of ['FormFullPublishService', "post('confirmToken'", "post('allowOverwrite'", "#[Post('retry-resources/:id')]", 'generation(', 'retryResources(']) {
      expect(controller).not.toContain(forbidden);
    }
  });

  it('多级表单控制器发布权限可被 nodeAccess 正确解析', () => {
    const authorization = read('app/console/service/AdminAuthorizationService.php');
    expect(authorization).toContain("preg_match('/^[a-z][a-z0-9_.-]*$/', $object)");
    const migration = read('database/migrations/066_form_publish_engine.sql');
    for (const code of ['console/form.designer:previewpublish', 'console/form.designer:publish', 'console/form.designer:publishstatus', 'console/form.designer:retryresources', 'form:publish:overwrite', 'form:publish:apply-resources']) {
      expect(migration).toContain(code);
    }
    expect(migration).toContain('CONVERT(X\'');
    expect(migration).not.toMatch(/'[\u4e00-\u9fff]+(?:[\u4e00-\u9fff/ ]*)'/);
  });

  it('CRUD Definition 接受完整筛选、格式化和布局元数据', () => {
    const validator = read('app/common/crud/DefinitionValidator.php');
    for (const key of ['listFormatter', 'listWidth', 'placeholder', 'controlProps']) expect(validator).toContain(`'${key}'`);
    for (const operator of ['ne', 'not_like', 'starts_with', 'ends_with', 'gt', 'lt', 'not_in', 'is_null', 'not_null']) {
      expect(validator).toContain(`'${operator}'`);
    }
    const definition = read('app/common/crud/CrudDefinition.php');
    expect(definition).toContain("'layoutSchema'");
  });

  it('设计器提供纯动态发布向导与发布结果', () => {
    const designer = read('admin-web/src/views/form/designer/index.vue');
    for (const label of ['发布设置', '变更预览', '冲突确认', '发布结果']) expect(designer).toContain(label);
    expect(designer).toContain('previewPublish');
    expect(designer).toContain('formSchemaHash');
    expect(designer).toContain('formDependencyHash');
    expect(designer).toContain('publishResult');
    expect(designer).not.toContain('allowOverwrite');
    expect(designer).not.toContain('confirmToken');
  });

  it('生成菜单优先独立源码并使用预置发布宿主兜底', () => {
    const templates = read('app/common/crud/ProductionTemplateContext.php');
    expect(templates).toContain("'component=generated/'");
    const router = read('admin-web/src/router/dynamic.ts');
    expect(router).toContain("normalized.startsWith('generated/')");
    expect(router).toContain("import('@/views/form/published.vue')");
    expect(router).toContain("import { resolveMenuPath } from '@/utils/route'");
    expect(existsSync(resolve(root, 'admin-web/src/views/form/published.vue'))).toBe(true);
  });

  it('动态发布 routePath 可命中发布宿主并传递 formKey 路径参数', () => {
    const routes = read('admin-web/src/router/routes.ts');
    const published = read('admin-web/src/views/form/published.vue');
    expect(routes).toContain("path: 'development/business/runtime/:formKey'");
    expect(routes).toContain("name: 'PublishedFormRuntime'");
    expect(routes).toContain("import('@/views/form/published.vue')");
    expect(published).toContain('route.params.formKey');
  });

  it('发布兜底宿主严格使用已发布 canonical FormSchema v2 运行态', () => {
    const published = read('admin-web/src/views/form/published.vue');
    const api = read('admin-web/src/api/formData.ts');
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

  it('表单列表展示发布状态并提供动态运行时入口', () => {
    const list = read('admin-web/src/views/form/list.vue');
    for (const label of ['发布状态', '预览运行时', '打开独立页面', '重新发布']) expect(list).toContain(label);
    expect(list).toContain('publish_status');
    expect(list).toContain('/development/business/runtime/');
    expect(list).not.toContain('生成记录');
    expect(list).not.toContain('crud_generation_id');
  });
});
