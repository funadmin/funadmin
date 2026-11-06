<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { preservesHtml } from './preserveHtml';
const { t } = useCmsI18n();
const props=withDefaults(defineProps<{modelValue?:string;disabled?:boolean;upload?:(file:File,images:boolean)=>Promise<{url:string;name:string}>}>(),{modelValue:'',disabled:false});
const emit=defineEmits<{'update:modelValue':[value:string];busy:[value:boolean]}>();
const error=ref(''),uploading=ref(false),imageInput=ref<HTMLInputElement>(),fileInput=ref<HTMLInputElement>();
const linkOpen=ref(false),linkUrl=ref(''),sourceMode=ref(false),requiresSource=ref(false);
function assessHtml(original:string,projected:string){requiresSource.value=!preservesHtml(original,projected);if(requiresSource.value)sourceMode.value=true;}
const editor=useEditor({
 extensions:[StarterKit.configure({heading:{levels:[1,2,3,4,5,6]},link:{openOnClick:false},trailingNode:false}),Image.configure({inline:false,allowBase64:false})],
 content:props.modelValue,editable:!props.disabled,
 editorProps:{attributes:{'aria-label':t('cms.editor.bodyEditor'),role:'textbox','aria-multiline':'true'}},
 onCreate:({editor})=>assessHtml(props.modelValue,editor.getHTML()),
 onUpdate:({editor})=>emit('update:modelValue',editor.isEmpty?'':editor.getHTML()),
});
watch(()=>t('cms.editor.bodyEditor'),label=>editor.value?.setOptions({editorProps:{attributes:{'aria-label':label,role:'textbox','aria-multiline':'true'}}}));
const locked=computed(()=>props.disabled||uploading.value);
watch(()=>props.modelValue,value=>{if(editor.value&&editor.value.getHTML()!==value){editor.value.commands.setContent(value||'',{emitUpdate:false});assessHtml(value||'',editor.value.getHTML());}});
watch(()=>props.disabled,value=>editor.value?.setEditable(!value));
function safeUrl(value:string){return /^(?:\/(?!\/)|https?:\/\/|mailto:|#)/i.test(value)&&!/[\x00-\x20\\]/.test(value);}
function openLink(){if(!editor.value||locked.value)return;linkUrl.value=editor.value.getAttributes('link').href||'';linkOpen.value=true;}
function applyLink(){if(!editor.value||locked.value)return;const url=linkUrl.value.trim();if(url&&!safeUrl(url)){error.value=t('cms.editor.linksMustUseHttpSEmailASitePath');return;}const chain=editor.value.chain().focus().extendMarkRange('link');if(url)chain.setLink({href:url}).run();else chain.unsetLink().run();linkOpen.value=false;error.value='';}
async function uploadFile(event:Event,images:boolean){
 const input=event.target as HTMLInputElement,file=input.files?.[0];input.value='';
 if(!file||!props.upload||!editor.value||locked.value)return;
 const target=editor.value;uploading.value=true;emit('busy',true);error.value='';
 try{const result=await props.upload(file,images);if(target.isDestroyed||target!==editor.value||props.disabled)return;
 if(!safeUrl(result.url)||/^mailto:|^#/.test(result.url)&&images)throw new Error(t('cms.editor.invalidUploadUrl'));
 if(images)target.chain().focus().setImage({src:result.url,alt:result.name}).run();
 else target.chain().focus().insertContent([{type:'text',text:result.name,marks:[{type:'link',attrs:{href:result.url}}]},{type:'text',text:' '}]).run();
 }catch(e:any){if(!target.isDestroyed)error.value=e?.msg||e?.message||t('cms.editor.uploadFailedPleaseRetry');}
 finally{uploading.value=false;if(!target.isDestroyed)emit('busy',false);}
}
onBeforeUnmount(()=>emit('busy',false));
</script>
<template><div class="rich-editor" :class="{'is-disabled':disabled}">
 <div class="editor-modes"><button type="button" :disabled="requiresSource||locked" :class="{active:!sourceMode}" @click="sourceMode=false">{{ t('cms.editor.visualEditor') }}</button><button type="button" :disabled="locked" :class="{active:sourceMode}" @click="sourceMode=true">{{ t('cms.editor.htmlSource') }}</button></div>
 <div v-if="requiresSource" class="editor-notice legacy-notice" role="status">{{ t('cms.editor.thisBodyContainsHtmlThatTheVisualEditorCannot') }}</div>
 <div v-if="!sourceMode" class="editor-toolbar" role="toolbar" :aria-label="t('cms.editor.bodyFormattingTools')" @mousedown.prevent>
  <button type="button" :aria-label="t('cms.editor.body')" :disabled="locked" :class="{active:editor?.isActive('paragraph')}" @click="editor?.chain().focus().setParagraph().run()">{{ t('cms.editor.body') }}</button>
  <button type="button" :aria-label="t('cms.editor.heading2')" :disabled="locked" :class="{active:editor?.isActive('heading',{level:2})}" @click="editor?.chain().focus().toggleHeading({level:2}).run()">H2</button>
  <button type="button" :aria-label="t('cms.editor.heading3')" :disabled="locked" :class="{active:editor?.isActive('heading',{level:3})}" @click="editor?.chain().focus().toggleHeading({level:3}).run()">H3</button>
  <button type="button" :aria-label="t('cms.editor.bold')" :title="t('cms.editor.boldCtrlB')" :disabled="locked" :class="{active:editor?.isActive('bold')}" @click="editor?.chain().focus().toggleBold().run()"><b>B</b></button>
  <button type="button" :aria-label="t('cms.editor.italic')" :disabled="locked" :class="{active:editor?.isActive('italic')}" @click="editor?.chain().focus().toggleItalic().run()"><i>I</i></button>
  <button type="button" :aria-label="t('cms.editor.underline')" :disabled="locked" :class="{active:editor?.isActive('underline')}" @click="editor?.chain().focus().toggleUnderline().run()"><u>U</u></button>
  <button type="button" :aria-label="t('cms.editor.strikethrough')" :disabled="locked" @click="editor?.chain().focus().toggleStrike().run()"><s>S</s></button>
  <button type="button" :aria-label="t('cms.editor.bulletList')" :disabled="locked" @click="editor?.chain().focus().toggleBulletList().run()">{{ t('cms.editor.list') }}</button>
  <button type="button" :aria-label="t('cms.editor.orderedList')" :disabled="locked" @click="editor?.chain().focus().toggleOrderedList().run()">{{ t('cms.editor.label1List') }}</button>
  <button type="button" :aria-label="t('cms.editor.blockquote')" :disabled="locked" @click="editor?.chain().focus().toggleBlockquote().run()">{{ t('cms.editor.blockquote') }}</button>
  <button type="button" :aria-label="t('cms.editor.insertLink')" :disabled="locked" @click="openLink">{{ t('cms.editor.link') }}</button>
  <button v-if="upload" type="button" :aria-label="t('cms.editor.uploadImage')" :disabled="locked" @click="imageInput?.click()">{{ t('cms.editor.image') }}</button>
  <button v-if="upload" type="button" :aria-label="t('cms.editor.uploadAttachment')" :disabled="locked" @click="fileInput?.click()">{{ t('cms.editor.attachment') }}</button>
  <button type="button" :aria-label="t('cms.editor.undo')" :disabled="locked||!editor?.can().undo()" @click="editor?.chain().focus().undo().run()">{{ t('cms.editor.undo') }}</button>
  <button type="button" :aria-label="t('cms.editor.redo')" :disabled="locked||!editor?.can().redo()" @click="editor?.chain().focus().redo().run()">{{ t('cms.editor.redo') }}</button>
 </div>
 <textarea v-if="sourceMode" class="source-input" :aria-label="t('cms.editor.bodyHtmlSource')" :value="modelValue" :disabled="disabled" @input="emit('update:modelValue',($event.target as HTMLTextAreaElement).value)" />
 <EditorContent v-else :editor="editor" />
 <input v-if="upload" ref="imageInput" data-upload="image" type="file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp" hidden @change="uploadFile($event,true)" />
 <input v-if="upload" ref="fileInput" data-upload="file" type="file" hidden @change="uploadFile($event,false)" />
 <div v-if="uploading" class="editor-notice" role="status">{{ t('cms.editor.uploadingPleaseWait') }}</div><div v-if="error" class="editor-error" role="alert">{{ error }}</div>
 <el-dialog v-model="linkOpen" :title="t('cms.editor.setLink')" width="min(500px,92vw)" append-to-body><el-input v-model="linkUrl" :aria-label="t('cms.editor.linkUrl')" :placeholder="t('cms.editor.httpsOrSitePath')" @keyup.enter="applyLink" /><small>{{ t('cms.editor.leaveBlankToRemoveTheCurrentLink') }}</small><template #footer><el-button @click="linkOpen=false">{{ t('cms.editor.cancel') }}</el-button><el-button type="primary" @click="applyLink">{{ t('cms.editor.confirm') }}</el-button></template></el-dialog>
</div></template>
<style scoped>.rich-editor{width:100%;border:1px solid var(--el-border-color);border-radius:6px;overflow:hidden;background:var(--el-bg-color)}.editor-modes{display:flex;gap:8px;padding:8px;border-bottom:1px solid var(--el-border-color)}.editor-modes button{border:0;background:transparent;padding:4px 8px;color:var(--el-text-color-secondary);cursor:pointer}.editor-modes button.active{color:var(--el-color-primary);font-weight:600}.editor-modes button:disabled{opacity:.5;cursor:default}.source-input{display:block;box-sizing:border-box;width:100%;min-height:320px;resize:vertical;border:0;outline:none;padding:16px;font:13px/1.7 monospace;color:var(--el-text-color-primary);background:var(--el-bg-color)}.legacy-notice{background:var(--el-color-warning-light-9);color:var(--el-color-warning-dark-2)}.editor-toolbar{display:flex;gap:4px;flex-wrap:wrap;padding:8px;border-bottom:1px solid var(--el-border-color);background:var(--el-fill-color-light)}.editor-toolbar button{border:0;border-radius:4px;background:transparent;color:var(--el-text-color-primary);padding:6px 8px;cursor:pointer;line-height:1.3;font:inherit;font-size:13px}.editor-toolbar button:hover,.editor-toolbar button.active{background:var(--el-color-primary-light-9);color:var(--el-color-primary)}.editor-toolbar button:disabled{opacity:.4;cursor:default}.rich-editor :deep(.tiptap){min-height:320px;max-height:60vh;overflow-y:auto;padding:16px;outline:none;line-height:1.8;overflow-wrap:anywhere}.rich-editor :deep(.tiptap img){max-width:100%;height:auto}.rich-editor :deep(.tiptap p){margin:0 0 12px}.rich-editor :deep(.tiptap ul),.rich-editor :deep(.tiptap ol){padding-left:1.7em;list-style:revert}.rich-editor :deep(.tiptap blockquote){border-left:3px solid var(--el-border-color);padding-left:14px;margin:12px 0;color:var(--el-text-color-secondary)}.rich-editor :deep(.tiptap h2){font-size:1.6em;font-weight:600}.rich-editor :deep(.tiptap h3){font-size:1.3em;font-weight:600}.rich-editor :deep(.tiptap a){color:var(--el-color-primary);text-decoration:underline}.editor-notice,.editor-error{padding:8px 16px;font-size:12px}.editor-error{color:var(--el-color-danger)}.is-disabled{opacity:.7}</style>
