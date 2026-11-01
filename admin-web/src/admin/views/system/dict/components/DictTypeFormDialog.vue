<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? t('systemDict.dialogEditType', '编辑字典分类') : t('systemDict.dialogAddType', '新增字典分类')"
    width="520px"
    align-center
    destroy-on-close
    @close="onClose"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item :label="t('systemDict.typeName', '字典名称')" prop="name">
        <el-input v-model="form.name" :placeholder="t('systemDict.typeNamePlaceholder', '如：用户性别')" maxlength="40" show-word-limit />
      </el-form-item>
      <el-form-item :label="t('systemDict.typeCode', '字典编码')" prop="code">
        <el-input
          v-model="form.code"
          :placeholder="t('systemDict.typeCodePlaceholder', '如：sys_user_sex（建议小写下划线）')"
          :disabled="isEdit"
          maxlength="60"
        />
      </el-form-item>
      <el-form-item :label="t('common.status', '状态')" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">{{ t('common.enable', '启用') }}</el-radio>
          <el-radio :value="0">{{ t('common.disable', '禁用') }}</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item :label="t('systemDict.remark', '备注')" prop="remark">
        <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="120" show-word-limit />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('common.cancel', '取消') }}</el-button>
      <el-button type="primary" :loading="submitting" @click="onSubmit">{{ t('common.confirm', '确定') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { dictTypeApi, type DictType } from '@/api/system/dict';

interface Props {
  modelValue: boolean;
  row?: DictType | null;
}

const props = withDefaults(defineProps<Props>(), { row: null });
const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void;
  (e: 'success'): void;
}>();

const { t } = useI18n();
const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
});

const isEdit = computed(() => Boolean(props.row?.id));
const formRef = ref<FormInstance>();
const submitting = ref(false);

const initForm = (): Partial<DictType> => ({
  code: '',
  name: '',
  status: 1,
  remark: ''
});

const form = reactive<Partial<DictType>>(initForm());

const rules: FormRules = {
  name: [{ required: true, message: t('systemDict.typeNameRequired', '请输入字典名称'), trigger: 'blur' }],
  code: [
    { required: true, message: t('systemDict.typeCodeRequired', '请输入字典编码'), trigger: 'blur' },
    { pattern: /^[a-zA-Z][a-zA-Z0-9_]*$/, message: t('systemDict.typeCodePattern', '只能包含字母数字下划线，且以字母开头'), trigger: 'blur' }
  ]
};

watch(visible, (v) => {
  if (!v) return;
  Object.assign(form, initForm());
  if (props.row) Object.assign(form, props.row);
});

async function onSubmit() {
  await formRef.value?.validate();
  submitting.value = true;
  try {
    if (isEdit.value && props.row) {
      await dictTypeApi.update(props.row.id, form);
    } else {
      await dictTypeApi.create(form);
    }
    visible.value = false;
    emit('success');
  } finally {
    submitting.value = false;
  }
}

function onClose() {
  formRef.value?.resetFields();
}
</script>
