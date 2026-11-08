import { describe, it, expect, vi } from 'vitest';
import { HookRuntime, HookCancelledError } from './runtime';
import type { HookModule } from './types';

it('uses the same ASCII id order as PHP independent of browser locale', async () => {
    const runtime = new HookRuntime({
        '/src/admin/plugins/a/hooks.ts': async () => ({
            a: (value: string) => value + 'a',
            A: (value: string) => value + 'A'
        })
    });
    runtime.sync([mod('a', [hook('a'), hook('A')])], 'public');
    expect(await runtime.filter('test', '')).toBe('Aa');
});

const mod = (code: string, clientHooks: HookModule['clientHooks']): HookModule => ({ code, version: '1', components: {}, clientHooks });
const hook = (id: string, type: 'filter' | 'action' = 'filter', point = 'test') => ({ id, type, point, file: 'hooks.ts', export: id });
describe('shared hook runtime', () => {
    it('orders cross-plugin filters and preserves false/null', async () => {
        const seen: unknown[] = [];
        const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ x: () => false }), '/src/admin/plugins/b/hooks.ts': async () => ({ y: (v: unknown) => {
                    seen.push(v);
                    return null;
                } }) });
        r.sync([mod('b', [hook('y')]), mod('a', [hook('x')])], 'admin:1');
        expect(await r.filter('test', 5)).toBeNull();
        expect(seen).toEqual([false]);
    });
    it('isolates action errors and binds nested depth per independent chain', async () => {
        const report = vi.fn();
        const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ bad: () => {
                    throw Error('bad');
                }, ok: vi.fn(), loop: (_: unknown, c: any) => c.hooks.filter('loop', 0) }) }, report);
        r.sync([mod('a', [hook('bad', 'action'), hook('ok', 'action'), hook('loop', 'filter', 'loop')])], 'x');
        await r.action('test');
        expect(report).toHaveBeenCalled();
        await expect(r.filter('loop', 0)).rejects.toThrow('32');
        await expect(Promise.all(Array.from({ length: 40 }, () => r.filter('none', false)))).resolves.toHaveLength(40);
    });
    it('cancels pending imports before invocation and rejects stale filters', async () => {
        let resolve!: (v: any) => void;
        const fn = vi.fn();
        const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': () => new Promise(res => resolve = res) });
        r.sync([mod('a', [hook('x')])], 'x');
        const pending = r.filter('test', 0);
        r.clear();
        resolve({ x: fn });
        await expect(pending).rejects.toBeInstanceOf(HookCancelledError);
        expect(fn).not.toHaveBeenCalled();
    });
    it('cleans once on host or projection change, including late registration', async () => {
        let ctx: any;
        const cleanup = vi.fn();
        const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ x: (_: unknown, c: any) => {
                    ctx = c;
                    c.onDispose(cleanup);
                } }) });
        const m = mod('a', [hook('x', 'action')]);
        r.sync([m], 'x');
        const host = new AbortController();
        await r.action('test', null, { signal: host.signal });
        host.abort();
        r.clear();
        expect(cleanup).toHaveBeenCalledTimes(1);
        ctx.onDispose(cleanup);
        expect(cleanup).toHaveBeenCalledTimes(2);
        r.sync([m], 'x');
        await r.action('test');
        r.sync([{ ...m, clientHooks: [] }], 'x');
        expect(ctx.signal.aborted).toBe(true);
    });
});
it('isolates invalid plugins and snapshots caller-owned descriptors', async () => {
    const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ x: () => 42 }) }, () => {
    });
    const good = mod('a', [hook('x')]);
    r.sync([good, mod('BAD', [hook('x')]), mod('b', [{ ...hook('x'), priority: NaN }])], 'x');
    good.clientHooks[0].point = 'mutated';
    expect(await r.filter('test', 0)).toBe(42);
});
it('contains reporter failures during actions and cleanup', async () => {
    const cleaned = vi.fn();
    const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ bad: () => {
                throw Error('handler');
            }, ok: (_: unknown, c: any) => {
                c.onDispose(() => {
                    throw Error('cleanup');
                });
                c.onDispose(cleaned);
            } }) }, () => {
        throw Error('reporter');
    });
    r.sync([mod('a', [hook('bad', 'action'), hook('ok', 'action')])], 'x');
    await expect(r.action('test')).resolves.toBeUndefined();
    expect(() => r.clear()).not.toThrow();
    expect(cleaned).toHaveBeenCalledOnce();
});
it('rejects duplicate codes/ids and noninteger priorities without dropping valid plugins', async () => {
    const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ x: () => 42 }), '/src/admin/plugins/b/hooks.ts': async () => ({ x: () => -1 }) }, () => {
    });
    const valid = mod('a', [hook('x')]);
    for (const invalid of [[mod('b', [hook('x'), hook('x')])], [mod('b', [hook('x')]), mod('b', [hook('x')])], [mod('b', [{ ...hook('x'), priority: NaN }])]]) {
        r.sync([valid, ...invalid], 'x');
        expect(await r.filter('test', 0)).toBe(42);
    }
});
it('keeps concurrent asynchronous nested chains independent', async () => {
    const r = new HookRuntime({ '/src/admin/plugins/a/hooks.ts': async () => ({ root: async (v: unknown, c: any) => {
                await Promise.resolve();
                return c.hooks.filter('nested', v);
            }, nested: async (v: unknown) => {
                await Promise.resolve();
                return v;
            } }) }, () => {
    });
    r.sync([mod('a', [hook('root', 'filter', 'root'), hook('nested', 'filter', 'nested')])], 'x');
    expect(await Promise.all(Array.from({ length: 40 }, (_, i) => r.filter('root', i)))).toEqual(Array.from({ length: 40 }, (_, i) => i));
});
