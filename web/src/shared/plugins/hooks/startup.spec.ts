import { it, expect, vi } from 'vitest';
import { APP_CONFIG } from '@/config';
import { HookSynchronizer, routeAudience, startPluginHooks } from './startup';
import { HookRuntime, hooks } from './runtime';
import { createRouter, createMemoryHistory } from 'vue-router';
const descriptor = { code: 'a', version: '1', components: {}, frontendHooks: [] };
it('classifies public before member and treats login as public', () => {
    expect(routeAudience({ path: '/admin/login', meta: {} })).toBe('public');
    expect(routeAudience({ path: '/web', meta: { public: true, member: true } })).toBe('public');
    expect(routeAudience({ path: '/web', meta: { member: true } })).toBe('member');
    expect(routeAudience({ path: '/admin/dashboard', meta: {} })).toBe('admin');
});
it('deduplicates requests and ignores stale results after identity switch', async () => {
    const resolves: Array<(v: any) => void> = [];
    const fetch = vi.fn(() => new Promise<any>(r => resolves.push(r)));
    const runtime = new HookRuntime({});
    const sync = vi.spyOn(runtime, 'sync');
    const s = new HookSynchronizer(runtime, fetch);
    const first = s.sync('admin:1', 'admin');
    expect(s.sync('admin:1', 'admin')).toBe(first);
    const next = s.sync('public', 'public');
    resolves[1]({ generation: 'new', modules: [] });
    await next;
    resolves[0]({ generation: 'old', modules: [descriptor] });
    await first;
    expect(sync).toHaveBeenCalledTimes(1);
    expect(sync).toHaveBeenCalledWith([], 'public');
});
it('clears contributions when descriptor fetch fails', async () => {
    const runtime = new HookRuntime({});
    runtime.sync([descriptor], 'old');
    const revision = runtime.revision.value;
    const report = vi.spyOn(console, 'error').mockImplementation(() => {
    });
    await new HookSynchronizer(runtime, async () => {
        throw Error('offline');
    }).sync('public', 'public');
    expect(runtime.revision.value).toBeGreaterThan(revision);
    report.mockRestore();
});
it('refreshes on focus and visible intervals, and removes listeners on stop', async () => {
    vi.useFakeTimers();
    const fetch = vi.fn(async () => ({ ok: true, json: async () => ({ code: 200, data: { generation: '1', modules: [] } }) }));
    vi.stubGlobal('fetch', fetch);
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: {}, meta: { public: true } }] });
    await router.push('/');
    const stop = startPluginHooks(router, () => ({ admin: '', member: '', memberToken: '' }));
    await vi.advanceTimersByTimeAsync(1);
    expect(fetch).toHaveBeenCalledTimes(1);
    window.dispatchEvent(new Event('focus'));
    await vi.advanceTimersByTimeAsync(1);
    expect(fetch).toHaveBeenCalledTimes(2);
    await vi.advanceTimersByTimeAsync(30000);
    expect(fetch).toHaveBeenCalledTimes(3);
    stop();
    window.dispatchEvent(new Event('focus'));
    await vi.advanceTimersByTimeAsync(30000);
    expect(fetch).toHaveBeenCalledTimes(3);
    vi.unstubAllGlobals();
    vi.useRealTimers();
    hooks.clear();
});
it('forces a lifecycle request and prevents the pending result from restoring old modules', async () => {
    const resolves: Array<(v: any) => void> = [];
    const runtime = new HookRuntime({});
    const applied = vi.spyOn(runtime, 'sync');
    const s = new HookSynchronizer(runtime, () => new Promise(r => resolves.push(r)));
    const old = s.sync('admin:1', 'admin');
    const fresh = s.sync('admin:1', 'admin', true);
    resolves[1]({ generation: '2', modules: [] });
    await fresh;
    resolves[0]({ generation: '1', modules: [descriptor] });
    await old;
    expect(applied).toHaveBeenCalledTimes(1);
    expect(applied).toHaveBeenLastCalledWith([], 'admin:1');
});
it.each([
    { path: '/admin/dashboard', meta: {}, url: '/admin/plugins/hooks/frontend?audience=admin', credentials: 'include', headers: {} },
    { path: '/web/profile', meta: { member: true }, url: '/plugins/hooks/frontend?audience=member', credentials: 'omit', headers: { Authorization: 'Bearer member-token' } },
    { path: '/', meta: { public: true }, url: '/plugins/hooks/frontend?audience=public', credentials: 'omit', headers: {} }
])('requests $path hook descriptors with its audience authentication', async ({ path, meta, url, credentials, headers }) => {
    const fetch = vi.fn(async () => ({ ok: true, json: async () => ({ code: 200, data: { generation: '1', modules: [] } }) }));
    vi.stubGlobal('fetch', fetch);
    const router = createRouter({ history: createMemoryHistory('/web/'), routes: [{ path, component: {}, meta }] });
    await router.push(path);
    const stop = startPluginHooks(router, () => ({ admin: 'admin-1', member: 'member-2', memberToken: 'member-token' }));
    try {
        await Promise.resolve();
        expect(fetch).toHaveBeenCalledWith(url, { credentials, headers });
    }
    finally {
        stop();
        vi.unstubAllGlobals();
    }
});
it('uses the configured API origin independently of the frontend asset base', async () => {
    const previous = APP_CONFIG.baseApi;
    APP_CONFIG.baseApi = 'https://api.example.test/admin';
    const fetch = vi.fn(async () => ({ ok: true, json: async () => ({ code: 200, data: { generation: '1', modules: [] } }) }));
    vi.stubGlobal('fetch', fetch);
    const router = createRouter({ history: createMemoryHistory('/web/'), routes: [{ path: '/admin/dashboard', component: {} }] });
    await router.push('/admin/dashboard');
    const stop = startPluginHooks(router, () => ({ admin: 'admin-1', member: '', memberToken: '' }));
    try {
        await Promise.resolve();
        expect(fetch).toHaveBeenCalledWith('https://api.example.test/admin/plugins/hooks/frontend?audience=admin', { credentials: 'include', headers: {} });
    }
    finally {
        stop();
        APP_CONFIG.baseApi = previous;
        vi.unstubAllGlobals();
    }
});
it('uses the full configured management API prefix for admin hooks', async () => {
    const previous = APP_CONFIG.baseApi;
    APP_CONFIG.baseApi = '/gateway/admin/';
    const fetch = vi.fn(async () => ({ ok: true, json: async () => ({ code: 200, data: { generation: '1', modules: [] } }) }));
    vi.stubGlobal('fetch', fetch);
    const router = createRouter({ history: createMemoryHistory('/'), routes: [{ path: '/admin/dashboard', component: {} }] });
    await router.push('/admin/dashboard');
    const stop = startPluginHooks(router, () => ({ admin: 'admin-1', member: '', memberToken: '' }));
    try {
        await Promise.resolve();
        expect(fetch).toHaveBeenCalledWith('/gateway/admin/plugins/hooks/frontend?audience=admin', { credentials: 'include', headers: {} });
    } finally {
        stop();
        APP_CONFIG.baseApi = previous;
        vi.unstubAllGlobals();
    }
});
