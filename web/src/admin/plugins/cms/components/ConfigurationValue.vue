<script setup lang="ts">
import { useCmsI18n } from '@/composables/useCmsI18n';
import type { ConfigurationField } from '../types';
import ConfigurationListValue from './ConfigurationListValue.vue';
import AppUpload from '@/components/Upload/index.vue';
const { t } = useCmsI18n();
defineProps<{field:ConfigurationField;modelValue:any;disabled?:boolean}>();
const emit=defineEmits<{ 'update:modelValue':[value:any] }>();
</script>
<template>
 <ConfigurationListValue v-if="field.type==='list'" :field="field" :model-value="modelValue" :disabled="disabled" @update:model-value="emit('update:modelValue',$event)" />
 <el-switch v-else-if="field.type==='boolean'" :model-value="!!modelValue" :disabled="disabled" @update:model-value="emit('update:modelValue',$event)" />
 <el-input-number v-else-if="field.type==='number'" :model-value="modelValue" :disabled="disabled" @update:model-value="emit('update:modelValue',$event??null)" />
 <el-select v-else-if="field.type==='select'" :model-value="modelValue" :disabled="disabled" clearable @update:model-value="emit('update:modelValue',$event??'')"><el-option v-for="option in field.options||[]" :key="option" :label="option" :value="option" /></el-select>
 <div v-else class="configuration-input"><el-input :model-value="modelValue??''" :disabled="disabled" :type="field.type==='textarea'?'textarea':'text'" :rows="field.type==='textarea'?3:undefined" :maxlength="field.type==='textarea'?10000:2000" :placeholder="field.type==='image'?t('cms.labels.imageUrlEGStorageLogoPng'):field.type==='url'?t('cms.labels.contactHtmlOrHttpsExampleCom'):field.type==='color'?'#183e35':undefined" @update:model-value="emit('update:modelValue',$event)" /><AppUpload v-if="field.type==='image'" :model-value="modelValue || ''" type="image" biz-type="image" :disabled="disabled" :hint="t('cms.labels.uploadAnImageOrEnterAPublicImageUrl')" @update:model-value="emit('update:modelValue',$event)" /></div>
</template>
<style scoped>.configuration-input{width:100%}.image-preview{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--el-border-color);border-radius:5px;margin-top:10px;background:var(--el-fill-color-light);padding:8px}.image-preview img{max-height:75px;max-width:200px;object-fit:contain}</style>
