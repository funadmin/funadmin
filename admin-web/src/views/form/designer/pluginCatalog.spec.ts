import { describe, expect, it } from 'vitest';
import type { FormComponentCatalog } from '@/api/form';
import { createField } from '../registry';
import { useDesigner } from '../composables/useDesigner';
import {
  catalogItemToControlMeta,
  createPluginCatalog,
  propertyControls,
  type PluginCatalogRegistration
} from './pluginCatalog';

const catalog: FormComponentCatalog = {
  schemaVersion: 2,
  components: [{
    type: 'demo:rating',
    namespace: 'demo',
    component: 'Rating',
    kind: 'field',
    label: '插件评分',
    group: '业务控件',
    defaultColumnType: 'tinyint',
    valueType: 'number',
    defaultValue: 0,
    defaultProps: { max: 5 },
    propertySchema: {
      type: 'object',
      properties: {
        max: { type: 'integer', title: '最大分值', minimum: 1 }
      }
    },
    codec: 'demo:rating',
    allowedAttrs: [],
    allowedEvents: ['change']
  }]
};

const registered = (type = 'demo:rating'): PluginCatalogRegistration => ({
  registered: [type],
  diagnostics: []
});

describe('设计器插件组件目录', () => {
  it('把成功白名单加载的 componentCatalog 项转换为完整 ControlMeta', () => {
    const state = createPluginCatalog();

    state.accept(catalog, registered());

    expect(state.controls.value).toEqual([catalogItemToControlMeta(catalog.components[0])]);
    expect(state.controls.value[0]).toMatchObject({
      type: 'demo:rating',
      label: '插件评分',
      group: '业务控件',
      defaultColumnType: 'tinyint',
      valueType: 'number',
      defaultProps: { max: 5 },
      propertySchema: catalog.components[0].propertySchema
    });
    expect(createField('demo:rating', 1)).toMatchObject({
      type: 'demo:rating',
      label: '插件评分',
      column_type: 'tinyint',
      control_props: { max: 5 }
    });
    const designer = useDesigner();
    const node = designer.addNode('demo:rating');
    expect(node).toMatchObject({ type: 'demo:rating', valueType: 'number', props: { max: 5 } });
  });

  it('把 propertySchema 转换为属性面板工作流定义', () => {
    expect(propertyControls(catalogItemToControlMeta(catalog.components[0]))).toEqual([{
      name: 'max',
      label: '最大分值',
      type: 'integer',
      minimum: 1
    }]);
  });

  it('丢弃未成功进入构建白名单的目录项并保留加载诊断', () => {
    const state = createPluginCatalog();

    state.accept(catalog, {
      registered: [],
      diagnostics: [{ code: 'missing-plugin', type: 'demo:rating', message: '插件组件未包含在当前构建中：demo:rating' }]
    });

    expect(state.controls.value).toEqual([]);
    expect(state.diagnostics.value).toContainEqual(expect.objectContaining({ code: 'missing-plugin', type: 'demo:rating' }));
    expect(state.canPublish([{ type: 'demo:rating' }])).toBe(false);
  });

  it('版本不匹配时显示诊断、保留结果且禁止发布', () => {
    const state = createPluginCatalog();
    const incompatible = { ...catalog, schemaVersion: 3 } as unknown as FormComponentCatalog;

    state.accept(incompatible, registered());

    expect(state.controls.value).toEqual([]);
    expect(state.diagnostics.value[0]).toMatchObject({ code: 'version-mismatch' });
    expect(state.canPublish([])).toBe(false);
  });

  it('为已加载目录之外的命名空间字段生成缺失插件诊断', () => {
    const state = createPluginCatalog();
    state.accept(catalog, registered());

    const diagnostics = state.diagnoseFields([{ type: 'missing:control' }, { type: 'input' }]);

    expect(diagnostics).toEqual([expect.objectContaining({
      code: 'missing-plugin',
      type: 'missing:control'
    })]);
    expect(state.canPublish([{ type: 'missing:control' }])).toBe(false);
  });
});