import { mount, type VueWrapper } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';
import { describe, expect, it } from 'vitest';
import Drawer from './ProviderSettingsDrawer.vue';
import zhCN from '@/locales/zh-CN';
import type { AiProfile } from '@/api/development/ai';

const mountDrawer = () => mount(Drawer, { props: { modelValue: true }, global: { plugins: [ElementPlus, createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } })], stubs: { ElDrawer: { template: '<section><slot /></section>' } } } });
describe('供应商预设和协议', () => {
  // 官方依据：各厂商 API reference/SDK；DeepSeek 的 WorkBuddy 指南仍明确使用 /v1。
  it.each([
    ['openai', 'https://api.openai.com/v1', 'openai-chat'],
    ['anthropic', 'https://api.anthropic.com/v1', 'anthropic-messages'],
    ['deepseek', 'https://api.deepseek.com/v1', 'openai-chat'],
    ['kimi', 'https://api.moonshot.cn/v1', 'openai-chat'],
    ['zhipu', 'https://open.bigmodel.cn/api/paas/v4', 'openai-chat'],
    ['qwen', 'https://dashscope.aliyuncs.com/compatible-mode/v1', 'openai-chat'],
    ['doubao', 'https://ark.cn-beijing.volces.com/api/v3', 'openai-chat'],
    ['siliconflow', 'https://api.siliconflow.cn/v1', 'openai-chat'],
    ['openrouter', 'https://openrouter.ai/api/v1', 'openai-chat'],
    ['groq', 'https://api.groq.com/openai/v1', 'openai-chat'],
    ['google-gemini', 'https://generativelanguage.googleapis.com/v1beta/openai', 'openai-chat'],
    ['ollama', '', 'openai-chat'],
  ])('%s 官方预设只填连接，不猜测模型能力', async (id, base_url, protocol) => {
    const w = mountDrawer();
    (w.getComponent('[data-testid="provider-preset"]') as VueWrapper).vm.$emit('update:modelValue', id);
    await w.vm.$nextTick();
    await w.get('[data-testid="apply-preset"]').trigger('click');
    await w.get('[data-testid="profile-test"]').trigger('click');
    expect(w.emitted('test')?.[0]?.[0]).toMatchObject({ name: id, base_url, protocol, model: '' });
    w.unmount();
  });
  it('已有档案二次确认、切换预设重置确认，跨目标禁止继承密钥', async () => {
    const w = mountDrawer();
    const profile = { id: 7, name: 'saved', provider: 'custom', protocol: 'openai-chat', base_url: 'https://old.example.com/v1', model: 'm', has_api_key: true, max_output_tokens: 100 } as AiProfile;
    await w.setProps({ profiles: [profile], savedProfile: profile });
    const set = async (id: string, value: unknown) => {
          if (id === 'provider-api-key') await w.get('input[data-testid="provider-api-key"]').setValue(value);
          else (w.getComponent(`[data-testid="${id}"]`) as VueWrapper).vm.$emit('update:modelValue', value);
          await w.vm.$nextTick();
        };
    await set('provider-api-key', 'temporary-secret');
    await set('provider-preset', 'anthropic');
    await w.get('[data-testid="apply-preset"]').trigger('click');
    expect((w.getComponent('[data-testid="provider-protocol"]') as VueWrapper).props()['modelValue' as keyof ReturnType<VueWrapper['props']>]).toBe('openai-chat');
    await set('provider-preset', 'openai');
    await set('provider-preset', 'anthropic');
    await w.get('[data-testid="apply-preset"]').trigger('click');
    expect((w.getComponent('[data-testid="provider-protocol"]') as VueWrapper).props()['modelValue' as keyof ReturnType<VueWrapper['props']>]).toBe('openai-chat');
    await w.get('[data-testid="apply-preset"]').trigger('click');
    expect((w.get('input[data-testid="provider-api-key"]').element as HTMLInputElement).value).toBe('');
    await w.get('form').trigger('submit');
    expect(w.emitted('save')).toBeUndefined();
    expect(w.text()).toContain('连接目标已变更');
    await w.get('[data-testid="profile-test"]').trigger('click');
    expect(w.emitted('test')?.[0]?.[0]).toMatchObject({ protocol: 'anthropic-messages', api_key: '' });
    await set('provider-api-key', 'new-secret');
    await w.get('form').trigger('submit');
    expect(w.emitted('save')?.[0]).toEqual([expect.objectContaining({ protocol: 'anthropic-messages', api_key: 'new-secret' }), 7]);
    await set('provider-protocol', 'openai-responses');
    await set('provider-clear-key', true);
    await w.get('form').trigger('submit');
    expect(w.emitted('save')?.[1]?.[0]).toMatchObject({ protocol: 'openai-responses', api_key: '' });
    w.unmount();
  });
  it('预设选择必须显式应用，协议独立可改且连接测试发送当前协议', async () => {
    const w = mountDrawer();
    const preset = (w.getComponent('[data-testid="provider-preset"]') as VueWrapper);
    preset.vm.$emit('update:modelValue', 'anthropic');
    await w.vm.$nextTick();
    expect((w.getComponent('[data-testid="provider-protocol"]') as VueWrapper).props()['modelValue' as keyof ReturnType<VueWrapper['props']>]).toBe('openai-chat');
    await w.get('[data-testid="apply-preset"]').trigger('click');
    expect((w.getComponent('[data-testid="provider-protocol"]') as VueWrapper).props()['modelValue' as keyof ReturnType<VueWrapper['props']>]).toBe('anthropic-messages');
    (w.getComponent('[data-testid="provider-protocol"]') as VueWrapper).vm.$emit('update:modelValue', 'openai-responses');
    await w.vm.$nextTick();
    await w.get('[data-testid="profile-test"]').trigger('click');
    expect(w.emitted('test')?.[0]?.[0]).toMatchObject({ protocol: 'openai-responses', base_url: 'https://api.anthropic.com/v1', api_key: '' });
    w.unmount();
  });
});
