<template>
  <el-dialog
    v-model="visible"
    :title="row?.id ? '编辑会员' : '新增会员'"
    width="680px"
    :close-on-click-modal="false"
    destroy-on-close
    @closed="resetForm"
  >
    <el-alert
      v-if="!row?.id"
      title="后台新建会员不设置密码，会员需后续通过前台找回或设置密码后才能登录。"
      type="warning"
      :closable="false"
      class="mb-4"
    />
    <el-form ref="formRef" :model="form" :rules="rules" label-width="92px">
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="用户名" prop="username">
            <el-input v-model="form.username" maxlength="80" show-word-limit />
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="手机号" prop="mobile">
            <el-input v-model="form.mobile" maxlength="20" />
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="邮箱" prop="email">
        <el-input v-model="form.email" maxlength="60" placeholder="选填" />
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="会员组" prop="groupIds">
            <el-select v-model="form.groupIds" multiple collapse-tags collapse-tags-tooltip class="w-full" placeholder="请选择会员组">
              <el-option v-for="item in options.groups" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </el-col>
        <el-col :span="12">
          <el-form-item label="会员等级" prop="levelId">
            <el-select v-model="form.levelId" class="w-full" placeholder="请选择会员等级">
              <el-option v-for="item in options.levels" :key="item.id" :label="item.name" :value="item.id" />
            </el-select>
          </el-form-item>
        </el-col>
      </el-row>
      <el-form-item label="会员标签" prop="tagIds">
        <el-select v-model="form.tagIds" multiple collapse-tags collapse-tags-tooltip clearable class="w-full" placeholder="请选择会员标签">
          <el-option v-for="item in options.tags" :key="item.id" :label="item.name" :value="item.id" />
        </el-select>
      </el-form-item>
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="性别" prop="sex">
            <el-radio-group v-model="form.sex">
              <el-radio value="0">保密</el-radio>
              <el-radio value="1">男</el-radio>
              <el-radio value="2">女</el-radio>
            </el-radio-group>
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
      <el-form-item label="头像" prop="avatar">
        <Upload v-model="form.avatar" type="image" biz-type="avatar" :max-size="2" hint="支持常见图片格式，最大 2MB" />
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
import { memberApi, type MemberModel, type MemberOptions, type MemberPayload } from '@/api/system/member';

const props = withDefaults(defineProps<{
  modelValue: boolean;
  row?: MemberModel | null;
  options: MemberOptions;
}>(), { row: null });
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
const initialForm = (): MemberPayload => ({
  username: '', mobile: '', email: '', sex: '0', groupIds: [], tagIds: [], levelId: 0, avatar: '', status: 1
});
const form = reactive<MemberPayload>(initialForm());
const rules: FormRules = {
  username: [
    { required: true, message: '请输入用户名', trigger: 'blur' },
    { min: 2, max: 80, message: '长度为 2 至 80 个字符', trigger: 'blur' }
  ],
  mobile: [
    { required: true, message: '请输入手机号', trigger: 'blur' },
    { pattern: /^[0-9+\- ]{6,20}$/, message: '请输入 6 至 20 位有效手机号', trigger: 'blur' }
  ],
  email: [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  groupIds: [{ type: 'array', required: true, min: 1, message: '请选择会员组', trigger: 'change' }],
  levelId: [{ required: true, type: 'number', min: 1, message: '请选择会员等级', trigger: 'change' }]
};

watch(
  () => [props.modelValue, props.row] as const,
  ([opened, row]) => {
    if (!opened) return;
    Object.assign(form, initialForm());
    if (row) {
      Object.assign(form, {
        username: row.username,
        mobile: row.mobile,
        email: row.email,
        sex: row.sex,
        groupIds: [...row.groupIds],
        tagIds: [...row.tagIds],
        levelId: row.levelId,
        avatar: row.avatar,
        status: row.status
      });
    } else {
      form.groupIds = props.options.groups.length ? [props.options.groups[0].id] : [];
      form.levelId = props.options.levels[0]?.id ?? 0;
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
    if (props.row?.id) await memberApi.update(props.row.id, form);
    else await memberApi.create(form);
    visible.value = false;
    emit('success');
  } finally {
    saving.value = false;
  }
}
</script>
