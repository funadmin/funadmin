import { mount, flushPromises } from '@vue/test-utils';
import { afterAll, beforeAll, afterEach, expect, it, vi } from 'vitest';
import RichTextEditor from '../../src/shared/components/RichTextEditor/index.vue';
// jsdom has no layout APIs; ProseMirror uses these only to scroll the selection.
beforeAll(()=>{
 Object.defineProperty(Range.prototype,'getClientRects',{configurable:true,value:()=>[]});
 Object.defineProperty(Range.prototype,'getBoundingClientRect',{configurable:true,value:()=>new DOMRect()});
});
afterAll(()=>{delete (Range.prototype as any).getClientRects;delete (Range.prototype as any).getBoundingClientRect;});
afterEach(()=>vi.restoreAllMocks());
it('renders rich HTML and saves visible formatting as HTML',async()=>{
 const wrapper=mount(RichTextEditor,{props:{modelValue:'<p>正文内容</p>'},attachTo:document.body});await flushPromises();
 expect(wrapper.find('[contenteditable=true]').text()).toBe('正文内容');
 const button=wrapper.find('button[aria-label="粗体"]');expect(button.exists()).toBe(true);
 const area=wrapper.find('[contenteditable=true]');(area.element as HTMLElement).focus();
 await area.trigger('keydown',{key:'a',code:'KeyA',ctrlKey:true});await button.trigger('mousedown');await button.trigger('click');await flushPromises();
 expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain('<strong>正文内容</strong>');wrapper.unmount();
});
it('replaces content when switching to a translation without echoing an update',async()=>{
 const wrapper=mount(RichTextEditor,{props:{modelValue:'<p>中文</p>'}});await flushPromises();await wrapper.setProps({modelValue:'<h2>English</h2>'});await flushPromises();
 expect(wrapper.find('[contenteditable=true]').html()).toContain('<h2>English</h2>');expect(wrapper.emitted('update:modelValue')).toBeUndefined();wrapper.unmount();
});
it('uploads an image and inserts its URL into the body',async()=>{
 const upload=vi.fn().mockResolvedValue({url:'/storage/photo.png',name:'photo.png'});
 const wrapper=mount(RichTextEditor,{props:{modelValue:'<p>图片：</p>',upload},attachTo:document.body});await flushPromises();
 const input=wrapper.find('input[data-upload=image]');expect(input.exists()).toBe(true);
 Object.defineProperty(input.element,'files',{value:[new File(['pic'],'photo.png',{type:'image/png'})]});await input.trigger('change');await flushPromises();
 expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain('src="/storage/photo.png"');wrapper.unmount();
});
it('preserves legacy table/video markup instead of silently stripping it on edit',async()=>{
 const legacy='<h1>旧页面</h1><table><tbody><tr><td>A</td><td>B</td></tr></tbody></table><video controls src="/film.mp4"></video><p>after</p>';
 const wrapper=mount(RichTextEditor,{props:{modelValue:legacy}});await flushPromises();
 const source=wrapper.find('textarea[aria-label="正文 HTML 源码"]');expect(source.exists()).toBe(true);
 expect((source.element as HTMLTextAreaElement).value).toBe(legacy);
 await source.setValue(legacy.replace('after','修改后'));expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toBe(legacy.replace('after','修改后'));
 expect(wrapper.find('[contenteditable=true]').exists()).toBe(false);wrapper.unmount();
});
it('keeps the rich editor available for a newly created empty body',async()=>{
 const wrapper=mount(RichTextEditor,{props:{modelValue:''}});await flushPromises();expect(wrapper.find('[contenteditable=true]').exists()).toBe(true);expect(wrapper.find('textarea').exists()).toBe(false);wrapper.unmount();
});
