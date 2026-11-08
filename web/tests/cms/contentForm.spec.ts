import { expect, it } from 'vitest';
import { fieldDefaults, validateContentForm, compatibleCategories, contentFingerprint } from '../../../admin/plugins/cms/web/src/admin/plugins/cms/components/contentForm';
const fields=[{name:'enabled',label:'启用',type:'boolean',required:true},{name:'count',label:'数量',type:'number',min:0,max:10},{name:'source',label:'来源',type:'text',default:'本站',max_length:4},{name:'photos',label:'图片',type:'gallery'},{name:'choice',label:'选项',type:'select',options:['A','B']}];
it('initializes typed defaults without overwriting existing zero false or explicit null',()=>{
 expect(fieldDefaults(fields)).toEqual({enabled:false,count:null,source:'本站',photos:[],choice:''});
 expect(fieldDefaults(fields,{count:0,enabled:false,source:null})).toMatchObject({count:0,enabled:false,source:null});
 const first=fieldDefaults(fields);(first.photos as string[]).push('1');expect(fieldDefaults(fields).photos).toEqual([]);
});
it('preserves explicitly declared null defaults instead of inventing typed values',()=>{
 expect(fieldDefaults([{name:'flag',label:'标记',type:'boolean',default:null},{name:'text',label:'文字',type:'text',default:null},{name:'gallery',label:'图库',type:'gallery',default:null}])).toEqual({flag:null,text:null,gallery:null});
});
it('returns field errors for missing fields and invalid types ranges and options',()=>{
 const errors=validateContentForm({title:'',desired_path:'//evil.test',data:{enabled:'false',count:11,source:'过长的内容文本',choice:'C'}},fields);
 expect(errors.title).toBeTruthy();expect(errors.desired_path).toBeTruthy();
 for(const name of ['enabled','count','source','choice'])expect(errors[`data.${name}`]).toBeTruthy();
 expect(validateContentForm({title:'标题',desired_path:'/正常.html',data:{enabled:false,count:0,source:'本站',choice:'A'}},fields)).toEqual({});
});
it('filters category IDs as strings by current model and enabled status',()=>{
 expect(compatibleCategories([{id:'1',model_id:null,status:1},{id:'2',model_id:'9007199254740993',status:1},{id:'3',model_id:'2',status:1},{id:'4',model_id:null,status:0}] as any,'9007199254740993').map(x=>x.id)).toEqual(['1','2']);
});
it('tracks editable values while ignoring revision bookkeeping and object key order',()=>{
 const form={title:'标题',data:{b:0,a:false},id:'1',lock_version:0};
 expect(contentFingerprint(form)).toBe(contentFingerprint({...form,lock_version:2,draft_revision_id:'9',data:{a:false,b:0}}));
 expect(contentFingerprint(form)).not.toBe(contentFingerprint({...form,title:'改过'}));
});
