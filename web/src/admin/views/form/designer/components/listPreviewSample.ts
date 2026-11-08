/** 列表预览示例数据：只用于设计器预览，不发请求、不写入。 */
import type { FormListBuiltinAction, FormListButton, FormListButtonLocation } from '../../schema/types';
import { defaultListButtons } from '../../runtime/listButtonHost';

export interface ListPreviewColumn {
  field: string;
  label: string;
  options?: Array<{ label: string; value: unknown }>;
  numeric?: boolean;
}

/** 生成固定条数的示例行；有静态选项的字段按行轮换选项值，使显示/禁用条件在不同行上有不同结果。 */
export function listPreviewRows(columns: readonly ListPreviewColumn[], count = 3): Record<string, unknown>[] {
  return Array.from({ length: count }, (_, index) => {
    const row: Record<string, unknown> = { id: index + 1 };
    for (const column of columns) {
      if (column.field === 'id') continue;
      if (column.options?.length) row[column.field] = column.options[index % column.options.length].value;
      else row[column.field] = column.numeric ? (index + 1) * 100 : `${column.label}${index + 1}`;
    }
    return row;
  });
}

export function listPreviewCell(column: ListPreviewColumn, value: unknown): string {
  const option = column.options?.find(item => item.value === value);
  return option ? option.label : value === undefined || value === null ? '' : String(value);
}

/** 预览中各位置的生效按钮：已配置则原样使用；继承时取宿主默认按钮，并只保留该位置实际可用的内置动作（分类按钮取决于分类树的节点操作开关）。 */
export function effectiveListButtons(locations: ReadonlyArray<{ key: FormListButtonLocation; builtinKeys: readonly FormListBuiltinAction[] }>, buttons: Partial<Record<FormListButtonLocation, FormListButton[]>> | undefined): Partial<Record<FormListButtonLocation, FormListButton[]>> {
  return Object.fromEntries(locations.map(location => [location.key, buttons?.[location.key] ?? defaultListButtons(location.key).filter(button => button.action.type !== 'builtin' || location.builtinKeys.includes(button.action.key))]));
}

export interface ListPreviewNode { label: string; depth: number; values?: Record<string, unknown> }
type Translate = (key: string, fallback: string) => string;
/** 示例分类节点：nested 为层级树（有父级字段），否则为平铺列表。 */
export function listPreviewNodes(t: Translate, nested = true): ListPreviewNode[] {
  const first = { label: t('formDesigner.buttonsWorkspace.sampleNode', '示例分类 1'), depth: 0 };
  const second = { label: t('formDesigner.buttonsWorkspace.sampleNode2', '示例分类 2'), depth: 0 };
  return nested
    ? [first, { label: t('formDesigner.buttonsWorkspace.sampleChild', '子分类 1-1'), depth: 1 }, second]
    : [first, second, { label: t('formDesigner.buttonsWorkspace.sampleNode3', '示例分类 3'), depth: 0 }];
}
