import { describe, expect, it, vi, beforeEach } from 'vitest';
import { withComposerConflictConfirm } from '@/api/system/pluginComposerConflicts';
import { pluginApi } from '@/api/system/plugin';

const mocks = vi.hoisted(() => ({ confirm: vi.fn(), post: vi.fn(), upload: vi.fn() }));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: mocks.confirm } }));
vi.mock('@/utils/http', () => ({ default: { post: mocks.post, upload: mocks.upload } }));
const conflict = { code: 409, data: { reason: 'composer_conflict', conflicts: [
  { package: 'test/lib', source: 'host', pluginVersion: '2.0.0', otherVersion: '1.0.0', reason: 'incompatible' }
] } };
beforeEach(() => vi.resetAllMocks());

describe('Composer conflict confirmation', () => {
  it('passes ordinary success without prompting', async () => {
    const flags: boolean[] = [];
    expect(await withComposerConflictConfirm(async flag => { flags.push(flag); return 'ok'; })).toBe('ok');
    expect(flags).toEqual([false]); expect(mocks.confirm).not.toHaveBeenCalled();
  });
  it('asks once and resends with explicit acceptance', async () => {
    const flags: boolean[] = []; mocks.confirm.mockResolvedValue('confirm');
    const result = await withComposerConflictConfirm(async flag => { flags.push(flag); if (!flag) throw conflict; return 'installed'; });
    expect(result).toBe('installed'); expect(flags).toEqual([false, true]); expect(mocks.confirm).toHaveBeenCalledOnce();
  });
  it.each(['cancel', 'close'])('rejects %s without a retry or false success', async reason => {
    mocks.confirm.mockRejectedValue(reason); let attempts = 0;
    await expect(withComposerConflictConfirm(async () => { attempts++; throw conflict; })).rejects.toBe(reason);
    expect(attempts).toBe(1);
  });
  it('does not swallow unrelated errors or loop on second conflicts', async () => {
    const error = { code: 422 }; await expect(withComposerConflictConfirm(async () => { throw error; })).rejects.toBe(error);
    mocks.confirm.mockResolvedValue('confirm');
    await expect(withComposerConflictConfirm(async () => { throw conflict; })).rejects.toBe(conflict);
    expect(mocks.confirm).toHaveBeenCalledOnce();
  });
  it.each(['installDiscovered', 'enable'] as const)('wraps %s requests', async action => {
    mocks.post.mockRejectedValueOnce(conflict).mockResolvedValueOnce('ok'); mocks.confirm.mockResolvedValue('confirm');
    await expect(pluginApi[action]('demo')).resolves.toBe('ok');
    expect(mocks.post.mock.calls[0][1]).toEqual({ acceptComposerConflicts: false });
    expect(mocks.post.mock.calls[1][1]).toEqual({ acceptComposerConflicts: true });
  });
  it.each([
    ['installCloud', () => pluginApi.installCloud('demo', '1.0.0')],
    ['update', () => pluginApi.update('demo', '2.0.0', false)],
    ['redeployHistory', () => pluginApi.redeployHistory('demo', 7, false)]
  ] as const)('wraps %s with acceptance and preserves original inputs', async (_name, run) => {
    mocks.post.mockRejectedValueOnce(conflict).mockResolvedValueOnce('ok'); mocks.confirm.mockResolvedValue('confirm');
    await expect(run()).resolves.toBe('ok');
    const [first, second] = mocks.post.mock.calls;
    expect(second[0]).toBe(first[0]);
    expect(first[1].acceptComposerConflicts).toBe(false);
    expect(second[1]).toEqual({ ...first[1], acceptComposerConflicts: true });
  });
  it('preserves migrate and file when retrying local update', async () => {
    mocks.upload.mockRejectedValueOnce(conflict).mockResolvedValueOnce('ok'); mocks.confirm.mockResolvedValue('confirm');
    const file = new File(['zip'], 'demo.zip'); await pluginApi.updateLocal('demo', file, false);
    const retried = mocks.upload.mock.calls[1][1] as FormData;
    expect(retried.get('acceptComposerConflicts')).toBe('true'); expect(retried.get('migrate')).toBe('false'); expect(retried.get('file')).toBe(file);
  });
  it('rebuilds multipart upload on accepted retry', async () => {
    mocks.upload.mockRejectedValueOnce(conflict).mockResolvedValueOnce('ok'); mocks.confirm.mockResolvedValue('confirm');
    const file = new File(['zip'], 'demo.zip'); await pluginApi.installLocal(file);
    expect(mocks.upload.mock.calls[0][1].get('acceptComposerConflicts')).toBe('false');
    expect(mocks.upload.mock.calls[1][1].get('acceptComposerConflicts')).toBe('true');
    expect(mocks.upload.mock.calls[1][1].get('file')).toBe(file);
  });
});
