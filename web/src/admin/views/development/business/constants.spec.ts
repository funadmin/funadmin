import { describe, expect, it } from 'vitest';
import enUS from '@/locales/en-US';
import zhCN from '@/locales/zh-CN';
import {
  FILE_STATUS_META,
  GENERATION_STATUS_META,
  LIFECYCLE_STATUS_META,
  RECOVERY_STATUS_META,
  deriveAdoptedBusiness,
  normalizeBusinessCode,
  normalizeGenerationStatus,
  type BusinessStatusMeta
} from './constants';

const STATUS_GROUPS = {
  lifecycle: LIFECYCLE_STATUS_META,
  generation: GENERATION_STATUS_META,
  recovery: RECOVERY_STATUS_META,
  file: FILE_STATUS_META
} as const;

const EXPECTED_STATUSES = {
  lifecycle: ['draft', 'published', 'dynamic_published', 'disabled'],
  generation: ['idle', 'planned', 'running', 'completed', 'failed', 'conflict', 'superseded'],
  recovery: ['none', 'recovering', 'rolled_back', 'recovered_completed', 'recovery_required'],
  file: ['create', 'update', 'auto-merged', 'delete', 'keep-local', 'conflict', 'binary-conflict', 'conflict-no-base']
} as const;

describe('业务开发状态元数据', () => {
  it.each(Object.entries(EXPECTED_STATUSES))('%s 状态具有完整 label、tone 和 description', (group, statuses) => {
    const metadata = STATUS_GROUPS[group as keyof typeof STATUS_GROUPS] as Record<string, BusinessStatusMeta>;
    expect(Object.keys(metadata)).toEqual(expect.arrayContaining([...statuses]));
    statuses.forEach((status) => {
      const item = metadata[status];
      expect(item).toEqual({
        labelKey: `business.status.${group}.${status}.label`,
        tone: expect.stringMatching(/^(success|warning|danger|info|primary)$/),
        descriptionKey: `business.status.${group}.${status}.description`
      });
    });
  });

  it('将历史 generated 状态统一映射为 completed', () => {
    expect(normalizeGenerationStatus('generated')).toBe('completed');
    expect(normalizeGenerationStatus('running')).toBe('running');
  });

  it.each(['zh-CN', 'en-US'])('%s 为全部状态提供非空文案', (locale) => {
    const messages = locale === 'zh-CN' ? zhCN : enUS;
    Object.entries(STATUS_GROUPS).forEach(([group, metadata]) => {
      Object.entries(metadata).forEach(([status, item]) => {
        const translated = messages.business.status[group as keyof typeof messages.business.status] as Record<string, { label: string; description: string }>;
        expect(translated[status]?.label, `${locale} ${item.labelKey}`).toBeTruthy();
        expect(translated[status]?.description, `${locale} ${item.descriptionKey}`).toBeTruthy();
      });
    });
  });
});

describe('数据库采纳的业务默认值', () => {
  it('业务标识去掉 fun_ 前缀并规范为小写标识；名称优先取表描述，否则取标识', () => {
    expect(deriveAdoptedBusiness('fun_Shop_Order', '订单')).toEqual({ code: 'shop_order', name: '订单' });
    expect(deriveAdoptedBusiness('fun_shop-goods')).toEqual({ code: 'shop_goods', name: 'shop_goods' });
    expect(deriveAdoptedBusiness('crm_contact', '  ')).toEqual({ code: 'crm_contact', name: 'crm_contact' });
  });

  it('normalizeBusinessCode 去空白、转小写、非法字符替换为下划线', () => {
    expect(normalizeBusinessCode('  Customer Order ')).toBe('customer_order');
  });
});
