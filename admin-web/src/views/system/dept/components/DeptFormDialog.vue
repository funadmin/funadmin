<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? t('systemDept.dialogEdit') : t('systemDept.dialogAdd')"
    width="560px"
    align-center
    destroy-on-close
    @close="onClose"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
      <el-form-item :label="t('systemDept.parentDept')" prop="parentId">
        <el-tree-select
          v-model="form.parentId"
          :data="parentOptions"
          :props="{ label: 'name', children: 'children' }"
          node-key="id"
          :placeholder="t('systemDept.parentPlaceholder')"
          check-strictly
          clearable
          filterable
          class="w-full"
        />
      </el-form-item>
      <el-form-item :label="t('systemDept.name')" prop="name">
        <el-input v-model="form.name" :placeholder="t('systemDept.namePlaceholder')" maxlength="40" show-word-limit />
      </el-form-item>
      <el-form-item :label="t('systemDept.sort')" prop="sort">
        <el-input-number v-model="form.sort" :min="0" :max="999" controls-position="right" />
      </el-form-item>
      <el-form-item :label="t('systemDept.leader')" prop="leader">
        <el-input v-model="form.leader" :placeholder="t('systemDept.leaderPlaceholder')" />
      </el-form-item>
      <el-form-item :label="t('systemDept.phone')" prop="phone">
        <el-input v-model="form.phone" :placeholder="t('systemDept.phonePlaceholder')" maxlength="20" />
      </el-form-item>
      <el-form-item :label="t('systemDept.email')" prop="email">
        <el-input v-model="form.email" :placeholder="t('systemDept.emailPlaceholder')" maxlength="60" />
      </el-form-item>
      <el-form-item :label="t('systemDept.status')" prop="status">
        <el-radio-group v-model="form.status">
          <el-radio :value="1">{{ t('systemDept.enabled') }}</el-radio>
          <el-radio :value="0">{{ t('systemDept.disabled') }}</el-radio>
        </el-radio-group>
      </el-form-item>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">{{ t('common.cancel') }}</el-button>
      <el-button type="primary" :loading="submitting" @click="onSubmit">{{ t('common.confirm') }}</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormInstance, FormRules } from 'element-plus';

const { t } = useI18n();
import { deptApi, type DeptModel } from '@/api/system/dept';

interface Props {
  modelValue: boolean;
  row?: DeptModel | null;
  /** 父级树（含全树用于选择上级） */
  tree: DeptModel[];
  /** 默认选中的父 id（点击「新增子项」时传入） */
  defaultParentId?: number;
}

const props = withDefaults(defineProps<Props>(), {
  row: null,
  defaultParentId: 0
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

const initForm = (): Partial<DeptModel> => ({
  parentId: 0,
  name: '',
  sort: 0,
  status: 1,
  leader: '',
  phone: '',
  email: ''
});

const form = reactive<Partial<DeptModel>>(initForm());

const rules = computed<FormRules>(() => ({
  name: [{ required: true, message: t('systemDept.namePlaceholder'), trigger: 'blur' }],
  email: [
    {
      type: 'email',
      message: t('systemDept.emailInvalid'),
      trigger: 'blur'
    }
  ]
}));

/** 编辑时禁止把自己 / 自己的子孙挂为父，避免环 */
const parentOptions = computed<DeptModel[]>(() => {
  if (!isEdit.value || !props.row) return props.tree;
  const banned = new Set<number>();
  const walk = (nodes: DeptModel[]) => {
    nodes.forEach((n) => {
      banned.add(n.id);
      if (n.children?.length) walk(n.children);
    });
  };
  walk([props.row]);
  const filter = (nodes: DeptModel[]): DeptModel[] =>
    nodes
      .filter((n) => !banned.has(n.id))
      .map((n) => ({ ...n, children: n.children ? filter(n.children) : undefined }));
  return filter(props.tree);
});

watch(visible, (v) => {
  if (!v) return;
  Object.assign(form, initForm());
  if (props.row) {
    Object.assign(form, props.row);
  } else if (props.defaultParentId) {
    form.parentId = props.defaultParentId;
  }
});

async function onSubmit() {
  await formRef.value?.validate();
  submitting.value = true;
  try {
    if (isEdit.value && props.row) {
      await deptApi.update(props.row.id, form);
    } else {
      await deptApi.create(form);
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
