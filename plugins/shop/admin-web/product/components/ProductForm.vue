<template><el-dialog v-model="visible" title="编辑"><el-form :model="form"><el-form-item label="name" prop="name"><el-input v-model="form.name" /></el-form-item><el-form-item label="price" prop="price"><el-input-number v-model="form.price" class="w-full" /></el-form-item><el-form-item label="status" prop="status"><el-switch v-model="form.status" /></el-form-item></el-form><template #footer><el-button @click="visible=false">取消</el-button><el-button type="primary" @click="submit">保存</el-button></template></el-dialog></template>
<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { productApi, type ProductModel, type ProductModelPayload } from '../api';
const props = defineProps<{ modelValue: boolean; row: ProductModel | null }>();
const emit = defineEmits<{ 'update:modelValue': [boolean]; success: [] }>();
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });
const form = reactive<ProductModelPayload>({});
const optionLists = reactive<Record<string, Array<{ label: string; value: string | number }>>>({  });
watch(() => [props.row, props.modelValue] as const, ([row, open]) => { Object.keys(form).forEach(key => delete form[key as keyof ProductModelPayload]); Object.assign(form, row || {}); }, { immediate: true });
async function submit() { if (props.row) await productApi.update(props.row.id, form); else await productApi.create(form); visible.value = false; emit('success'); }
</script>
