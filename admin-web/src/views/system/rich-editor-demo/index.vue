<template>
  <PageWrapper title="富文本编辑器" subtitle="基于 WangEditor v5，支持图片上传（uploadApi）/ 源码 / 全屏">
    <ElRow :gutter="16">
      <ElCol :span="24">
        <ElCard shadow="never" class="app-rich-demo__card">
          <template #header>
            <div class="app-rich-demo__title">
              <span>基础用法</span>
              <ElTag size="small" type="info">v-model: HTML</ElTag>
              <div class="flex-1" />
              <div class="app-rich-demo__actions">
                <ElButton type="primary" size="small" @click="onLoadSample">
                  <i class="i-ep-document-copy" />
                  加载示例
                </ElButton>
                <ElButton link type="danger" size="small" @click="content = ''">
                  <i class="i-ep-delete" />
                  清空
                </ElButton>
              </div>
            </div>
          </template>

          <RichEditor
            v-model="content"
            :height="380"
            placeholder="老板，开始写点什么吧…"
            biz-type="image"
          />

          <ElDivider content-position="left">实时预览</ElDivider>
          <div class="app-rich-demo__preview" v-html="content || '<span class=\'placeholder\'>(空)</span>'" />

          <ElDivider content-position="left">HTML 源码</ElDivider>
          <pre class="app-rich-demo__pre">{{ content || '(空)' }}</pre>
        </ElCard>
      </ElCol>

      <ElCol :span="24" class="app-rich-demo__col">
        <ElCard shadow="never" class="app-rich-demo__card">
          <template #header>
            <div class="app-rich-demo__title">
              <span>禁用状态</span>
              <ElTag size="small" type="warning">disabled</ElTag>
            </div>
          </template>
          <RichEditor v-model="readonlyContent" disabled :height="220" />
        </ElCard>
      </ElCol>

      <ElCol :span="24" class="app-rich-demo__col">
        <ElCard shadow="never" class="app-rich-demo__card">
          <template #header>
            <div class="app-rich-demo__title">
              <span>使用提示</span>
            </div>
          </template>
          <ul class="app-rich-demo__tips">
            <li>支持 <b>粗体</b> / <i>斜体</i> / <u>下划线</u> / <s>删除线</s>，标题 H1~H3，列表，引用，行内代码</li>
            <li>插入链接：选中文本 → 点击链接按钮 → 填写 URL</li>
            <li>插入图片：点击图片按钮 → 选择本地文件，自动走 <code>uploadApi.upload</code></li>
            <li>外部粘贴由 WangEditor 默认策略处理；插入图片走工具栏「上传图片」</li>
            <li>源码模式可直接编辑 HTML；全屏可按 <kbd>ESC</kbd> 退出</li>
          </ul>
        </ElCard>
      </ElCol>
    </ElRow>
  </PageWrapper>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { ElButton, ElCard, ElCol, ElDivider, ElRow, ElTag } from 'element-plus';
import PageWrapper from '@/components/PageWrapper/index.vue';
import RichEditor from '@/components/RichEditor/index.vue';

defineOptions({ name: 'SystemRichEditorDemo' });

const content = ref<string>('');
const readonlyContent = ref<string>(
  '<h2>这是一段只读内容</h2><p>当 <code>disabled</code> 为 true 时，工具栏与编辑区均不可交互。</p>'
);

function onLoadSample() {
  content.value = `
<h1>富文本编辑器示例</h1>
<p>这是基于 <b>WangEditor v5</b> 的富文本编辑器，与 Element Plus 主题协调。</p>
<h3>支持的能力</h3>
<ul>
  <li>常用文本样式：<b>加粗</b>、<i>斜体</i>、<u>下划线</u>、<s>删除线</s></li>
  <li>有序 / 无序列表</li>
  <li>多级标题与段落</li>
  <li>引用与行内代码</li>
  <li>左 / 中 / 右 三种对齐</li>
  <li>插入链接与图片（图片走通用 upload）</li>
  <li>HTML 源码 / 全屏模式</li>
</ul>
<blockquote>提示：可点击工具栏右侧的 “源码” 按钮直接编辑 HTML。</blockquote>
`.trim();
}
</script>

<style scoped>
.app-rich-demo__col {
  margin-top: 16px;
}
.app-rich-demo__title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.app-rich-demo__actions {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}
.app-rich-demo__pre {
  margin: 0;
  padding: 12px;
  border-radius: 6px;
  background: var(--el-fill-color-lighter);
  color: var(--el-text-color-regular);
  font-size: 12px;
  font-family: 'JetBrains Mono', Consolas, Menlo, monospace;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 240px;
  overflow: auto;
}
.app-rich-demo__preview {
  padding: 12px 14px;
  border: 1px dashed var(--el-border-color-lighter);
  border-radius: 6px;
  min-height: 80px;
  font-size: 14px;
  line-height: 1.7;
  color: var(--el-text-color-primary);
}
.app-rich-demo__preview :deep(.placeholder) {
  color: var(--el-text-color-placeholder);
}
.app-rich-demo__tips {
  margin: 0;
  padding-left: 18px;
  color: var(--el-text-color-regular);
  font-size: 14px;
  line-height: 1.9;
}
.app-rich-demo__tips code {
  padding: 1px 6px;
  background: var(--el-fill-color);
  border-radius: 3px;
  font-family: 'JetBrains Mono', Consolas, monospace;
  font-size: 12px;
  color: var(--el-color-danger);
}
</style>
