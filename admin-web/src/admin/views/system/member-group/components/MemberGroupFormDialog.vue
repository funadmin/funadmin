<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? t('systemMemberGroup.dialogEdit', '编辑会员组') : t('systemMemberGroup.dialogAdd', '新增会员组')"
    width="480px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item :label="t('systemMemberGroup.groupName', '会员组名称')" prop="name">
        <el-input v-model="form.name" maxlength="50" show-word-limit :placeholder="t('systemMemberGroup.namePlaceholder', '请输入会员组名称')" />
      </el-form-item>
      <el-form-item :label="t('systemMemberGroup.icon', '图标')" prop="icon">
        <IconSelect v-model="form.icon" />
      </el-form-item>
      <el-form-item :label="t('common.status', '状态')" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">{{ t('common.enable', '启用') }}</el-radio>
          <el-radio :value="0">{{ t('systemMemberGroup.stopped', '停用') }}</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">{{ t('common.cancel', '取消') }}</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">{{ t('common.confirm', '确定') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { memberGroupApi, type MemberGroupModel } from '@/api/system/memberGroup';
import IconSelect from '@/components/IconSelect/index.vue';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: MemberGroupModel | null }>(), { row: null });
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void;
  (event: 'success'): void;
}>();
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});
const formRef = ref<FormInstance>();
const saving = ref(false);
const { t } = useI18n();
const initialForm = () => ({ name: '', icon: '', status: 1 as 0 | 1 });
const form = reactive(initialForm());
const rules: FormRules = {
  name: [
    { required: true, message: t('systemMemberGroup.nameRequired', '请输入会员组名称'), trigger: 'blur' },
    { max: 50, message: t('systemMemberGroup.nameMax', '最多 50 个字符'), trigger: 'blur' }
  ]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (opened) Object.assign(form, initialForm(), row ? { name: row.name, icon: row.icon, status: row.status } : {});
  },
  { immediate: true }
);

function resetForm() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}

async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await memberGroupApi.update(props.row.id, form);
    else await memberGroupApi.create(form);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
