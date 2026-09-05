<template>
  <el-dialog v-model="visible" :title="row?.id ? '编辑附件分组' : '新增附件分组'" width="500px" destroy-on-close @closed="resetForm">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="92px">
      <el-form-item label="上级分组" prop="parentId">
        <el-tree-select v-model="form.parentId" :data="parentOptions" :props="{ label: 'title', children: 'children' }" node-key="id" check-strictly clearable class="w-full" placeholder="无上级" />
      </el-form-item>
      <el-form-item label="分组名称" prop="title">
        <el-input v-model="form.title" maxlength="100" show-word-limit />
      </el-form-item>
      <el-form-item label="缩略图" prop="thumb">
        <Upload v-model="form.thumb" type="image" biz-type="image" :max-size="5" />
      </el-form-item>
      <el-form-item label="排序" prop="sort">
        <el-input-number v-model="form.sort" :min="0" :max="999999" controls-position="right" class="w-full" />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">启用</el-radio>
          <el-radio :value="0">停用</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import Upload from '@/components/Upload/index.vue';
import { attachmentGroupApi, type AttachmentGroupModel, type AttachmentGroupPayload } from '@/api/system/attachment';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: AttachmentGroupModel | null; parents: AttachmentGroupModel[] }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const formRef = ref<FormInstance>();
const saving = ref(false);
const initialForm = (): AttachmentGroupPayload => ({ parentId: 0, title: '', thumb: '', status: 1, sort: 999 });
const form = reactive(initialForm());
const parentOptions = computed<AttachmentGroupModel[]>(() => [
  { id: 0, parentId: 0, title: '无上级' } as AttachmentGroupModel,
  ...props.parents
]);
const rules: FormRules = {
  title: [{ required: true, message: '请输入分组名称', trigger: 'blur' }, { max: 100, message: '最多 100 个字符', trigger: 'blur' }]
};
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (!opened) return;
  Object.assign(form, initialForm(), row ? { parentId: row.parentId, title: row.title, thumb: row.thumb, status: row.status, sort: row.sort } : {});
}, { immediate: true });
function resetForm() { formRef.value?.resetFields(); Object.assign(form, initialForm()); }
async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await attachmentGroupApi.update(props.row.id, form);
    else await attachmentGroupApi.create(form);
    visible.value = false;
    emit('success');
  } finally { saving.value = false; }
}
</script>
