import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
const read = (path: string) => readFileSync(new URL(path, import.meta.url), 'utf8');
describe('实际页面按钮接线', () => {
  it('动态及分类宿主传入协议上下文、权限和全部成功回调', () => {
    for (const path of ['../data.vue', '../components/ListSourceTree.vue']) {
      const source = read(path);
      expect(source).toContain(':context="');
      expect(source).toContain(':permission-check="');
      expect(source).toContain(':clear-selection="');
      expect(source).toContain(':close="');
      expect(source).toContain(':refresh="');
    }
    expect(read('../data.vue')).toContain('@selection-change="');
    expect(read('../data.vue')).toContain('clearSelection();');
    expect(read('../data.vue')).toContain(':lock="buttonLock" :form-key="formKey"');
    expect(read('../data.vue')).toContain('actionFilterFields');
    expect(read('../components/ListSourceTree.vue')).toContain('props.lock ?? localButtonLock');
  });
  it('动态页共享组件接入行、顶部及工具，不保留硬编码编辑按钮', () => {
    const source = read('../data.vue');
    expect(source).toContain(':buttons="rowButtons"');
    expect(source).toContain(':buttons="toolbarButtons"');
    expect(source).toContain(':schema="tableSchema"');
    expect(source).toContain('tools: meta.value.schema.list.tools');
    expect(read('../../../components/DataTable/SchemaTablePage.vue')).toContain(':show-refresh="definition.list?.tools?.refresh !== false"');
    expect(source).toContain('hasRowButtons.value ?');
    expect(source).not.toContain('@click="openDialog(row)"');
  });
});
