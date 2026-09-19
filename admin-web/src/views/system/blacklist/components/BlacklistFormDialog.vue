<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? t('systemBlacklist.dialogEdit', '编辑黑名单') : t('systemBlacklist.dialogAdd', '新增黑名单')"
    width="520px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item :label="t('systemBlacklist.ipRule', 'IP/规则')" prop="ip">
        <el-input v-model="form.ip" maxlength="50" show-word-limit :placeholder="t('systemBlacklist.ipFormPlaceholder', '请输入 IP、CIDR 或业务规则')" />
      </el-form-item>
      <el-form-item :label="t('systemBlacklist.remark', '备注')" prop="remark">
        <el-input v-model="form.remark" type="textarea" :rows="4" maxlength="200" show-word-limit />
      </el-form-item>
      <el-form-item :label="t('common.status', '状态')" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">{{ t('common.enable', '启用') }}</el-radio>
          <el-radio :value="0">{{ t('systemBlacklist.stopped', '停用') }}</el-radio>
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
import { blacklistApi, type BlacklistModel } from '@/api/system/blacklist';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: BlacklistModel | null }>(), { row: null });
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void;
  (event: 'success'): void;
}>();

const { t } = useI18n();
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});
const formRef = ref<FormInstance>();
const saving = ref(false);
const initialForm = () => ({ ip: '', remark: '', status: 1 as 0 | 1 });
const form = reactive(initialForm());
const rules: FormRules = {
  ip: [
    { required: true, message: t('systemBlacklist.ipRequired', '请输入 IP/规则'), trigger: 'blur' },
    { max: 50, message: t('systemBlacklist.ipMax', '最多 50 个字符'), trigger: 'blur' }
  ],
  remark: [{ max: 200, message: t('systemBlacklist.remarkMax', '最多 200 个字符'), trigger: 'blur' }]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (!opened) return;
    Object.assign(form, initialForm(), row ? { ip: row.ip, remark: row.remark, status: row.status } : {});
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
    if (props.row?.id) {
      await blacklistApi.update(props.row.id, form);
    } else {
      await blacklistApi.create(form);
    }
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
