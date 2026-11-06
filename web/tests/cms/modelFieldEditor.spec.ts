import { defineComponent, h } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import ModelPanel from '../../../admin/plugins/cms/web/src/admin/modules/cms/components/ModelPanel.vue';
const http=vi.hoisted(()=>({get:vi.fn(),post:vi.fn()}));vi.mock('@/utils/http',()=>({default:http}));
const pass=defineComponent({setup(_,{slots}){return()=>h('div',[slots.default?.(),slots.footer?.()]);}});
const input=defineComponent({props:['modelValue'],emits:['update:modelValue'],setup(p,{emit}){return()=>h('input',{value:p.modelValue,onInput:(e:any)=>emit('update:modelValue',e.target.value)});}});
const button=defineComponent({emits:['click'],setup(_,{slots,emit}){return()=>h('button',{onClick:()=>emit('click')},slots.default?.());}});
const stubs={...Object.fromEntries(['ElAlert','ElDialog','ElForm','ElFormItem','ElSelect','ElOption','ElCheckbox','ElSwitch'].map(n=>[n,pass])),ElInput:input,ElInputNumber:input,ElButton:button,ElTable:true,ElTableColumn:true};
beforeEach(()=>{vi.clearAllMocks();http.get.mockResolvedValue([]);http.post.mockResolvedValue({});});
it('offers typed default help placeholder and numeric/text constraints and persists the schema',async()=>{
 const w=mount(ModelPanel,{props:{scope:{site_id:'7',locale:'zh-cn'},settings:{models:[]},can:()=>true,isSuper:true},global:{stubs}});await flushPromises();
 await w.findAll('button').find(b=>b.text()==='新建模型')!.trigger('click');await flushPromises();const state=(w.vm as any).$.setupState;
 Object.assign(state.form,{code:'example',name:'示例',fields:[{name:'count',label:'数量',type:'number',min:0,max:10,default:0},{name:'source',label:'来源',type:'text',default:'本站',max_length:20}]});await flushPromises();
 for(const label of ['填写说明','占位提示','默认值','最小值','最大值','最大字符数'])expect(w.findAll('input').some(x=>x.attributes('aria-label')?.includes(label))).toBe(true);
 await w.findAll('button').find(b=>b.text()==='保存新版本')!.trigger('click');await flushPromises();expect(http.post).toHaveBeenCalledWith('/plugin/cms/models/save',expect.objectContaining({fields:expect.arrayContaining([expect.objectContaining({name:'count',default:0,min:0,max:10}),expect.objectContaining({name:'source',max_length:20})])}));w.unmount();
});
