import { mount, flushPromises } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import { afterEach, expect, it, vi } from 'vitest';
import AttachmentField from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/AttachmentField.vue';
const http=vi.hoisted(()=>({get:vi.fn(),upload:vi.fn()}));
vi.mock('@/utils/http',()=>({default:http}));
afterEach(()=>vi.clearAllMocks());
it('uploads inside the content form and selects the returned attachment ID',async()=>{
 http.get.mockResolvedValue([]);http.upload.mockResolvedValue({id:'9007199254740993',url:'/uploads/new.png',name:'new.png',ext:'png'});
 const wrapper=mount(AttachmentField,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,images:true},global:{plugins:[ElementPlus]}});
 await flushPromises();const input=wrapper.find('input[type=file]');expect(input.exists()).toBe(true);
 Object.defineProperty(input.element,'files',{value:[new File(['image'],'new.png',{type:'image/png'})]});await input.trigger('change');await flushPromises();
 expect(http.upload).toHaveBeenCalledWith('/plugins/cms/content/upload',expect.any(FormData));
 const body=http.upload.mock.calls[0][1] as FormData;expect(body.get('site_id')).toBe('7');expect(body.get('bizType')).toBe('image');
 expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['9007199254740993']);wrapper.unmount();
});
it('retains the existing gallery and appends two uploaded files',async()=>{
 http.get.mockResolvedValue([]);http.upload.mockResolvedValueOnce({id:'12',url:'/a.png',name:'a.png',ext:'png'}).mockResolvedValueOnce({id:'13',url:'/b.png',name:'b.png',ext:'png'});
 const wrapper=mount(AttachmentField,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,images:true,multiple:true,modelValue:['11']},global:{plugins:[ElementPlus]}});await flushPromises();
 const input=wrapper.find('input[type=file]');expect(input.exists()).toBe(true);Object.defineProperty(input.element,'files',{value:[new File(['a'],'a.png',{type:'image/png'}),new File(['b'],'b.png',{type:'image/png'})]});await input.trigger('change');await flushPromises();
 expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['11','12','13']]);wrapper.unmount();
});
it('shows upload failures without losing the selected attachment',async()=>{
 http.get.mockResolvedValue([]);http.upload.mockRejectedValue(new Error('上传失败，请重试'));
 const wrapper=mount(AttachmentField,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,modelValue:'11'},global:{plugins:[ElementPlus]}});await flushPromises();
 const input=wrapper.find('input[type=file]');expect(input.exists()).toBe(true);Object.defineProperty(input.element,'files',{value:[new File(['a'],'a.pdf',{type:'application/pdf'})]});await input.trigger('change');await flushPromises();expect(wrapper.text()).toContain('上传失败');expect(wrapper.emitted('update:modelValue')).toBeUndefined();wrapper.unmount();
});
it('does not expose upload or selection without create/edit permission',async()=>{
 const wrapper=mount(AttachmentField,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>false},global:{plugins:[ElementPlus]}});await flushPromises();expect(wrapper.find('input[type=file]').exists()).toBe(false);expect(http.get).not.toHaveBeenCalled();wrapper.unmount();
});
it('ignores an upload completion after the selected site changes',async()=>{
 let finish!: (value:any)=>void;http.get.mockResolvedValue([]);http.upload.mockImplementation(()=>new Promise(resolve=>{finish=resolve;}));
 const wrapper=mount(AttachmentField,{props:{scope:{site_id:'7',locale:'zh-cn'},can:()=>true,modelValue:'11'},global:{plugins:[ElementPlus]}});await flushPromises();
 const input=wrapper.find('input[type=file]');Object.defineProperty(input.element,'files',{value:[new File(['a'],'old.pdf',{type:'application/pdf'})]});await input.trigger('change');await flushPromises();
 await wrapper.setProps({scope:{site_id:'8',locale:'en-us'},modelValue:'22'});finish({id:'12',url:'/old.pdf',name:'old.pdf',ext:'pdf'});await flushPromises();
 expect(wrapper.emitted('update:modelValue')).toBeUndefined();wrapper.unmount();
});
