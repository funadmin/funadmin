import { describe, expect, it, vi } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import http from '@/utils/http';
import { upgradeApi } from '@/api/system/upgrade';

vi.mock('@/utils/http', () => ({
  default: { get: vi.fn(), post: vi.fn(), upload: vi.fn() }
}));

const projectRoot = resolve(process.cwd(), '..');

describe('M6 系统升级契约', () => {
  it('提供状态、检查、执行、恢复与离线包 API', async () => {
    const file = new File(['zip'], 'upgrade.zip', { type: 'application/zip' });
    await upgradeApi.status();
    await upgradeApi.check();
    await upgradeApi.execute({ manifestId: 'manifest-1234567890', operationToken: 'token-1234567890' });
    await upgradeApi.restore(3, 'restore-token-123456');
    await upgradeApi.recoverStale();
    await upgradeApi.upload(file, 'upload-token-123456');
    expect(http.get).toHaveBeenCalledWith('/system/upgrade/status');
    expect(http.get).toHaveBeenCalledWith('/system/upgrade/check');
    expect(http.post).toHaveBeenCalledWith('/system/upgrade/execute', { manifestId: 'manifest-1234567890', operationToken: 'token-1234567890' }, expect.any(Object));
    expect(http.post).toHaveBeenCalledWith('/system/upgrade/3/restore', { operationToken: 'restore-token-123456' }, expect.any(Object));
    expect(http.post).toHaveBeenCalledWith('/system/upgrade/recover-stale', {}, expect.any(Object));
    const uploadForm = vi.mocked(http.upload).mock.calls[0][1] as FormData;
    expect(uploadForm.get('operationToken')).toBe('upload-token-123456');
    expect(uploadForm.has('sha256')).toBe(false);
    expect(uploadForm.has('version')).toBe(false);
    expect(uploadForm.has('signature')).toBe(false);
  });

  it('提供 Vue 升级页、任务阶段、权限门禁和可交互 mock 入口', () => {
    const pagePath = resolve(projectRoot, 'admin-web/src/views/system/upgrade/index.vue');
    expect(existsSync(pagePath)).toBe(true);
    const page = readFileSync(pagePath, 'utf8');
    for (const text of ['SystemUpgrade', 'system:upgrade:check', 'system:upgrade:execute', 'system:upgrade:restore', 'operationToken', 'manifestId', '备份', '校验', '恢复']) {
      expect(page).toContain(text);
    }
    const menuSeed = readFileSync(resolve(projectRoot, 'admin-web/src/mock/data/adminSeed.ts'), 'utf8');
    expect(menuSeed).toContain("routeName: 'SystemUpgrade'");
    expect(menuSeed).toContain("component: 'system/upgrade/index'");
    const mockIndex = readFileSync(resolve(projectRoot, 'admin-web/src/mock/index.ts'), 'utf8');
    expect(mockIndex).toContain('upgradeMockHandlers');
    const mockUpgrade = readFileSync(resolve(projectRoot, 'admin-web/src/mock/modules/upgrade.ts'), 'utf8');
    expect(mockUpgrade).toContain('manifestId');
    expect(mockUpgrade).not.toContain('packageUrl');
    expect(mockUpgrade).not.toContain('sha256');
    expect(existsSync(resolve(projectRoot, 'admin-web/src/mock/modules/upgrade.ts'))).toBe(true);
  });

  it('通过 forward migration 注册规范权限和菜单字段', () => {
    const migration = readFileSync(resolve(projectRoot, 'database/migrations/043_system_upgrade.sql'), 'utf8');
    for (const action of ['status', 'check', 'executeupgrade', 'upload', 'restore']) {
      expect(migration).toContain(`backend/systemupgrade:${action}`);
    }
    for (const field of ['`name`', '`sort_order`', '`created_at`', '`updated_at`']) {
      expect(migration).toContain(field);
    }
    expect(migration).toContain('WHERE NOT EXISTS');
    expect(migration).not.toMatch(/\b(?:DROP|TRUNCATE|DELETE)\b/i);
  });

  it('将后端升级 action 映射为前端业务权限', () => {
    const auth = readFileSync(resolve(projectRoot, 'app/console/controller/auth/AdminAuth.php'), 'utf8');
    const mappings = {
      status: 'system:upgrade:list',
      check: 'system:upgrade:check',
      executeupgrade: 'system:upgrade:execute',
      upload: 'system:upgrade:upload',
      restore: 'system:upgrade:restore'
    };
    for (const [action, permission] of Object.entries(mappings)) {
      expect(auth).toContain(`'backend/systemupgrade:${action}' => '${permission}'`);
    }
  });

  it('保留旧升级入口文件，不在 M6 删除', () => {
    const cleanup = readFileSync(resolve(projectRoot, 'tests/legacy_controller_cleanup_test.php'), 'utf8');
    expect(cleanup).not.toContain("'app/console/controller/sys/Upgrade.php'");
    expect(cleanup).not.toContain("'app/console/view/sys/upgrade/index.html'");
  });
});
