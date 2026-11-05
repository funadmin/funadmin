# 插件钩子示例

`hookdemo` 和 `hookconsumer` 都不含应用目录、控制器、菜单或路由。后者只有函数；前者同时演示 PHP 函数、前端函数和 Vue 插槽组件。

将所需示例目录复制到 `admin/plugins/`，通过插件中心或现有命令校验、安装、启用。前端文件按插件发布规则进入 `web/src/admin/modules/{code}`，然后运行 `npm run build:webman`；PHP 新代码部署后 reload 工作进程。不要将私有 `hooks/` 文件放进 public。

PHP 普通服务、CLI、队列、模板与插件可以统一调用：

```php
use app\common\plugin\hook\Hook;

$value = Hook::filter('plugin.hookconsumer.decorate', '订单');
```

两个插件启用时结果是 `订单 · hookdemo`；禁用 hookdemo 后返回 `订单`；禁用 hookconsumer 后同样原样返回。处理器内继续调用其他钩子使用 `$context['hooks']`，以继承调用链，避免循环调用失去深度限制。

任意 Vue 页面、普通组件或其他插件可调用：

```ts
import { hooks } from '@/shared/plugins/hooks';

const value = await hooks.filter('plugin.hookconsumer.decorate', '订单');
```

处理器使用 `context.hooks` 调用其他扩展点，使用 `context.onDispose` 注销事件和定时器；宿主页面可通过 context.signal 传入作用域取消信号。

`dashboard.cards` 演示后台插槽。两个 `decorate` 函数同时声明 public、member、admin，可在三类页面上调用，但后台卡片不会出现在公共页。自定义插槽示例：

```vue
<script setup lang="ts">
import { PluginSlot } from '@/shared/plugins/hooks';
</script>
<template>
  <PluginSlot name="dashboard.cards" :context="{ section: 'overview' }" />
</template>
```

没有监听器时 action 无操作、filter 返回原值。action 报错记录后继续；filter 报错会中断当前调用链，调用者应处理异常。没有配置强依赖是本例的特意安排；若缺少目标插件时业务不能工作，在 requires.plugins 声明版本依赖。

上传示例给新建附件记录日志，且向普通上传响应的 extensions.hookdemo 添加字段。upload.completed 包含 created/reused；复用附件不应该重复加水印。upload.result 不允许改动 url、name、size、driver 等宿主核心字段。
