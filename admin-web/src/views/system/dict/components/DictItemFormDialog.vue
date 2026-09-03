<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑字典项' : '新增字典项'"
    width="540px"
    align-center
    destroy-on-close
    @close="onClose"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item label="所属分类" prop="typeCode">
        <el-input :model-value="typeName" disabled placeholder="请先在左侧选择分类" />
      </el-form-item>
      <el-form-item label="字典标签" prop="label">
        <el-input v-model="form.label" placeholder="如：男" maxlength="40" show-word-limit />
      </el-form-item>
      <el-form-item label="字典键值" prop="value">
        <el-input v-model="form.value" placeholder="如：1" maxlength="60" />
      </el-form-item>
      <el-form-item label="样式属性" prop="cssClass">
        <el-select v-model="form.cssClass" placeholder="用于 Tag 颜色（可选）" clearable class="!w-full">
          <el-option label="primary" value="primary" />
          <el-option label="success" value="success" />
          <el-option label="warning" value="warning" />
          <el-option label="danger" value="danger" />
          <el-option label="info" value="info" />
        </el-select>
      </el-form-item>
      <el-form-item label="排序" prop="sort">
        <el-input-number v-model="form.sort" :min="0" :max="999" controls-position="right" />
      </el-form-item>
      <el-form-item label="状态" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">启用</el-radio>
          <el-radio :value="0">禁用</el-radio>
        </el-radio-group>
      </el-form-item>
      <el-form-item label="备注" prop="remark">
        <el-input v-model="form.remark" type="textarea" :rows="2" maxlength="120" show-word-limit />
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="submitting" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { dictItemApi, type DictItemModel } from '@/api/system/dict';

interface Props {
  modelValue: boolean;
  row?: DictItemModel | null;
  /** 当前选中的字典分类 code（新增时必填） */
  typeCode: string;
  /** 当前选中的字典分类名称（仅展示） */
  typeName?: string;
}

const props = withDefaults(defineProps<Props>(), {
  row: null,
  typeName: ''
});

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void;
  (e: 'success'): void;
}>();

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v)
});

const isEdit = computed(() => Boolean(props.row?.id));
const formRef = ref<FormInstance>();
const submitting = ref(false);

const initForm = (): Partial<DictItemModel> => ({
  typeCode: '',
  label: '',
  value: '',
  sort: 0,
  status: 1,
  cssClass: '',
  remark: ''
});

const form = reactive<Partial<DictItemModel>>(initForm());

const rules: FormRules = {
  label: [{ required: true, message: '请输入字典标签', trigger: 'blur' }],
  value: [{ required: true, message: '请输入字典键值', trigger: 'blur' }]
};

watch(visible, (v) => {
  if (!v) return;
  Object.assign(form, initForm());
  if (props.row) {
    Object.assign(form, props.row);
  } else {
    form.typeCode = props.typeCode;
  }
});

async function onSubmit() {
  if (!form.typeCode) {
    form.typeCode = props.typeCode;
  }
  await formRef.value?.validate();
  submitting.value = true;
  try {
    if (isEdit.value && props.row) {
      await dictItemApi.update(props.row.id, form);
    } else {
      await dictItemApi.create(form);
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
