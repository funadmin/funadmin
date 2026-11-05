/** el-table 尺寸，对应 Element Plus Table size */
export type DataTableSize = 'small' | 'default' | 'large';

/** 工具栏持久化到 localStorage 的展示状态 */
export interface DataTableDisplayState {
  size: DataTableSize;
  stripe: boolean;
  border: boolean;
  /** 表头是否使用浅色底（关闭则透明） */
  headerBg: boolean;
}

/** 列设置项（用于列显隐 + 排序） */
export interface DataTableColumnOption {
  key: string;
  label: string;
  /** 固定在列表底部，不参与拖拽排序（如操作列） */
  fixed?: boolean;
  /** 不可取消勾选 */
  alwaysVisible?: boolean;
}
