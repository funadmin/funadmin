# 主题配置指南

> 主题相关的所有可调项都在 `useAppStore`（`src/store/modules/app.ts`）；CSS 变量在 `src/styles/index.scss`。
> 这份文档解释「**怎么改主色 / 暗黑 / 预设 / 圆角 / 标签风格**」以及「为什么 `:focus` 不能加深主色按钮」。

---

## 一、核心概念

| 概念 | 在哪 | 说明 |
| ---- | ---- | ---- |
| **主色** | `appStore.primaryColor` | 全站唯一主色（hex），驱动 `--el-color-primary` 及 light-1~9 / dark-2 派生 |
| **预设方案** | `appStore.preset` (`PresetTheme`) | 一键打包「主色 + 明暗 + 菜单风格」 |
| **明暗模式** | `appStore.themeMode` (`'light' \| 'dark' \| 'auto'`) | 跟随 `prefers-color-scheme` 或手动 |
| **菜单主题** | `appStore.menuTheme` (`'dark' \| 'light' \| 'fresh'`) | 侧栏配色，与暗黑模式独立 |
| **布局模式** | `appStore.layoutMode` (`'vertical' \| 'horizontal' \| 'mix' \| 'columns'`) | 侧栏 / 顶栏 / 混合 / 双列 |
| **圆角倍率** | `appStore.radiusScale` (0 ~ 2) | 8px 基准 × scale → `--app-radius-*` |
| **标签页风格** | `appStore.tabStyle` | `default / card / minimal / pill` |
| **页面切换动画** | `appStore.pageTransition` | `fade / fade-slide / slide-left / fade-scale / zoom-in / none` |

所有可调项已 **持久化到 localStorage**（key 见 `CACHE_KEYS.THEME`）。

---

## 二、改主色（最常见）

### 2.1 在代码里改

```ts
import { useAppStore } from '@/store/modules/app';
const appStore = useAppStore();
appStore.setPrimaryColor('#5D87FF'); // 自动调用 applyPrimaryColor()
```

`applyPrimaryColor()` 会做这几件事（务必都做，缺一不可）：

```ts
root.style.setProperty('--el-color-primary', this.primaryColor);
root.style.setProperty('--el-color-primary-rgb', '93, 135, 255'); // 关键
for (i = 1..9) root.style.setProperty(`--el-color-primary-light-${i}`, mix(白));
root.style.setProperty('--el-color-primary-dark-2', mix(黑, 20%));
```

> ⚠️ **必须同步 `--el-color-primary-rgb`**：Element Plus 多处用 `rgba(var(--el-color-primary-rgb), α)` 算阴影/半透明，只改 `--el-color-primary` 会出现「实色一种蓝、半透明另一种蓝」。

### 2.2 在右侧设置面板改

`src/components/Setting/index.vue` 提供颜色选取器 + 推荐色板。用户改完后会自动 `setPrimaryColor`。

### 2.3 默认主色历史迁移

老用户的 localStorage 可能锁着旧默认色（`#2c7be5`、`#165dff`），刷新后还是旧色。`main.ts` 在 `applyTheme()` 之前调了一次：

```ts
appStore.migrateLegacyPrimary();
```

逻辑：**仅当当前主色 == 某个历史默认值时**才替换为新默认 `#5D87FF`，自定义色不受影响。

新增历史默认色（项目改默认主色时）需要把旧值追加到 `LEGACY_DEFAULT_PRIMARIES`。

---

## 三、预设主题方案

7 套预设，定义在 `app.ts`：

| value | label | 主色 | themeMode | menuTheme |
| ----- | ----- | ---- | --------- | --------- |
| `elegant` | 雅致 | `#5D87FF` | light | light |
| `fresh` | 清新 | `#13c2c2` | light | light |
| `classic` | 经典 | `#1677ff` | light | dark |
| `minimal` | 极简 | `#0f172a` | light | light |
| `vibrant` | 活力 | `#8b5cf6` | light | fresh |
| `sunset` | 暖阳 | `#f97316` | light | light |
| `midnight` | 暗夜 | `#6366f1` | dark | dark |

```ts
appStore.setPreset('classic'); // 一次切换主色 + 明暗 + 菜单
```

新增预设：往 `PRESET_THEMES` 数组追加一项即可，类型 `PresetThemeConfig`。

---

## 四、按钮焦点态特别说明

> 这是**项目最容易踩的坑**，单独拎出来。

`ElDialog` / `ElDrawer` / `ElMessageBox` 打开时会**自动 focus 「确定」按钮**，导致 `:focus` 状态生效。

如果 `:focus` 也走 `:hover` / `:active` 那一套 `color-mix(... 88%, #000)` 暗化公式，弹窗一打开「确定」按钮就显得**比主色深一档**，体感像是「主色没设对 / 没跟主题走」。

#### 解决方案（已固化在 `src/styles/index.scss`）

```scss
.el-button--primary:not(.is-plain):not(.is-link):not(.is-text):hover,
.el-button--primary:not(.is-plain):not(.is-link):not(.is-text):active {
  background-color: color-mix(in srgb, var(--el-color-primary) 88%, #000) !important;
  border-color:     color-mix(in srgb, var(--el-color-primary) 88%, #000) !important;
}

/* 注意：:focus 不在上面那条规则里！ */

.el-button--primary:not(.is-plain):not(.is-link):not(.is-text):focus-visible {
  outline: 2px solid color-mix(in srgb, var(--el-color-primary) 45%, transparent);
  outline-offset: 2px;
}
```

| 状态 | 行为 |
| ---- | ---- |
| `:hover` | 暗化 12% |
| `:active` | 暗化 12% |
| `:focus`（鼠标点到 / 弹窗自动 focus） | **不变色**（仍为主色） |
| `:focus-visible`（键盘 Tab 聚焦） | 加 outline ring，背景仍为主色 |

> 改全局按钮样式时**不要把 `:focus` 重新加进去**，否则又会出现「弹窗确定按钮颜色深一档」的反馈。

---

## 五、Plain / 边框按钮统一规则

老板要求：「按钮风格全站统一为 plain primary」。落地约束：

1. **搜索表单的「重置 / 查询」**：硬编码 `plain`（见 [`SearchForm/index.vue`](../src/components/SearchForm/index.vue)），不可改。
2. **业务工具栏的「新增 / 导出 / 批量删」**：写法 `<el-button type="primary" plain>`，不要用实色。
3. **行内操作（编辑 / 删除）**：写法 `<el-button size="small" type="primary" link>` 或 `type="danger" link`，节省横向空间。
4. **危险操作的二次确认**：`ElMessageBox.confirm({ type: 'warning' })` 自带主色「确定」实色按钮，**不需要**额外样式。
5. **plain 按钮的 hover**：`:hover` 仅加深边框颜色 + 浅底，**不**变成实色背景（同样在 `index.scss` 里固化）。

---

## 六、暗黑模式 / 灰度 / 色弱

```ts
appStore.setTheme('dark');     // 强制暗
appStore.setTheme('auto');     // 跟随系统
appStore.setGrayMode(true);    // 缅怀模式（全屏灰阶）
appStore.setWeakMode(true);    // 色弱模式（低饱和度）
```

DOM 层面：

```html
<html class="dark gray-mode weak-mode" data-theme="dark" data-preset="elegant" data-tab-style="default">
```

业务样式优先用 `--app-*` / `--el-*` 变量，**不要写硬编码 `#fff` / `#000`**，否则暗黑模式下会塌掉。

---

## 七、布局 / 标签页 / 圆角 / 动画

```ts
appStore.setLayoutMode('mix');      // vertical | horizontal | mix | columns
appStore.setMenuTheme('fresh');     // dark | light | fresh
appStore.setSidebarWidth(220);      // 160 ~ 320
appStore.setTabStyle('pill');       // default | card | minimal | pill
appStore.setPageTransition('fade-slide');
appStore.setRadiusScale(1.25);      // 圆角放大 25%
```

CSS 端可读：

```scss
.my-card {
  border-radius: var(--app-radius);    // 8 * scale
  width: var(--app-sidebar-w);         // 来自 setSidebarWidth
}
[data-tab-style="pill"] .app-tabs__item { /* 胶囊样式 */ }
html.dark .my-card { /* 暗黑覆盖 */ }
```

---

## 八、水印

```ts
appStore.setWatermark(true);
appStore.setWatermarkText('Admin Console · 内部资料');
```

水印实现：[`src/utils/watermark.ts`](../src/utils/watermark.ts)，DOM observer 防止用户控制台删除。

---

## 九、重置 / 导出

```ts
appStore.resetSettings();   // 一键回到「雅致」预设
```

设置面板的「恢复默认」按钮就是调它。

---

## 十、常见 CSS 变量速查

| 变量 | 用途 |
| ---- | ---- |
| `--el-color-primary` | Element Plus 主色 |
| `--el-color-primary-rgb` | 主色 RGB（拼透明度用） |
| `--el-color-primary-light-1` ~ `-9` | 主色按 10% 步长往白混 |
| `--el-color-primary-dark-2` | 主色 + 20% 黑 |
| `--app-card-bg` | 业务卡片背景（明暗自动） |
| `--app-text` | 主文本色 |
| `--app-text-secondary` | 次文本色 |
| `--app-border` | 通用边框色 |
| `--app-radius-sm/md/lg/xl` | 圆角，跟随 `radiusScale` |
| `--app-sidebar-w` | 侧栏宽度（跟随 `sidebarWidth`） |

写组件样式时**优先用上面的变量**，否则改主题就会出现局部「不跟色」。
