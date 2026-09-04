<template>
  <el-dialog v-model="visible" :title="row?.id ? '编辑配置定义' : '新增配置定义'" width="680px" destroy-on-close @closed="resetForm">
    <el-form ref="formRef" :model="form" :rules="rules" label-width="96px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="配置编码" prop="code">
            <el-input v-model="form.code" maxlength="30" :disabled="row?.isSystem === 1" placeholder="例如 site_name" />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="配置分组" prop="group">
            <el-select v-model="form.group" class="w-full">
              <el-option v-for="item in options.groups" :key="item.id" :label="`${item.title} (${item.name})`" :value="item.name" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="字段类型" prop="type">
            <el-select
              v-model="form.type"
              class="w-full"
              filterable
              default-first-option
              :empty-values="[null, undefined]"
              placeholder="请选择字段类型"
              popper-class="config-type-select-dropdown"
              @change="onTypeChange"
            >
              <el-option v-for="item in availableTypes" :key="item.name" :label="`${item.title} (${item.name})`" :value="item.name">
                <div class="config-type-option">
                  <span>{{ item.title }}</span>
                  <el-tag size="small" type="info" effect="plain">{{ item.name }}</el-tag>
                </div>
              </el-option>
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="验证规则" prop="verify">
            <el-select v-model="form.verify" class="w-full" clearable filterable>
              <el-option label="无" value="" />
              <el-option v-for="item in options.verifies" :key="item.value" :label="`${item.title} (${item.value})`" :value="item.value" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="默认值" prop="value">
        <el-input v-model="form.value" type="textarea" :rows="3" :placeholder="valuePlaceholder" />
      </el-form-item>
      <el-form-item v-if="selectedType?.requiresOptions" label="选项定义" prop="extra">
        <el-input v-model="form.extra" type="textarea" :rows="4" maxlength="255" show-word-limit placeholder="每行一个选项，例如 1:开启\n0:关闭" />
        <div class="config-form-tip">当前字段类型需要配置可选项，默认值必须使用选项左侧的值。</div>
      </el-form-item>
      <el-form-item label="配置备注" prop="remark">
        <el-input v-model="form.remark" maxlength="100" show-word-limit />
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
import { configApi, type ConfigModel, type ConfigOptions, type ConfigPayload, type ConfigTypeOption } from '@/api/system/config';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: ConfigModel | null; options: ConfigOptions }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const formRef = ref<FormInstance>();
const saving = ref(false);
const fallbackTypes: ConfigTypeOption[] = [
  { name: 'text', title: '单行文本', requiresOptions: false },
  { name: 'textarea', title: '多行文本', requiresOptions: false },
  { name: 'number', title: '整数', requiresOptions: false },
  { name: 'float', title: '浮点数', requiresOptions: false },
  { name: 'decimal', title: '小数', requiresOptions: false },
  { name: 'switch', title: '开关', requiresOptions: false },
  { name: 'radio', title: '单选按钮', requiresOptions: true },
  { name: 'checkbox', title: '复选框', requiresOptions: true },
  { name: 'select', title: '下拉选择', requiresOptions: true },
  { name: 'array', title: '数组', requiresOptions: true },
  { name: 'tags', title: '标签', requiresOptions: false },
  { name: 'datetime', title: '日期时间', requiresOptions: false },
  { name: 'range', title: '日期范围', requiresOptions: false },
  { name: 'color', title: '颜色', requiresOptions: false },
  { name: 'image', title: '单张图片', requiresOptions: false },
  { name: 'images', title: '多张图片', requiresOptions: false },
  { name: 'file', title: '单文件', requiresOptions: false },
  { name: 'files', title: '多文件', requiresOptions: false },
  { name: 'editor', title: '富文本编辑器', requiresOptions: false },
  { name: 'hidden', title: '隐藏域', requiresOptions: false }
];
const initialForm = (): ConfigPayload => ({ code: '', group: 'site', type: 'text', verify: '', value: '', extra: '', remark: '', status: 1 });
const form = reactive<ConfigPayload>(initialForm());
const availableTypes = computed(() => {
  const source = props.options.types.length ? props.options.types : fallbackTypes;
  return Array.from(new Map(source.filter((item) => item.name).map((item) => [item.name, item])).values());
});
const selectedType = computed(() => availableTypes.value.find((item) => item.name === form.type));
const valuePlaceholder = computed(() => {
  if (form.type === 'switch') return '请输入 1（开启）或 0（关闭）';
  if (['checkbox', 'array', 'images', 'files'].includes(form.type)) return '每行填写一个值';
  if (selectedType.value?.requiresOptions) return '请输入选项定义中左侧的值';
  return '配置值以字符串形式存储';
});
const rules: FormRules = {
  code: [{ required: true, message: '请输入配置编码', trigger: 'blur' }, { pattern: /^[A-Za-z][A-Za-z0-9_.-]{0,29}$/, message: '以字母开头，只能包含字母、数字、点、横线和下划线', trigger: 'blur' }],
  group: [{ required: true, message: '请选择配置分组', trigger: 'change' }],
  type: [{ required: true, message: '请选择字段类型', trigger: 'change' }],
  extra: [
    {
      validator: (_rule, value: string, callback) => {
        if (selectedType.value?.requiresOptions && !String(value || '').trim()) callback(new Error('请填写当前字段类型的选项定义'));
        else callback();
      },
      trigger: 'blur'
    },
    { max: 255, message: '最多 255 个字符', trigger: 'blur' }
  ],
  remark: [{ max: 100, message: '最多 100 个字符', trigger: 'blur' }]
};
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (!opened) return;
  Object.assign(form, initialForm(), row ? { code: row.code, group: row.group, type: row.type, verify: row.verify === '0' ? '' : row.verify, value: row.value, extra: row.extra, remark: row.remark, status: row.status } : {});
}, { immediate: true });
function resetForm() { formRef.value?.resetFields(); Object.assign(form, initialForm()); }
function onTypeChange() {
  formRef.value?.clearValidate('type');
  if (!selectedType.value?.requiresOptions) {
    form.extra = '';
    formRef.value?.clearValidate('extra');
  }
  if (form.type === 'switch' && !['0', '1'].includes(form.value)) form.value = '0';
}
async function onSubmit() {
  if (!(await formRef.value?.validate().catch(() => false))) return;
  saving.value = true;
  try {
    if (props.row?.id) await configApi.update(props.row.id, form);
    else await configApi.create(form);
    visible.value = false;
    emit('success');
  } finally { saving.value = false; }
}
</script>

<style scoped>
.config-type-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
}

.config-form-tip {
  margin-top: 6px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

:global(.config-type-select-dropdown .el-select-dropdown__item) {
  height: 38px;
  line-height: 38px;
}
</style>
