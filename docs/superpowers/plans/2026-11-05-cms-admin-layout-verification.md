# CMS 管理页布局修正

日期：2026-11-05。

## 原因

CMS 根容器在 LayoutRouterView 的纵向 flex 容器中使用 margin:auto，四个方向的自动外边距吸收剩余空间，导致页面居中且宽度收缩。额外的英文标题、大字号和说明文字进一步增加了顶部空白，与现有后台管理页不一致。

## 修改

- 使用宿主 PageWrapper，根节点满宽、外边距为 0、顶部对齐。
- 将站点与语言上下文放入紧凑的页头，去掉英文展示标题和重复说明。
- 内容列表复用 DataTableShell，区分筛选区域和操作栏，保留标准刷新、密度、全屏和显示配置。
- 搜索项使用带标签的后台表单；分页靠右；操作列固定在右侧。
- 保留原有站点/语言选择及内容保存、翻译、发布流程。
- 预览 iframe 使用显式空字符串 sandbox 属性，保持原沙箱限制，并解决已安装源码被类型检查纳入后暴露的布尔属性类型错误。

## 当前环境同步

通过宿主 PluginResourcePublisher 的计划、暂存、应用和完成流程发布前端与静态资源，更新相应资源登记。实际变更为 pages/Index.vue、components/ContentPanel.vue 和 components/TagPanel.vue；没有手工绕过文件归属检查或改写内容数据。

源包与已安装页面逐字节一致；PluginModificationDetector 返回 cms_modified=false，未将页面修正留为未知人工漂移。Vite 已热更新当前用户打开的 CMS 页面，无需重新安装插件。

## 验证

- CMS 前端 4 个文件、9 项交互测试通过；仅更新测试对公共表格组件的插槽 stub，原有保存/翻译竞态回归保留。
- node scripts/check-cms.mjs：宿主和源包类型检查通过。
- node scripts/build-cms.mjs：生产模块编译通过，输出临时目录，未覆盖 public。
- git diff --check：通过。
- 使用本机 Turbo 读取当前实际登录的 CMS 页面并截图：页头紧接主内容区、站点/语言信息紧凑、筛选表单与表格使用完整可用宽度，没有原来的顶部及左右大块空白。

本轮未更改菜单层级、数据库结构、权限规则或内容记录。
