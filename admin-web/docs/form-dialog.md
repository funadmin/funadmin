# 表单 / 弹窗 / 抽屉 指南

> 目标：让所有「新增 / 编辑 / 权限分配 / 详情查看」类弹层风格统一、行为一致。
> 参考实现：[`UserFormDialog.vue`](../src/views/system/user/components/UserFormDialog.vue)、[`RolePermDrawer.vue`](../src/views/system/role/components/RolePermDrawer.vue)。

---

## 一、何时用弹窗，何时用抽屉

| 场景 | 推荐 | 理由 |
| ---- | ---- | ---- |
| 字段 ≤ 12，表单较短 | `el-dialog` 宽 480~640 | 集中、聚焦、关闭快 |
| 字段较多 / 嵌套树 / 列表 / 富文本 | `el-drawer` 宽 480~720 | 高度无限，长内容滚动友好 |
| 权限 / 树形分配 / 关联多对多 | `el-drawer` | 内含树或穿梭框时空间紧张 |
| 大对象详情查看 | `el-drawer`（右侧） | 不挡住列表，方便对比 |
| 二次确认 / 提示 / 输入单值 | `ElMessageBox.confirm/prompt` | 不要为这种事单独写弹窗 |

---

## 二、标准弹窗模板（v-model 双向）

```vue
<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑XX' : '新增XX'"
    width="560px"
    :close-on-click-modal="false"
    @closed="onClosed"
  >
    <el-form ref="formRef" :model="form" :rules="rules" label-width="90px" class="px-2">
      <!-- 一行两列 / 长字段独占一行 -->
      <el-row :gutter="16">
        <el-col :span="12">
          <el-form-item label="账号" prop="username">
            <el-input v-model="form.username" :disabled="isEdit" placeholder="请输入账号" />
          </el-form-item>
        </el-col>
        <el-col :span="24">
          <el-form-item label="备注" prop="remark">
            <el-input v-model="form.remark" type="textarea" :rows="3" maxlength="200" show-word-limit />
          </el-form-item>
        </el-col>
      </el-row>
    </el-form>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">确定</el-button>
    </template>
  </el-dialog>
</template>
```

**Props / Emits 协议（强约束）**：

```ts
interface Props {
  modelValue: boolean;     // v-model
  row?: BizModel | null;   // null = 新增，否则编辑
}
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];             // 提交成功通知父级刷新
}>();
```

调用方就只写：

```vue
<FooFormDialog v-model="dialogVisible" :row="current" @success="loadData" />
```

---

## 三、`script` 内骨架（最小可用）

```ts
<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import type { FormInstance, FormRules } from 'element-plus';
import { fooApi, type FooModel } from '@/api/system/foo';

interface Props {
  modelValue: boolean;
  row?: FooModel | null;
}
const props = withDefaults(defineProps<Props>(), { row: null });
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  success: [];
}>();

const visible = ref(false);
const isEdit = ref(false);
const saving = ref(false);
const formRef = ref<FormInstance>();

/** 用工厂函数生成初始值，便于 reset */
const initialForm = () => ({
  name: '',
  status: 1 as 0 | 1,
  remark: ''
});
const form = reactive<ReturnType<typeof initialForm>>(initialForm());

const rules: FormRules = {
  name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
  status: [{ required: true, message: '请选择状态', trigger: 'change' }]
};

/** 父级 v-model → 内部 visible（带回填） */
watch(() => props.modelValue, (v) => {
  visible.value = v;
  if (v) initForm();
});
/** 内部关闭 → 通知父级 */
watch(visible, (v) => emit('update:modelValue', v));

function initForm() {
  Object.assign(form, initialForm());
  isEdit.value = !!props.row;
  if (props.row) {
    form.name = props.row.name;
    form.status = props.row.status;
    form.remark = props.row.remark || '';
  }
}

async function onSubmit() {
  await formRef.value?.validate();
  saving.value = true;
  try {
    if (isEdit.value && props.row) {
      await fooApi.update(props.row.id, form);
    } else {
      await fooApi.create(form);
    }
    emit('success');
    visible.value = false;
  } finally {
    saving.value = false;
  }
}

function onClosed() {
  formRef.value?.resetFields();
  Object.assign(form, initialForm());
}
</script>
```

**关键约定**：

1. **`initialForm()` 工厂函数**：避免 `Object.assign(form, {...})` 漏字段；`onClosed` 也用它清空。
2. **`@closed` 而不是 `@close`**：`@closed` 在动画结束后触发，避免 reset 抖动。
3. **`isEdit` 通过 `!!props.row` 判定**，不要新增 `mode` prop。
4. **编辑禁改主键**：`<el-input :disabled="isEdit" />`（账号 / 编码 这类不可变字段）。
5. **新增独有字段**（如密码）：`<el-col v-if="!isEdit">`，编辑时直接不渲染。
6. **`form.password` 提交时剔除**：编辑模式下 `const { password, ...rest } = form; await api.update(id, rest);`。

---

## 四、表单校验（Element Plus 原生）

```ts
const rules: FormRules = {
  username: [{ required: true, message: '请输入账号', trigger: 'blur' }],
  password: [{ required: true, min: 6, message: '至少 6 位', trigger: 'blur' }],
  email:    [{ type: 'email', message: '邮箱格式不正确', trigger: 'blur' }],
  mobile:   [{ pattern: /^1\d{10}$/, message: '手机号格式不正确', trigger: 'blur' }],
  roleIds:  [{ required: true, message: '请选择角色', trigger: 'change' }],
  age: [
    { required: true, message: '请输入年龄', trigger: 'blur' },
    {
      validator: (_r, v, cb) => (v >= 0 && v <= 150 ? cb() : cb(new Error('0 - 150'))),
      trigger: 'blur'
    }
  ]
};
```

要点：

- **必填走 `required: true` + `trigger`**（`blur` for input / `change` for select）。
- **正则用 `pattern`**（手机号、URL、身份证等）。
- **跨字段校验**（如「确认密码 == 密码」）走 `validator`，闭包里读 `form.password`。
- 老板**不要**重复造一遍校验框架，Element Plus 的 `FormRules` 已经够用。

---

## 五、抽屉模板（含树）

参考 [`RolePermDrawer.vue`](../src/views/system/role/components/RolePermDrawer.vue)：

```vue
<template>
  <el-drawer
    v-model="visible"
    :title="`分配权限：${row?.name}`"
    direction="rtl"
    size="480px"
    :close-on-click-modal="false"
    @opened="onOpened"
  >
    <div v-loading="loading" class="px-4">
      <el-checkbox v-model="checkAll" @change="onCheckAll">全选 / 反选</el-checkbox>
      <el-tree
        ref="treeRef"
        :data="treeData"
        show-checkbox
        node-key="id"
        :default-checked-keys="checkedKeys"
        :props="{ label: 'name', children: 'children' }"
      />
    </div>

    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" @click="onSubmit">保存</el-button>
    </template>
  </el-drawer>
</template>
```

要点：

- 抽屉默认 `direction="rtl"` 右侧出现，宽度 ≥ 480。
- 数据加载放 **`@opened`**（动画完成后），避免树过早渲染抖动。
- `el-tree` 的勾选不会自动同步出去，提交前 `treeRef.value!.getCheckedKeys()` + `getHalfCheckedKeys()` 一并提交。

---

## 六、详情 / 只读弹层

只读用 `el-descriptions` + 大尺寸抽屉，**不放表单**：

```vue
<el-drawer v-model="visible" title="详情" size="540px">
  <el-descriptions :column="2" border>
    <el-descriptions-item label="账号">{{ row.username }}</el-descriptions-item>
    <el-descriptions-item label="昵称">{{ row.nickname }}</el-descriptions-item>
    <el-descriptions-item label="状态" :span="2">
      <el-tag :type="row.status === 1 ? 'success' : 'info'">
        {{ row.status === 1 ? '启用' : '禁用' }}
      </el-tag>
    </el-descriptions-item>
  </el-descriptions>
</el-drawer>
```

---

## 七、`ElMessageBox` 常用形态

```ts
// 确认（删除 / 危险动作）
await ElMessageBox.confirm(`确认删除 ${row.name} ?`, '提示', { type: 'warning' });
await api.remove(row.id);

// 输入值（重置密码 / 备注）
const { value } = await ElMessageBox.prompt('请输入新密码', '重置密码', {
  inputType: 'password',
  inputPattern: /.{6,}/,
  inputErrorMessage: '至少 6 位'
});
await api.resetPassword(row.id, value);
```

> **注意**：之前修过一次 `el-button:focus` 不再加深背景，所以 `ElMessageBox` 自动 focus「确定」时主色按钮**不会**变深，体感更稳。详情见 [theme.md §按钮焦点态](./theme.md#四按钮焦点态特别说明)。

---

## 八、风格约束

1. **底部按钮顺序**：左「取消」（默认 / 灰），右「确定」（实色 primary）。
2. **`:close-on-click-modal="false"`** 永远关闭，避免点空白处误丢失编辑内容。
3. **`width` 用具体像素**（480 / 560 / 640 / 720），不要用百分比，避免大屏过宽。
4. **栅格**：`<el-row :gutter="16">` + `<el-col :span="12">`；超长字段（备注 / 描述）用 `:span="24"`。
5. **`label-width`**：90 ~ 100，全局保持一致。
6. **保存按钮**：`:loading="saving"`，提交期间禁用其他动作（关闭按钮也会被 disabled，因为有 mask）。
7. **暗黑模式 / 主色** 自动跟随，不要在弹窗内写硬编码色。
