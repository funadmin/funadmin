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
            <el-select v-model="form.type" class="w-full" filterable>
              <el-option v-for="item in uniqueTypes" :key="item.name" :label="`${item.title} (${item.name})`" :value="item.name" />
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
        <el-input v-model="form.value" type="textarea" :rows="3" placeholder="保持字符串存储；多选值每行一个" />
      </el-form-item>
      <el-form-item label="选项定义" prop="extra">
        <el-input v-model="form.extra" type="textarea" :rows="4" maxlength="255" show-word-limit placeholder="每行 value:显示名称，例如 1:开启" />
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
import { configApi, type ConfigModel, type ConfigOptions, type ConfigPayload } from '@/api/system/config';

const props = withDefaults(defineProps<{ modelValue: boolean; row?: ConfigModel | null; options: ConfigOptions }>(), { row: null });
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'success'): void }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const formRef = ref<FormInstance>();
const saving = ref(false);
const initialForm = (): ConfigPayload => ({ code: '', group: 'site', type: 'text', verify: '', value: '', extra: '', remark: '', status: 1 });
const form = reactive<ConfigPayload>(initialForm());
const uniqueTypes = computed(() => Array.from(new Map(props.options.types.map((item) => [item.name, item])).values()));
const rules: FormRules = {
  code: [{ required: true, message: '请输入配置编码', trigger: 'blur' }, { pattern: /^[A-Za-z][A-Za-z0-9_.-]{0,29}$/, message: '以字母开头，只能包含字母、数字、点、横线和下划线', trigger: 'blur' }],
  group: [{ required: true, message: '请选择配置分组', trigger: 'change' }],
  type: [{ required: true, message: '请选择字段类型', trigger: 'change' }],
  extra: [{ max: 255, message: '最多 255 个字符', trigger: 'blur' }],
  remark: [{ max: 100, message: '最多 100 个字符', trigger: 'blur' }]
};
watch(() => [props.modelValue, props.row] as const, ([opened, row]) => {
  if (!opened) return;
  Object.assign(form, initialForm(), row ? { code: row.code, group: row.group, type: row.type, verify: row.verify === '0' ? '' : row.verify, value: row.value, extra: row.extra, remark: row.remark, status: row.status } : {});
}, { immediate: true });
function resetForm() { formRef.value?.resetFields(); Object.assign(form, initialForm()); }
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
