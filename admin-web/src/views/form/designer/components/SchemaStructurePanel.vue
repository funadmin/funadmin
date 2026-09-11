<template>
  <el-tabs v-model="activeTab">
    <el-tab-pane label="验证规则" name="validation">
      <div class="flex flex-col gap-3">
        <el-card v-for="(rule, index) in validation" :key="index" shadow="never">
          <template #header>
            <div class="flex items-center justify-between"><span>规则 {{ index + 1 }}</span><el-button link type="danger" @click="removeValidation(index)">删除</el-button></div>
          </template>
          <el-form label-width="72px" size="small">
            <el-form-item label="type"><el-select v-model="rule.type" class="w-full" @change="emitValidation"><el-option v-for="type in VALIDATION_TYPES" :key="type" :label="type" :value="type" /></el-select></el-form-item>
            <el-form-item label="trigger"><el-select v-model="rule.trigger" multiple class="w-full" @change="emitValidation"><el-option v-for="trigger in VALIDATION_TRIGGERS" :key="trigger" :label="trigger" :value="trigger" /></el-select></el-form-item>
            <el-form-item label="message"><el-input v-model="rule.message" @change="emitValidation" /></el-form-item>
            <el-form-item label="value"><el-input :model-value="displayValue(rule.value)" @change="(value) => updateValidationValue(index, value)" /></el-form-item>
            <el-form-item label="condition"><el-input v-model="rule.condition" placeholder="$form.status" @change="emitValidation" /></el-form-item>
            <el-form-item label="severity"><el-radio-group v-model="rule.severity" @change="emitValidation"><el-radio-button value="error">error</el-radio-button><el-radio-button value="warning">warning</el-radio-button></el-radio-group></el-form-item>
            <el-form-item label="bail"><el-switch v-model="rule.bail" @change="emitValidation" /></el-form-item>
          </el-form>
        </el-card>
        <el-button type="primary" plain @click="addValidation">添加验证规则</el-button>
        <RawJson title="验证规则原始 JSON" :value="validation" @apply="applyRaw('validation', $event)" />
      </div>
    </el-tab-pane>

    <el-tab-pane label="联动条件" name="conditions">
      <div class="flex flex-col gap-3">
        <el-alert v-if="cycleFields.length" :title="`检测到循环依赖：${cycleFields.join(' → ')}`" type="warning" :closable="false" show-icon />
        <el-card v-for="(rule, ruleIndex) in conditions" :key="ruleIndex" shadow="never">
          <template #header>
            <div class="flex items-center justify-between"><span>条件组 {{ ruleIndex + 1 }}</span><el-button link type="danger" @click="removeConditionRule(ruleIndex)">删除</el-button></div>
          </template>
          <el-form label-width="76px" size="small">
            <el-form-item label="条件组"><el-radio-group v-model="rule.when.op" @change="emitConditions"><el-radio-button value="and">and</el-radio-button><el-radio-button value="or">or</el-radio-button></el-radio-group></el-form-item>
            <div v-for="(condition, conditionIndex) in rule.when.conditions" :key="conditionIndex" class="mb-2 rounded border p-2">
              <el-input v-model="condition.field" placeholder="字段" class="mb-2" @change="emitConditions" />
              <el-select v-model="condition.op" placeholder="op" class="mb-2 w-full" @change="emitConditions"><el-option v-for="operator in CONDITION_OPERATORS" :key="operator" :label="operator" :value="operator" /></el-select>
              <el-input :model-value="displayValue(condition.value)" placeholder="value" @change="(value) => updateConditionValue(ruleIndex, conditionIndex, value)" />
              <el-button link type="danger" @click="removeComparison(ruleIndex, conditionIndex)">删除条件</el-button>
            </div>
            <el-button link type="primary" @click="addComparison(ruleIndex)">添加条件</el-button>
            <el-divider />
            <el-form-item label="目标动作"><el-select v-model="rule.then.action" class="w-full" @change="emitConditions"><el-option v-for="action in CONDITION_ACTIONS" :key="action" :label="action" :value="action" /></el-select></el-form-item>
            <el-form-item label="目标字段"><el-input v-model="rule.then.target" @change="emitConditions" /></el-form-item>
            <el-form-item v-if="rule.then.action === 'setValue'" label="目标值"><el-input :model-value="displayValue(rule.then.value)" @change="(value) => updateConditionActionValue(ruleIndex, value)" /></el-form-item>
          </el-form>
        </el-card>
        <el-button type="primary" plain @click="addConditionRule">添加联动规则</el-button>
        <RawJson title="联动条件原始 JSON" :value="conditions" @apply="applyRaw('conditions', $event)" />
      </div>
    </el-tab-pane>

    <el-tab-pane label="事件动作" name="events">
      <div class="flex flex-col gap-3">
        <el-card v-for="eventName in eventNames" :key="eventName" shadow="never">
          <template #header><div class="flex items-center justify-between"><span>{{ eventName }}</span><el-button link type="danger" @click="removeEvent(eventName)">删除事件</el-button></div></template>
          <el-card v-for="(action, actionIndex) in events[eventName]" :key="actionIndex" shadow="never" class="mb-2">
            <el-form label-width="76px" size="small">
              <el-form-item label="动作类型"><el-select :model-value="action.type" class="w-full" @update:model-value="(type) => changeActionType(eventName, actionIndex, type)"><el-option v-for="type in ACTION_TYPES" :key="type" :label="type" :value="type" /></el-select></el-form-item>
              <el-form-item v-for="parameter in actionParameters(action.type)" :key="parameter.name" :label="parameter.name">
                <el-switch v-if="parameter.type === 'boolean'" :model-value="Boolean(action[parameter.name])" @update:model-value="(value) => updateBooleanActionParameter(eventName, actionIndex, parameter.name, Boolean(value))" />
                <el-select v-else-if="parameter.type === 'select'" :model-value="String(action[parameter.name] ?? '')" class="w-full" @update:model-value="(value) => updateActionParameter(eventName, actionIndex, parameter.name, value, parameter.type)"><el-option v-for="option in parameter.options" :key="option" :label="option" :value="option" /></el-select>
                <el-input v-else :model-value="displayValue(action[parameter.name])" @change="(value) => updateActionParameter(eventName, actionIndex, parameter.name, value, parameter.type)" />
              </el-form-item>
            </el-form>
            <el-button link type="danger" @click="removeAction(eventName, actionIndex)">删除动作</el-button>
          </el-card>
          <el-button link type="primary" @click="addAction(eventName)">添加动作</el-button>
        </el-card>
        <div class="flex gap-2"><el-select v-model="newEvent" placeholder="已注册事件" class="flex-1"><el-option v-for="eventName in availableEvents" :key="eventName" :label="eventName" :value="eventName" /></el-select><el-button type="primary" plain :disabled="!newEvent" @click="addEvent">添加事件</el-button></div>
        <RawJson title="事件动作原始 JSON" :value="events" @apply="applyRaw('events', $event)" />
      </div>
    </el-tab-pane>

    <el-tab-pane label="字段权限" name="access">
      <el-form label-width="92px" size="small">
        <el-form-item label="读取权限"><el-select v-model="access.read" multiple filterable allow-create default-first-option class="w-full" placeholder="选择或输入权限码" @change="emitAccess"><el-option v-for="option in permissionOptions" :key="option.value" :label="`${option.label} (${option.value})`" :value="option.value" /></el-select></el-form-item>
        <el-form-item label="写入权限"><el-select v-model="access.write" multiple filterable allow-create default-first-option class="w-full" placeholder="选择或输入权限码" @change="emitAccess"><el-option v-for="option in permissionOptions" :key="option.value" :label="`${option.label} (${option.value})`" :value="option.value" /></el-select></el-form-item>
        <el-form-item label="提交策略">
          <el-select v-model="access.include" class="w-full" @change="emitAccess"><el-option label="自动" value="auto" /><el-option label="始终包含" value="always" /><el-option label="始终排除" value="never" /></el-select>
        </el-form-item>
      </el-form>
      <RawJson title="字段权限原始 JSON" :value="access" @apply="applyRaw('access', $event)" />
    </el-tab-pane>

    <el-tab-pane label="数据源" name="dataSource">
      <el-form label-width="92px" size="small">
        <el-form-item label="kind"><el-select v-model="dataSource.kind" class="w-full" @change="changeDataSourceKind"><el-option v-for="kind in DATA_SOURCE_KINDS" :key="kind" :label="kind" :value="kind" /></el-select></el-form-item>
        <el-form-item v-if="dataSource.kind === 'endpoint'" label="endpoint key"><el-select v-model="dataSource.endpoint" filterable allow-create default-first-option class="w-full" placeholder="已注册 endpoint key" @change="emitDataSource"><el-option v-if="dataSource.endpoint" :label="String(dataSource.endpoint)" :value="dataSource.endpoint" /></el-select></el-form-item>
        <el-form-item v-if="dataSource.kind === 'dictionary'" label="字典编码"><el-input :model-value="String(dataSource.dictionary ?? '')" @update:model-value="(value) => updateDataSourceText('dictionary', value)" /></el-form-item>
        <el-form-item v-if="dataSource.kind === 'relation'" label="关联名称"><el-input :model-value="String(dataSource.relation ?? '')" @update:model-value="(value) => updateDataSourceText('relation', value)" /></el-form-item>
        <el-form-item label="参数映射"><KeyValueEditor v-model="dataSource.params" @change="emitDataSource" /></el-form-item>
        <el-form-item label="响应映射">
          <div class="w-full"><el-input v-model="dataSource.response.items" placeholder="items: data.rows" class="mb-1" @change="emitDataSource" /><el-input v-model="dataSource.response.label" placeholder="label: name" class="mb-1" @change="emitDataSource" /><el-input v-model="dataSource.response.value" placeholder="value: id" class="mb-1" @change="emitDataSource" /><el-input v-model="dataSource.response.disabled" placeholder="disabled（可选）" @change="emitDataSource" /></div>
        </el-form-item>
        <el-form-item label="依赖字段"><el-select v-model="dataSource.dependsOn" multiple filterable allow-create default-first-option class="w-full" @change="emitDataSource" /></el-form-item>
        <el-form-item label="搜索"><el-switch v-model="dataSource.searchable" @change="emitDataSource" /></el-form-item>
        <el-form-item label="分页"><el-input-number v-model="dataSource.pagination.pageSize" :min="1" :max="500" class="w-full" @change="emitDataSource" /></el-form-item>
        <el-form-item label="缓存(ms)"><el-input-number v-model="dataSource.cacheTtl" :min="0" class="w-full" @change="emitDataSource" /></el-form-item>
        <el-form-item label="旧值策略"><el-select v-model="dataSource.staleValue" class="w-full" @change="emitDataSource"><el-option label="清空" value="clear" /><el-option label="保留" value="retain" /><el-option label="重新校验" value="revalidate" /></el-select></el-form-item>
        <el-form-item><el-button type="primary" plain :loading="testing" @click="testDataSource">测试请求</el-button></el-form-item>
      </el-form>
      <el-alert v-if="testResult" :title="testResult" :type="testResultType" :closable="false" class="mb-2" />
      <RawJson title="数据源原始 JSON" :value="dataSource" @apply="applyRaw('dataSource', $event)" />
    </el-tab-pane>
  </el-tabs>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, reactive, ref, watch, type PropType } from 'vue';
import { ElButton, ElCollapse, ElCollapseItem, ElInput, ElMessage } from 'element-plus';
import type { FormSchemaNode } from '@/api/form';
import { formDataApi } from '@/api/formData';
import { componentRegistry } from '../../schema/componentRegistry';
import { ACTION_TYPES, type ActionType, type FormAction } from '../../runtime/actionExecutor';
import type { DataSourceKind } from '../../dataSource/dataSourceRegistry';
import {
  ACTION_PARAMETER_SCHEMAS, CONDITION_ACTIONS, CONDITION_OPERATORS, DATA_SOURCE_KINDS,
  VALIDATION_TRIGGERS, VALIDATION_TYPES, createConditionRule, createDataSource, createEventAction,
  createValidationRule, detectConditionCycles, type DesignerConditionRule, type DesignerDataSource,
  type DesignerValidationRule
} from '../structuredEditor';

const props = withDefaults(defineProps<{
  node: FormSchemaNode;
  permissionOptions?: Array<{ label: string; value: string }>;
}>(), { permissionOptions: () => [] });
const emit = defineEmits<{ update: [patch: Partial<FormSchemaNode>] }>();
const activeTab = ref('validation');
const validation = ref<DesignerValidationRule[]>([]);
const conditions = ref<DesignerConditionRule[]>([]);
const events = reactive<Record<string, FormAction[]>>({});
const dataSource = reactive<DesignerDataSource>(createDataSource('static'));
const access = reactive<{ read: string[]; write: string[]; include: 'auto' | 'always' | 'never' }>({ read: [], write: [], include: 'auto' });
const newEvent = ref('');
const testing = ref(false);
const testResult = ref('');
const testResultType = ref<'success' | 'error'>('success');

const clone = <T,>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
const replaceReactive = (target: Record<string, unknown>, value: Record<string, unknown>): void => {
  Object.keys(target).forEach((key) => delete target[key]);
  Object.assign(target, value);
};
const syncDraft = (): void => {
  validation.value = clone((props.node.validation ?? []).map((rule) => ({ ...createValidationRule(), ...rule })) as DesignerValidationRule[]);
  conditions.value = clone((props.node.conditions ?? []).map((rule) => ({ ...createConditionRule(), ...(rule as DesignerConditionRule) })) as DesignerConditionRule[]);
  replaceReactive(events, clone(props.node.events ?? {}) as Record<string, unknown>);
  replaceReactive(dataSource, { ...createDataSource((props.node.dataSource?.kind ?? props.node.dataSource?.mode ?? 'static') as DataSourceKind), ...(clone(props.node.dataSource ?? {}) as object) });
  replaceReactive(access, { read: [], write: [], include: 'auto', ...(clone(props.node.access ?? {}) as object) });
};
watch(() => props.node, syncDraft, { immediate: true, deep: true });

const displayValue = (value: unknown): string => typeof value === 'string' ? value : value == null ? '' : JSON.stringify(value);
const parseValue = (value: string): unknown => {
  const text = value.trim();
  if (!text) return '';
  try { return JSON.parse(text); } catch { return value; }
};
const emitValidation = () => emit('update', { validation: clone(validation.value) });
const addValidation = () => { validation.value.push(createValidationRule()); emitValidation(); };
const removeValidation = (index: number) => { validation.value.splice(index, 1); emitValidation(); };
const updateValidationValue = (index: number, value: string) => { validation.value[index].value = parseValue(value); emitValidation(); };

const emitConditions = () => emit('update', { conditions: clone(conditions.value) });
const addConditionRule = () => { conditions.value.push(createConditionRule()); emitConditions(); };
const removeConditionRule = (index: number) => { conditions.value.splice(index, 1); emitConditions(); };
const addComparison = (index: number) => { conditions.value[index].when.conditions.push({ field: '', op: 'eq', value: '' }); emitConditions(); };
const removeComparison = (ruleIndex: number, conditionIndex: number) => { conditions.value[ruleIndex].when.conditions.splice(conditionIndex, 1); emitConditions(); };
const updateConditionValue = (ruleIndex: number, conditionIndex: number, value: string) => { conditions.value[ruleIndex].when.conditions[conditionIndex].value = parseValue(value); emitConditions(); };
const updateConditionActionValue = (index: number, value: string) => { conditions.value[index].then.value = parseValue(value); emitConditions(); };
const cycleFields = computed(() => detectConditionCycles([{ field: props.node.field ?? props.node.id, conditions: conditions.value }]));

const definition = computed(() => componentRegistry.resolve(props.node.type));
const allowedEvents = computed(() => definition.value?.allowedEvents ?? []);
const eventNames = computed(() => Object.keys(events));
const availableEvents = computed(() => allowedEvents.value.filter((name) => !events[name]));
const emitEvents = () => emit('update', { events: clone(events) });
const addEvent = () => { if (!newEvent.value || !allowedEvents.value.includes(newEvent.value)) return; events[newEvent.value] = []; newEvent.value = ''; emitEvents(); };
const removeEvent = (name: string) => { delete events[name]; emitEvents(); };
const addAction = (name: string) => { events[name].push(createEventAction('setValue')); emitEvents(); };
const removeAction = (name: string, index: number) => { events[name].splice(index, 1); emitEvents(); };
const changeActionType = (name: string, index: number, type: ActionType) => { events[name][index] = createEventAction(type); emitEvents(); };
const actionParameters = (type: unknown) => ACTION_PARAMETER_SCHEMAS[type as ActionType] ?? [];
const updateActionParameter = (eventName: string, index: number, name: string, value: string, type: string) => { events[eventName][index][name] = type === 'json' ? parseValue(value) : value; emitEvents(); };
const updateBooleanActionParameter = (eventName: string, index: number, name: string, value: boolean) => { events[eventName][index][name] = value; emitEvents(); };

const emitAccess = () => emit('update', { access: clone(access) });
const emitDataSource = () => emit('update', { dataSource: clone(dataSource) });
const updateDataSourceText = (name: string, value: string) => { dataSource[name] = value; emitDataSource(); };
const changeDataSourceKind = (kind: DataSourceKind) => { replaceReactive(dataSource, createDataSource(kind)); emitDataSource(); };
const testDataSource = async (): Promise<void> => {
  if (!props.node.field) {
    ElMessage.warning('布局节点不能测试数据源');
    return;
  }
  testing.value = true;
  testResult.value = '';
  try {
    const result = await formDataApi.options('designer-preview', props.node.field, { ...dataSource.params, page: 1, pageSize: dataSource.pagination.pageSize });
    testResult.value = `测试成功，共 ${result.total ?? result.options.length} 条数据`;
    testResultType.value = 'success';
  } catch (error) {
    testResult.value = `测试失败：${error instanceof Error ? error.message : '请求异常'}`;
    testResultType.value = 'error';
  } finally { testing.value = false; }
};

const applyRaw = (key: 'validation' | 'conditions' | 'events' | 'dataSource' | 'access', value: unknown): void => {
  if (key === 'validation' && Array.isArray(value)) validation.value = value as DesignerValidationRule[];
  else if (key === 'conditions' && Array.isArray(value)) conditions.value = value as DesignerConditionRule[];
  else if (key === 'events' && value && typeof value === 'object') replaceReactive(events, value as Record<string, unknown>);
  else if (key === 'dataSource' && value && typeof value === 'object') replaceReactive(dataSource, value as Record<string, unknown>);
  else if (key === 'access' && value && typeof value === 'object') replaceReactive(access, value as Record<string, unknown>);
  emit('update', { [key]: clone(value) });
};

const RawJson = defineComponent({
  props: { title: { type: String, required: true }, value: { type: [Object, Array] as PropType<unknown>, required: true } },
  emits: ['apply'],
  setup(sectionProps, { emit: sectionEmit }) {
    const raw = ref('');
    watch(() => sectionProps.value, (value) => { raw.value = JSON.stringify(value, null, 2); }, { immediate: true, deep: true });
    const apply = () => { try { sectionEmit('apply', JSON.parse(raw.value)); ElMessage.success('原始 JSON 已应用'); } catch { ElMessage.warning('请输入合法 JSON'); } };
    return () => h(ElCollapse, {}, () => h(ElCollapseItem, { title: `原始 JSON（高级）· ${sectionProps.title}`, name: sectionProps.title }, () => [
      h(ElInput, { modelValue: raw.value, type: 'textarea', rows: 8, 'onUpdate:modelValue': (value: string) => { raw.value = value; } }),
      h(ElButton, { type: 'primary', plain: true, class: 'mt-2', onClick: apply }, () => '应用原始 JSON')
    ]));
  }
});

const KeyValueEditor = defineComponent({
  props: { modelValue: { type: Object as PropType<Record<string, unknown>>, required: true } },
  emits: ['update:modelValue', 'change'],
  setup(editorProps, { emit: editorEmit }) {
    const rows = ref<Array<{ key: string; value: string }>>([]);
    watch(() => editorProps.modelValue, (value) => { rows.value = Object.entries(value).map(([key, item]) => ({ key, value: displayValue(item) })); }, { immediate: true, deep: true });
    const commit = () => { editorEmit('update:modelValue', Object.fromEntries(rows.value.filter((row) => row.key).map((row) => [row.key, parseValue(row.value)]))); editorEmit('change'); };
    return () => h('div', { class: 'w-full' }, [
      ...rows.value.map((row, index) => h('div', { class: 'mb-1 flex gap-1' }, [
        h(ElInput, { modelValue: row.key, placeholder: '参数名', 'onUpdate:modelValue': (value: string) => { row.key = value; }, onChange: commit }),
        h(ElInput, { modelValue: row.value, placeholder: '$form.field', 'onUpdate:modelValue': (value: string) => { row.value = value; }, onChange: commit }),
        h(ElButton, { type: 'danger', plain: true, onClick: () => { rows.value.splice(index, 1); commit(); } }, () => '删')
      ])),
      h(ElButton, { link: true, type: 'primary', onClick: () => rows.value.push({ key: '', value: '' }) }, () => '添加映射')
    ]);
  }
});
</script>
