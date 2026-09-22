import { execFileSync } from 'node:child_process';
import { runInNewContext } from 'node:vm';
import { resolve } from 'node:path';
import { compileScript, parse } from '@vue/compiler-sfc';
import ts from 'typescript';
import * as Vue from 'vue';
import * as VueI18n from 'vue-i18n';
import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, afterEach } from 'vitest';
import ElementPlus, { ElMessageBox } from 'element-plus';
import Bar from '../components/ListButtonBar.vue';
import Tree from '../components/ListSourceTree.vue';
import SchemaTablePage from '@/components/DataTable/SchemaTablePage.vue';
import * as host from './listButtonHost';
import * as buttons from '../schema/listButtons';
import { useCrud } from '@/composables/useCrud';
import * as fieldPresentation from './fieldPresentation';

const root = resolve(process.cwd(), '..');
const permission = Vue.reactive({ permissions: [] as string[] });
const php = process.env.PHP_BINARY || '/opt/homebrew/opt/php@8.1/bin/php';
function generate(empty: boolean | 'defaults' = false, batch = true, soft = true, plugin = false, search = true, presentation: Record<string, unknown> = {}) {
  return JSON.parse(execFileSync(php, ['-r', `require 'vendor/autoload.php';
$s = ['schemaVersion'=>2,'key'=>'host_demo','title'=>'宿主测试','nodes'=>[['id'=>'title','kind'=>'field','type'=>'input','field'=>'order_title','title'=>'标题','database'=>['columnType'=>'varchar','length'=>100]]]];
$c = (new app\\common\\form\\schema\\FormSchemaCompiler(new app\\common\\form\\schema\\FormSchemaValidator()))->compile($s);
$d = (new app\\admin\\development\\service\\FormCrudDefinitionFactory())->createFromSchema($c, ['table_name'=>'fun_host_demo']);
$a = $d->toArray(); $a['fields'][0]['name']='order_id'; $a['primaryKey']='order_id';
$a['fields'][1]['search']=${search ? 'true' : 'false'}; $a['fields'][1]['searchOperator']='eq';
$a['fields'][1] = array_replace($a['fields'][1], json_decode(base64_decode('${Buffer.from(JSON.stringify(presentation)).toString('base64')}'), true));
$a['features']['batchDelete']=${batch ? 'true' : 'false'}; $a['softDeletes']=${soft ? 'true' : 'false'};
$a['list']['buttons'] = ${empty ? "['toolbar'=>[], 'row'=>[], 'categoryToolbar'=>[], 'categoryNode'=>[]]" : "['row'=>[['id'=>'approve','label'=>'批准','permission'=>'business:approve','action'=>['type'=>'registered','key'=>'approve','capabilityVersion'=>'v1'],'visibleWhen'=>['op'=>'eq','field'=>'order_title','value'=>'可批准'],'success'=>['refresh'=>true,'clearSelection'=>true]]], 'toolbar'=>[['id'=>'approve_many','label'=>'批量批准','permission'=>'business:approve','action'=>['type'=>'registered','key'=>'approve','capabilityVersion'=>'v1']]], 'categoryNode'=>[['id'=>'approve_category','label'=>'批准分类','permission'=>'business:approve','action'=>['type'=>'registered','key'=>'approve','capabilityVersion'=>'v1']]]]"};
$a['list']['leftTree']=['enabled'=>true,'source'=>['type'=>'module','module'=>'categories'],'mapping'=>['valueField'=>'id','labelField'=>'title','targetField'=>'order_id']];
${empty === 'defaults' ? "unset($a['list']['buttons']);" : ''}
$a['formSchema']['list']=$a['list'];
echo json_encode(app\\common\\crud\\ProductionTemplateContext::build(app\\common\\crud\\CrudDefinition::fromArray($a), ['type'=>${plugin ? "'plugin'" : "'core'"}]));`], { cwd: root, encoding: 'utf8' }));
}
function evaluate(code: string, modules: Record<string, unknown>) {
  const output = ts.transpileModule(code, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.CommonJS, esModuleInterop: false } }).outputText;
  const exports: Record<string, any> = {};
  // 仅执行本仓库模板从固定测试数据生成的产物；导入严格限定于测试模块表。
  runInNewContext(output, { exports, require: (name: string) => {
    if (Object.hasOwn(modules, name)) return modules[name];
    if (/^\.\/components\/HostDemo(Form|Detail)\.vue$/.test(name)) return { default: Vue.defineComponent({ template: '<div />' }) };
    throw Error(`未允许的生成依赖：${name}`);
  } });
  return exports;
}
const box = Vue.defineComponent({ template: '<div><slot /><slot name="toolbar-left" /></div>' });
const shell = Vue.defineComponent({ template: '<div><slot name="search" /><slot name="toolbar-left" /><slot :size="\'small\'" :stripe="false" :border="false" :headerCellStyle="{}" /></div>' });
function render(empty: boolean | 'defaults' = false, batch = true, soft = true) {
  const generated = generate(empty, batch, soft);
  const declaration = JSON.parse(generated.apiContent.match(/listButtonAdapter: (\{[\s\S]*?\}) as const/)[1]);
  const http = { get: vi.fn(async (url: string) => {
    if (url.endsWith('/list-actions')) return { schemaHash: declaration.schemaHash, sourceSchemaHash: 'source-hash', sourceKey: 'categories', actions: { approve: { permission: 'business:approve', capabilityVersion: 'v1', locations: ['row', 'toolbar', 'categoryNode'], targets: ['record', 'selection', 'category'], batch: true, effect: 'read', resultContract: 'json' } } };
    if (url.endsWith('/left-tree')) return { nodes: [{ id: 'cat-7', value: 'cat-7', label: '分类', parent: null }], actions: {}, schemaHash: 'source-hash', sourceKey: 'categories' };
    return { list: [{ orderId: 'order-42', orderTitle: '可批准' }], total: 1 };
  }), post: vi.fn(async () => ({ status: 'success' })), delete: vi.fn() };
  const api = evaluate(generated.apiContent, { '@/utils/http': { default: http } });
  const { descriptor } = parse(generated.viewContent);
  const script = compileScript(descriptor, { id: 'generated-test', inlineTemplate: true });
  const page = evaluate(script.content, { vue: Vue, 'vue-i18n': VueI18n, 'element-plus': { ElMessageBox }, '@/api/generated/host-demo': api, '@/components/DataTable/SchemaTablePage.vue': { default: SchemaTablePage }, '@/composables/useCrud': { useCrud }, '@/views/form/components/ListButtonBar.vue': { default: Bar }, '@/views/form/components/ListSourceTree.vue': { default: Tree }, '@/views/form/runtime/listButtonHost': host, '@/views/form/schema/listButtons': buttons, '@/views/form/runtime/fieldPresentation': fieldPresentation, '@/store/modules/user': { useUserStore: () => permission }, '@/utils/csv': {} }).default;
  permission.permissions = ['business:approve', declaration.catalogPermission, declaration.executePermission, 'generated:host-demo:left-tree'];
  const wrapper = mount(page, { global: { plugins: [ElementPlus, VueI18n.createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': {} } })], components: { PageWrapper: box, DataTableShell: shell }, stubs: { SearchForm: true, DataTableShell: shell } } });
  return { wrapper, http, declaration };
}
afterEach(() => { vi.restoreAllMocks(); });
describe('PHP 真实生成页面消费正式宿主', () => {
  it('正式生成列表和详情接入共享字段展示 helper', () => {
    const generated = generate(false, true, true);
    expect(generated.viewContent).toContain("import { formatFieldValue, resolveFieldOptions } from '@/views/form/runtime/fieldPresentation';");
    expect(generated.viewContent).toContain("presentField('orderId', scope.row)");
    expect(generated.detailContent).toContain("import { formatFieldValue, resolveFieldOptions } from '@/views/form/runtime/fieldPresentation';");
    expect(generated.detailContent).toMatch(/presentField\('orderId', \{ \.\.\.row \}\)/);
    for (const content of [generated.viewContent, generated.detailContent]) {
      const { descriptor, errors } = parse(content);
      expect(errors).toEqual([]);
      expect(descriptor.template!.content).not.toContain('JSON.parse');
      expect(descriptor.template!.content).not.toContain('resolveFieldOptions');
      expect(descriptor.scriptSetup!.content).toContain('const fieldPresentations =');
      expect(() => compileScript(descriptor, { id: 'presentation', inlineTemplate: true })).not.toThrow();
    }
  });
  it('列表和详情特殊展示均可编译，详情选项中的插值符号与引号原样显示', () => {
    const label = `嵌套 }} {{ "引号" '单引号' \\ & </script> scope.row.`;
    for (const formatter of ['', 'tag', 'switch', 'boolean', 'image', 'images', 'money', 'percent', 'number', 'link', 'email', 'phone', 'json', 'date', 'datetime', 'time']) {
      const generated = generate(false, true, true, false, true, { listFormatter: formatter, options: [{ label, value: 'chosen' }] });
      for (const content of [generated.viewContent, generated.detailContent]) {
        const { descriptor, errors } = parse(content);
        expect(errors).toEqual([]);
        expect(() => compileScript(descriptor, { id: 'special-presentation', inlineTemplate: true })).not.toThrow();
      }
      if (formatter === 'tag') {
        const { descriptor } = parse(generated.detailContent);
        const script = compileScript(descriptor, { id: 'detail-presentation', inlineTemplate: true });
        const page = evaluate(script.content, { vue: Vue, 'vue-i18n': VueI18n, '@/views/form/runtime/fieldPresentation': fieldPresentation }).default;
        const wrapper = mount(page, { props: { modelValue: true, row: { orderId: 42, orderTitle: 'chosen' } }, global: { stubs: { ElDrawer: box, ElDescriptions: box, ElDescriptionsItem: box, ElTag: box } } });
        expect(wrapper.text()).toContain(label);
        wrapper.unmount();
      }
    }
  });
  it('安全元数据不重新引入敏感字段或关闭的列表详情字段', () => {
    for (const presentation of [{ component: 'password' }, { controlProps: { sensitive: true } }, { controlProps: { writeOnly: true } }, { controlProps: { schemaAccess: 'private' } }]) {
      const generated = generate(false, true, true, false, true, presentation);
      for (const content of [generated.viewContent, generated.detailContent]) {
        const { descriptor } = parse(content);
        expect(descriptor.template!.content).not.toContain('fieldPresentations.orderTitle');
        expect(descriptor.scriptSetup!.content.match(/const fieldPresentations = .*;/)?.[0]).not.toContain('orderTitle');
        expect(generated.controllerContent).not.toContain("'orderTitle' =>");
      }
    }
  });
  it('关闭列表和详情仅影响展示，不作为 DTO 访问授权', () => {
    const generated = generate(false, true, true, false, true, { component: 'hidden', list: false, detail: false });
    for (const content of [generated.viewContent, generated.detailContent]) {
      expect(parse(content).descriptor.template!.content).not.toContain('fieldPresentations.orderTitle');
    }
    expect(generated.controllerContent).toContain("'orderTitle' =>");
  });
  it('hidden 字段即使配置 search 也不生成搜索绑定', () => {
    const generated = generate(false, true, true, false, true, { component: 'hidden', list: true });
    expect(generated.viewContent).not.toContain('query.orderTitle');
    expect(generated.viewContent).not.toContain("['order_title', query.orderTitle]");
  });
  it('正式生成默认工具栏按正常列表、回收站、新增、移入回收站、导入、导出顺序并保留样式元数据', () => {
    const generated = generate('defaults', true, true);
    const defaults = JSON.parse(generated.viewContent.match(/resolveListButtons\(listConfig, 'toolbar', (\[.*\]) as FormListButton\[\]/)[1]);
    expect(defaults.slice(0, 6).map((button: any) => button.label)).toEqual(['正常列表', '回收站', '新增', '移入回收站', 'CSV 导入', 'CSV 导出']);
    expect(defaults[2]).toMatchObject({ color: 'primary', plain: true, icon: 'plus' });
    expect(defaults[3]).toMatchObject({ color: 'danger', plain: true, icon: 'delete', selection: { min: 1 } });
    expect(defaults[4]).toMatchObject({ color: 'success', plain: true, icon: 'upload' });
    expect(defaults[5]).toMatchObject({ color: 'default', plain: true, icon: 'download' });
  });
  it('后端 schema 接受 normal 和布尔 plain，拒绝错误类型和错误位置', () => {
    const result = execFileSync(php, ['-r', `require 'vendor/autoload.php';
$v = new app\\common\\form\\schema\\ListButtonSchemaValidator();
foreach ([true, false] as $plain) $v->validate(['buttons'=>['toolbar'=>[['id'=>'normal','label'=>'正常列表','plain'=>$plain,'action'=>['type'=>'builtin','key'=>'normal']]]]], []);
$count = 0;
foreach ([['toolbar', 'true'], ['toolbar', 1], ['toolbar', null], ['row', true], ['categoryToolbar', true], ['categoryNode', true]] as [$location, $plain]) {
  try { $v->validate(['buttons'=>[$location=>[['id'=>'normal','label'=>'正常列表','plain'=>$plain,'action'=>['type'=>'builtin','key'=>'normal']]]]], []); }
  catch (app\\common\\form\\schema\\FormSchemaException $e) { $count++; }
}
echo $count;`], { cwd: root, encoding: 'utf8' });
    expect(result).toBe('6');
  });
  it('真实生成脚本与 API 在无批删、无软删和插件降级时通过类型检查', () => {
    for (const [batch, soft, plugin, search] of [[true, true, false, true], [false, false, false, true], [false, false, true, true], [true, true, false, false]]) {
      const generated = generate(false, batch, soft, plugin, search);
      const { descriptor } = parse(generated.viewContent);
      const script = compileScript(descriptor, { id: 'typecheck' }).content;
      const pagePath = resolve(process.cwd(), 'src/views/generated/host-demo/index.ts');
      const apiPath = resolve(process.cwd(), 'src/api/generated/host-demo.ts');
      const files: Record<string, string> = { [pagePath]: script, [apiPath]: generated.apiContent };
      const options: ts.CompilerOptions = { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ESNext, moduleResolution: ts.ModuleResolutionKind.Bundler, strict: true, skipLibCheck: true, noEmit: true, paths: { '@/*': [resolve(process.cwd(), 'src/*')] } };
      const compilerHost = ts.createCompilerHost(options);
      const read = compilerHost.readFile; const exists = compilerHost.fileExists;
      compilerHost.readFile = path => files[path] ?? read(path);
      compilerHost.fileExists = path => Object.hasOwn(files, path) || exists(path);
      compilerHost.getSourceFile = (path, version) => { const content = compilerHost.readFile(path); return content === undefined ? undefined : ts.createSourceFile(path, content, version); };
      const program = ts.createProgram([pagePath, apiPath, resolve(process.cwd(), 'src/types/api.d.ts'), resolve(process.cwd(), 'src/vite-env.d.ts')], options, compilerHost);
      const errors = ts.getPreEmitDiagnostics(program).filter(error => error.file && Object.hasOwn(files, error.file.fileName) && (error.code !== 2307 || !String(error.messageText).includes('.vue')));
      expect(errors.map(error => ts.flattenDiagnosticMessageText(error.messageText, '\n'))).toEqual([]);
    }
  }, 30000);
  it('默认按钮真实渲染 plain、图标和禁用选择，状态入口重复点击不反转', async () => {
    const { wrapper, http } = render('defaults'); permission.permissions = ['*']; await flushPromises();
    const bar = wrapper.findAllComponents(Bar).find(item => item.props('context')?.location === 'toolbar')!;
    expect(bar.findAll('button').slice(0, 6).map(item => item.text())).toEqual(['正常列表', '回收站', '新增', '移入回收站', 'CSV 导入', 'CSV 导出']);
    const remove = bar.findAll('button').find(item => item.text() === '移入回收站')!;
    expect(remove.attributes('disabled')).toBeDefined();
    expect(remove.classes()).toContain('is-plain');
    expect(remove.find('svg').exists()).toBe(true);
    expect(bar.findAll('button').find(item => item.text() === '正常列表')!.classes()).toContain('el-button--primary');
    expect(bar.findAll('button').find(item => item.text() === '回收站')!.classes()).toContain('el-button--info');
    await remove.trigger('click'); await flushPromises();
    expect(http.delete).not.toHaveBeenCalled();
    const table = wrapper.findComponent({ name: 'ElTable' });
    table.vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    expect(remove.attributes('disabled')).toBeUndefined();
    table.vm.$emit('selection-change', []); await flushPromises();
    expect(remove.attributes('disabled')).toBeDefined();
    for (const label of ['回收站', '回收站', '正常列表', '正常列表']) {
      await bar.findAll('button').find(item => item.text() === label)!.trigger('click'); await flushPromises();
      expect(http.get).toHaveBeenLastCalledWith('/generated/host-demo', expect.objectContaining({ recycled: label === '回收站' ? 1 : 0 }));
      expect(bar.findAll('button').find(item => item.text() === '正常列表')!.classes()).toContain(label === '正常列表' ? 'el-button--primary' : 'el-button--info');
      expect(bar.findAll('button').find(item => item.text() === '回收站')!.classes()).toContain(label === '回收站' ? 'el-button--warning' : 'el-button--info');
    }
    wrapper.unmount();
  });
  it('批删确认中选择或权限变化时取消，不使用确认后的新选择', async () => {
    const { wrapper, http } = render('defaults'); permission.permissions = ['*']; await flushPromises();
    const table = wrapper.findComponent({ name: 'ElTable' });
    table.vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    await wrapper.findAll('button').find(button => button.text() === '移入回收站')!.trigger('click'); await flushPromises();
    expect(document.body.textContent).toContain('确认移入回收站');
    table.vm.$emit('selection-change', [{ orderId: 'order-99' }]); await flushPromises();
    expect(http.delete).not.toHaveBeenCalled();
    wrapper.unmount();
  });
  it('生成 API、独立权限、snake/camel 主键与发布版本真实进入共享执行链', async () => {
    const { wrapper, http, declaration } = render(); await flushPromises();
    const approve = wrapper.findAll('button').find(button => button.text() === '批准');
    expect(approve).toBeDefined(); expect(approve!.attributes('disabled')).toBeUndefined();
    await approve!.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/list-action', expect.objectContaining({ ids: ['order-42'], schemaHash: declaration.schemaHash, location: 'row' }), expect.anything());
    expect(http.get.mock.calls.some(([url]) => url.includes('/form/data'))).toBe(false);
    permission.permissions = ['business:approve', 'form.data:listactions', 'form.data:listaction']; await flushPromises();
    const revoked = wrapper.findAll('button').find(button => button.text() === '批准');
    expect(!revoked || revoked.attributes('disabled') !== undefined).toBe(true);
    http.post.mockClear(); http.get.mockClear();
    if (revoked) await revoked.trigger('click'); await flushPromises();
    expect(http.post).not.toHaveBeenCalled();
    expect(http.get.mock.calls.some(([url]) => url.endsWith('/list-actions'))).toBe(false);
    wrapper.unmount();
  });
  it('分类树继承生成宿主，节点身份与来源版本不借动态入口', async () => {
    const { wrapper, http } = render(); await flushPromises();
    const approve = wrapper.findAll('button').find(button => button.text() === '批准分类');
    expect(approve).toBeDefined(); expect(approve!.attributes('disabled')).toBeUndefined();
    await approve!.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/list-action', expect.objectContaining({ ids: [], category: { id: 'cat-7' }, sourceSchemaHash: 'source-hash' }), expect.anything());
    wrapper.unmount();
  });
  it('无批删能力仍可选择注册批动作，切换筛选后清空', async () => {
    const { wrapper, http } = render(false, false); await flushPromises();
    const table = wrapper.findComponent({ name: 'ElTable' });
    table.vm.$emit('selection-change', [{ orderId: 'order-42', orderTitle: '可批准' }]); await flushPromises();
    const action = wrapper.findAll('button').find(button => button.text() === '批量批准')!;
    expect(action.attributes('disabled')).toBeUndefined(); await action.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/list-action', expect.objectContaining({ ids: ['order-42'], location: 'toolbar' }), expect.anything()); wrapper.unmount();
  });
  it('回收站仅在能力启用时出现，恢复命中生成 API，显式空不补默认', async () => {
    const { wrapper, http } = render('defaults'); permission.permissions = ['*']; await flushPromises();
    await wrapper.findAll('button').find(button => button.text() === '回收站')!.trigger('click'); await flushPromises();
    const rowBar = wrapper.findAllComponents(Bar).find(bar => bar.props('context')?.location === 'row' && bar.props('row')?.orderId === 'order-42')!;
    expect(rowBar.props('row')).toEqual({ orderId: 'order-42', orderTitle: '可批准' });
    const restore = rowBar.findAll('button').find(button => button.text() === '恢复')!;
    expect(restore).toBeDefined(); expect(restore.attributes('disabled')).toBeUndefined(); await restore.trigger('click'); await flushPromises();
    expect(wrapper.findAllComponents(Bar).flatMap(bar => bar.emitted('error') ?? [])).toEqual([]);
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/order-42/restore');
    wrapper.findComponent({ name: 'ElTable' }).vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    const batchRestore = wrapper.findAll('button').find(button => button.text() === '批量恢复');
    expect(batchRestore).toBeDefined(); await batchRestore!.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/restore', { ids: ['order-42'] }); wrapper.unmount();
    const disabled = render('defaults', false, false); permission.permissions = ['*']; await flushPromises();
    expect(disabled.wrapper.findAll('button').some(button => ['回收站', '恢复', '永久删除'].includes(button.text()))).toBe(false); disabled.wrapper.unmount();
  });
  it('批量回收站按钮使用独立批量权限而非单条权限', async () => {
    const { wrapper, http } = render('defaults');
    permission.permissions = ['generated:host-demo:list', 'generated:host-demo:batch-restore', 'generated:host-demo:batch-destroy']; await flushPromises();
    await wrapper.findAll('button').find(button => button.text() === '回收站')!.trigger('click'); await flushPromises();
    wrapper.findComponent({ name: 'ElTable' }).vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    const restore = wrapper.findAll('button').find(button => button.text() === '批量恢复');
    expect(restore).toBeDefined(); expect(restore!.attributes('disabled')).toBeUndefined();
    expect(wrapper.findAll('button').some(button => button.text() === '恢复')).toBe(false);
    await restore!.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/restore', { ids: ['order-42'] });
    wrapper.findComponent({ name: 'ElTable' }).vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    const destroy = wrapper.findAll('button').find(button => button.text() === '批量永久删除');
    expect(destroy).toBeDefined(); expect(destroy!.attributes('disabled')).toBeUndefined();
    await destroy!.trigger('click'); await flushPromises();
    const confirmation = [...document.querySelectorAll('.el-dialog button')].find(button => button.textContent?.trim() === '确定') as HTMLButtonElement;
    expect(confirmation).toBeDefined(); confirmation.click(); await flushPromises();
    expect(http.delete).toHaveBeenCalledWith('/generated/host-demo/destroy', { ids: ['order-42'] });
    permission.permissions = ['generated:host-demo:list', 'generated:host-demo:restore', 'generated:host-demo:destroy'];
    wrapper.findComponent({ name: 'ElTable' }).vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    for (const label of ['批量恢复', '批量永久删除']) {
      const button = wrapper.findAll('button').find(item => item.text() === label);
      expect(!button || button.attributes('disabled') !== undefined).toBe(true);
    }
    wrapper.unmount();
  });
  it('生成筛选转换为 snake 字段且变化清空注册选择', async () => {
    const { wrapper, http } = render(); await flushPromises();
    const search = wrapper.findComponent({ name: 'SearchForm' });
    const query = (search.vm.$attrs as any).model;
    query.orderTitle = '筛选'; await flushPromises();
    await wrapper.findAll('button').find(button => button.text() === '批准')!.trigger('click'); await flushPromises();
    expect(http.post).toHaveBeenCalledWith('/generated/host-demo/list-action', expect.objectContaining({ filter: { order_title: '筛选' } }), expect.anything());
    wrapper.findComponent({ name: 'ElTable' }).vm.$emit('selection-change', [{ orderId: 'order-42' }]); await flushPromises();
    query.orderTitle = '改变'; await flushPromises();
    expect(wrapper.findAll('button').find(button => button.text() === '批量批准')!.attributes('disabled')).toBeDefined(); wrapper.unmount();
  });
  it('发布版本失配禁止执行', async () => {
    const { wrapper, http } = render(); await flushPromises();
    http.get.mockImplementation(async () => ({ schemaHash: 'stale', actions: {} }) as any);
    await wrapper.findAll('button').find(button => button.text() === '批准')!.trigger('click'); await flushPromises();
    expect(http.post).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('显式空集合不恢复任何默认管理按钮', async () => {
    const { wrapper, http } = render(true); await flushPromises();
    expect(wrapper.findAllComponents(Bar).every(bar => bar.props('buttons').length === 0)).toBe(true);
    expect(http.post).not.toHaveBeenCalled(); wrapper.unmount();
  });
});