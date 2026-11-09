# CMS Blade 迁移设计

用户已确认：内置主题改用 Blade；保留现有 CMS 数据标签；后台数据库自定义标签片段继续使用受限解释器。

## 方案与边界

复用项目已有 illuminate/view，CMS 使用独立的主题视图工厂和每次渲染创建的数据标签上下文。四套内置主题（default、corporate、industrial、studio）共 96 个文件改为 .blade.php。标准 Blade 的 include/if/foreach 处理布局与循环；$cms->value/$cms->items/$cms->tag 访问原有标签注册表。普通变量自动转义，净化后的 TrustedHtml 实现 Htmlable。模板标识及站点、语言、发布过滤保持一致。

不采用把数据库片段编译为 Blade 的方案：后台标签编辑权限不等价于执行服务器 PHP 的权限。原 TemplateEngine 继续用于数据库片段与旧 .html 自定义主题的兼容。Blade 主题调用数据库标签时只将片段解释结果作为 Htmlable 输出，绝不重新进行 Blade 编译。

## 行为要求

- 页面、导航片段、模板目录列表同时识别 Blade；同名优先 Blade，旧 HTML 自定义主题继续可用。
- includes/extends 只能在当前主题内查找，不使用共享的模板数据状态，连续请求不能串站、串语言。
- 自定义标签的参数校验、调用次数、递归检测、分页状态继续有效。
- 修改后的源包文件同步到 admin/app/cms 镜像；不修改真实站点数据、不执行生产迁移。
- Vue 插值需要 @{{ }} 或 @verbatim；示例和文档同步更新。
- 不提交、不重置工作区，不覆盖其他会话修改。
