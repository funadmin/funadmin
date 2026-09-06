# FunAdmin 表单管理（Form Management）设计文档

日期：2026-09-06　路线：A 运行时通用引擎　分期：M1+M2+M3 已交付（056/057 已 live 登记），M4 后续

## 1. 目标与边界
- 可视化表单管理：拖拽设计器设计表单（字段参数尽量细），可**创建新表**或**采纳已有表**。
- 运行时通用引擎：元数据驱动渲染列表/新增编辑/详情（M3），通用后端 API 按元数据校验与读写真实表（M3）。
- 本期（M1+M2）：元数据两表、表单管理列表、设计器（palette/画布/属性面板、拖拽、undo/redo、字段复制）、建表/采纳双模式、DDL 守卫式迁移预览/应用/登记。

## 2. 数据模型
- `fun_form`：id、key（唯一）、name、table_name、connection（默认 mysql）、source_type（created/adopted）、status、list_config json、form_config json（布局/label 宽/分组）、remark、created_at/updated_at/deleted_at、sort_order。
- `fun_form_field`：id、form_id、field_name、label、type（控件类型）、column_type（完整列类型）、nullable、default_value、comment、unsigned、index_type（none/unique/index）、options_source json（static/dict/relation/remote）、control_props json、validate_rules json、link_rules json、relation_type（none/belongs_to/has_many）、relation_table、relation_label_field、relation_value_field、relation_multiple、relation_on_delete、list_show、list_sort、list_filter、list_formatter、list_width、form_show、form_required、form_group、form_span、form_readonly、sort_order、created_at/updated_at。
- 业务数据：真实表。created 表由守卫式迁移创建并登记 fun_system_migration；adopted 表禁 DDL，仅元数据覆盖。

## 3. 字段参数清单（全量）
列侧：字段名/注释/列类型(枚举+长度精度)/可空/默认值/无符号/索引。
表单侧：控件类型(输入/多行/数字/金额/开关/单选/多选/下拉/多选下拉/日期/时间/日期范围/上传/多图/富文本/颜色/滑块/评分)、placeholder、分组、必填、校验(长度/数值/正则/消息)、选项来源(静态/字典/关联表/远程)、联动(显隐/禁用/选项跟随/计算)、编辑禁改、span。
列表侧：显示/排序/筛选/格式化器(tag/图片/日期/金额/开关)/列宽/溢出提示。
关联：belongs_to(FK+删除规则 RESTRICT/CASCADE/SET NULL)、has_many(子表+FK 列+内嵌编辑)。
高级：props 透传 json。

## 4. 设计器
三栏：左 palette（分组可拖拽）、中画布（sortablejs 拖入/排序/跨组、span 预览、WYSIWYG 真实组件、点选、复制字段）、右属性面板（列/表单/列表三 tab＋高级 props）＋表单级设置。
undo/redo：定义快照栈。保存流：客户端校验→服务端 validate→DDL 变更时迁移预览→确认→应用+登记→元数据落库；乐观锁 updated_at。

## 5. 后端 API（console 应用，属性路由）
- FormDesigner：form 列表/详情/保存(含字段)/删除/状态；validate；infer（采纳表，复用 SchemaInspector/FieldInference）；migration/preview；migration/apply。
- FormData（M3）：/form/data/:key index/detail/create/update/delete/export；belongs_to LEFT JOIN 标签；has_many 子表独立路由 /form/data/:key/sub/:relation；动态 Validate（type→规则注册表）。
- 权限：每表单自动生成 form:<key>:list|detail|create|update|delete|export（source_type='form'）；模块菜单 source_type='form'，component 指向通用页（designer/data 用 query 传 id/key）。

## 6. 前端
路由（菜单驱动）：/form/list（管理）、/form/designer?id=、/form/data?key=（M3）。
通用组件（M3）：SchemaList/SchemaForm/SchemaDetail；注册表：控件/格式化器/联动解释器。
拖拽：sortablejs（已有依赖 ^1.15.7），不引新包。

## 7. 迁移与治理
- 056_form_management.sql：fun_form/fun_form_field＋模块菜单/权限（中文用 HEX 字面量），守卫式可重跑。
- 每个 created 表独立守卫式迁移（057+ 按序），登记 fun_system_migration；adopted 表永不 DDL。
- 已登记迁移不可改；新需求走新编号。

## 8. 测试与验收（M1+M2）
- PHP 契约：定义 validate（必填/类型/索引参数）、DDL 预览守卫式且可重跑、adopted 禁 DDL、乐观锁。
- Vitest：设计器 store（undo/redo/复制/排序纯函数）、definition 序列化契约。
- 门禁：php -l 全量、PHP 测试、vitest、vue-tsc、隔离 MySQL 链 001→056、live 应用+登记、浏览器冒烟（建表单→建表→设计器保存）。

## 9. 风险
- 并行会话活跃：动 migrations/权限数据前核对登记状态与 handoff。
- 设计器复杂度：M1 控件注册表先六控件（输入/多行/数字/开关/下拉/日期），其余 M3/M4 补齐。
