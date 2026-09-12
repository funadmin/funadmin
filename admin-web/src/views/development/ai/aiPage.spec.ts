import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const root = resolve(process.cwd(), 'src/views/development/ai');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');

describe('AI Development page contract', () => {
  it('桌面端包含三栏并在窄屏使用 tabs 和 drawer', () => {
    const page = read('index.vue');
    for (const marker of ['ai-conversations-pane', 'ai-workspace-pane', 'ai-context-pane', 'ElTabs', 'ElDrawer']) {
      expect(page).toContain(marker);
    }
    expect(page).toMatch(/@media\s*\(max-width:\s*1024px\)/);
  });

  it('接入全部阶段五组件、停止动作和设置入口', () => {
    const page = read('index.vue');
    for (const component of ['ConversationList', 'MessageTimeline', 'ApprovalCard', 'ApprovalModeSelector', 'ToolCallTimeline', 'ChangeSetDrawer', 'ProviderSettingsDrawer']) {
      expect(page).toContain(component);
    }
    expect(page).toContain('cancelActiveTask');
    expect(page).toContain('openProviderSettings');
  });

  it('中英文语言包包含菜单与阶段五核心文案', () => {
    expect(zhCN.menu.AiDevelopment).toBe('AI 开发助手');
    expect(enUS.menu.AiDevelopment).toBe('AI Development Assistant');
    expect(zhCN.aiDevelopment.approvalModes.fullAccess).toBeTruthy();
    expect(enUS.aiDevelopment.changeSet.confirmApply).toBeTruthy();
  });

  it('组件模板不使用 v-html 且 API key 不进入浏览器存储', () => {
    const files = ['index.vue', 'components/MessageTimeline.vue', 'components/ProviderSettingsDrawer.vue'];
    for (const file of files) {
      const source = read(file);
      expect(source).not.toContain('v-html');
      expect(source).not.toContain('localStorage');
      expect(source).not.toMatch(/sessionStorage.*api[_-]?key/i);
    }
  });
});
