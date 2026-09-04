<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? '编辑会员等级' : '新增会员等级'"
    width="620px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
      <el-form-item label="等级名称" prop="name">
        <el-input v-model="form.name" maxlength="30" show-word-limit placeholder="请输入等级名称" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="等级金额" prop="amount">
            <el-input-number v-model="amountValue" :min="0" :max="99999999.99" :precision="2" :step="100" class="w-full" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="等级折扣" prop="discount">
            <el-input-number v-model="form.discount" :min="0" :max="100" :step="1" class="w-full" />
            <div class="mt-1 text-xs text-[var(--el-text-color-secondary)]">100 表示不打折，90 表示九折</div>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="缩略图" prop="thumb">
        <Upload v-model="form.thumb" type="image" biz-type="image" :max-size="5" hint="支持 jpg/png/gif/webp/bmp，最大 5MB" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="排序" prop="sort">
            <el-input-number v-model="form.sort" :min="0" :max="2147483647" controls-position="right" class="w-full" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="状态" prop="status">
            <el-radio-group v-model="form.status">
              <el-radio :value="1">启用</el-radio>
              <el-radio :value="0">停用</el-radio>
            </el-radio-group>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="等级描述" prop="description">
        <el-input v-model="form.description" type="textarea" :rows="3" maxlength="200" show-word-limit />
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
import { memberLevelApi, type MemberLevelModel, type MemberLevelPayload } from '@/api/system/memberLevel';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: MemberLevelModel | null }>(), { row: null });
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
const initialForm = (): MemberLevelPayload => ({
  name: '', amount: '0.00', discount: 100, thumb: '', status: 1, sort: 0, description: ''
});
const form = reactive<MemberLevelPayload>(initialForm());
const amountValue = computed({
  get: () => Number(form.amount || 0),
  set: (value: number | undefined) => { form.amount = Number(value ?? 0).toFixed(2); }
});
const rules: FormRules = {
  name: [
    { required: true, message: '请输入等级名称', trigger: 'blur' },
    { max: 30, message: '最多 30 个字符', trigger: 'blur' }
  ],
  amount: [{ required: true, message: '请输入等级金额', trigger: 'change' }],
  discount: [{ required: true, message: '请输入等级折扣', trigger: 'change' }],
  description: [{ max: 200, message: '最多 200 个字符', trigger: 'blur' }]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (!opened) return;
    Object.assign(form, initialForm());
    if (row) {
      Object.assign(form, {
        name: row.name,
        amount: row.amount,
        discount: row.discount,
        thumb: row.thumb,
        status: row.status,
        sort: row.sort,
        description: row.description
      });
    }
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
    if (props.row?.id) await memberLevelApi.update(props.row.id, form);
    else await memberLevelApi.create(form);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
