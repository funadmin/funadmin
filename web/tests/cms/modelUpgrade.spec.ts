import { expect,it } from 'vitest';
it('preserves compatible fields and exposes removed or changed values before adoption',async()=>{
  const {planModelUpgrade}=await import('../../../admin/plugins/cms/web/src/admin/modules/cms/components/modelUpgrade');
  const plan=planModelUpgrade([{name:'title',label:'Title',type:'text'},{name:'old',label:'Old',type:'text'},{name:'count',label:'Count',type:'text'}],[{name:'title',label:'Title',type:'textarea'},{name:'count',label:'Count',type:'number'},{name:'added',label:'Added',type:'text',default:'new'}],{title:'Keep me',old:'Keep in history',count:'not numeric'});
  expect(plan.data).toEqual({title:'Keep me',count:null,added:'new'});
  expect(plan.removed).toEqual([{name:'old',label:'Old',value:'Keep in history'},{name:'count',label:'Count',value:'not numeric'}]);
});
