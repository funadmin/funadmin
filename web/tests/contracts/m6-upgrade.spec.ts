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
    const pagePath = resolve(projectRoot, 'web/src/admin/views/system/upgrade/index.vue');
    expect(existsSync(pagePath)).toBe(true);
    const page = readFileSync(pagePath, 'utf8');
    for (const text of ['SystemUpgrade', 'system:upgrade:check', 'system:upgrade:execute-upgrade', 'system:upgrade:restore', 'system:upgrade:recover-stale', 'operationToken', 'manifestId', '备份', '校验', '恢复']) {
      expect(page).toContain(text);
    }
    const menuSeed = readFileSync(resolve(projectRoot, 'web/src/admin/mock/data/adminSeed.ts'), 'utf8');
    expect(menuSeed).toContain("routeName: 'SystemUpgrade'");
    expect(menuSeed).toContain("component: 'system/upgrade/index'");
    const mockIndex = readFileSync(resolve(projectRoot, 'web/src/admin/mock/index.ts'), 'utf8');
    expect(mockIndex).toContain('upgradeMockHandlers');
    const mockUpgrade = readFileSync(resolve(projectRoot, 'web/src/admin/mock/modules/upgrade.ts'), 'utf8');
    expect(mockUpgrade).toContain('manifestId');
    expect(mockUpgrade).not.toContain('packageUrl');
    expect(mockUpgrade).not.toContain('sha256');
    expect(existsSync(resolve(projectRoot, 'web/src/admin/mock/modules/upgrade.ts'))).toBe(true);
  });


  it('升级页面直接使用按方法推导的后端权限码（无对照表）', () => {
    const auth = readFileSync(resolve(projectRoot, 'admin/app/admin/authorization/model/Permission.php'), 'utf8');
    expect(auth).not.toContain("'systemupgrade:");
    const page = readFileSync(resolve(projectRoot, 'web/src/admin/views/system/upgrade/index.vue'), 'utf8');
    for (const code of ['system:upgrade:check', 'system:upgrade:execute-upgrade', 'system:upgrade:upload', 'system:upgrade:restore']) {
      expect(page).toContain(code);
    }
  });

  it('保留旧升级入口文件，不在 M6 删除', () => {
    const cleanup = readFileSync(resolve(projectRoot, 'admin/app/admin/system/controller/SystemUpgrade.php'), 'utf8');
    expect(cleanup).toContain('class SystemUpgrade');
  });
});
