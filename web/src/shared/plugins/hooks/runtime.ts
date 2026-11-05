import { shallowRef } from 'vue';
import type { Component } from 'vue';
import type { HookCalls, HookContext, HookDescriptor, HookHostContext, HookModule, SourceModules } from './types';
export class HookCancelledError extends Error {
    constructor() {
        super('Plugin hook invocation cancelled');
        this.name = 'HookCancelledError';
    }
}
interface Entry {
    module: HookModule;
    hook: HookDescriptor;
}
const sources = import.meta.glob('/src/admin/modules/**/*.{ts,js,vue,tsx}');
export class HookRuntime implements HookCalls {
    readonly revision = shallowRef(0);
    private entries: Entry[] = [];
    private signature = '';
    private controller = new AbortController();
    constructor(private modules: SourceModules = sources, private report: (error: unknown) => void = console.error) {
    }
    private reportSafely(error: unknown): void {
        try {
            this.report(error);
        }
        catch {
            // A reporter must never interrupt dispatch or lifecycle cleanup.
        }
    }
    sync(modules: HookModule[], identity: string): void {
        if (!Array.isArray(modules)) {
            this.clear();
            throw Error('Invalid hook modules');
        }
        const entries: Entry[] = [];
        const codeCounts = new Map<string, number>();
        for (const module of modules)
            codeCounts.set(module?.code, (codeCounts.get(module?.code) ?? 0) + 1);
        for (const input of modules) {
            try {
                const module = JSON.parse(JSON.stringify(input)) as HookModule;
                if (!/^[a-z][a-z0-9]*$/.test(module.code) || codeCounts.get(module.code) !== 1 || typeof module.version !== 'string' || !Array.isArray(module.frontendHooks) || !module.components || Array.isArray(module.components))
                    throw Error('Invalid hook module');
                const ids = new Set<string>();
                const contributions: Entry[] = [];
                for (let index = 0; index < module.frontendHooks.length; index++) {
                    const hook = module.frontendHooks[index];
                    const original = input.frontendHooks[index];
                    if (typeof hook.id !== 'string' || !hook.id || ids.has(hook.id) || typeof hook.point !== 'string' || !/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)*$/.test(hook.point) || !['action', 'filter', 'component'].includes(hook.type))
                        throw Error('Invalid hook descriptor');
                    ids.add(hook.id);
                    if (original.priority !== undefined && !Number.isInteger(original.priority))
                        throw Error('Invalid hook priority');
                    if (hook.audiences && (!Array.isArray(hook.audiences) || hook.audiences.length === 0 || hook.audiences.some(a => !['public', 'member', 'admin'].includes(a))))
                        throw Error('Invalid hook audience');
                    if (hook.permission && (hook.audiences ?? ['admin']).some(a => a !== 'admin'))
                        throw Error('Invalid hook permission audience');
                    this.source(module, hook.type === 'component' ? module.components[hook.component!] : hook.file);
                    if (hook.type !== 'component' && (!hook.file || !/\.(ts|js)$/.test(hook.file) || !hook.export))
                        throw Error('Invalid function hook source');
                    Object.freeze(hook.audiences);
                    Object.freeze(hook);
                    contributions.push({ module, hook });
                }
                Object.freeze(module.components);
                Object.freeze(module.frontendHooks);
                Object.freeze(module);
                entries.push(...contributions);
            }
            catch (error) {
                this.reportSafely(error);
            }
        }
        const compare = (a: string, b: string) => a < b ? -1 : a > b ? 1 : 0;
        entries.sort((a, b) => (a.hook.priority ?? 100) - (b.hook.priority ?? 100)
            || compare(a.module.code, b.module.code) || compare(a.hook.id, b.hook.id));
        const signature = JSON.stringify([identity, entries]);
        if (signature === this.signature)
            return;
        this.controller.abort();
        this.controller = new AbortController();
        this.entries = entries;
        this.signature = signature;
        this.revision.value++;
    }
    clear(): void {
        this.controller.abort();
        this.controller = new AbortController();
        this.entries = [];
        this.signature = '';
        this.revision.value++;
    }
    private source(module: HookModule, file?: string): () => Promise<any> {
        if (!file || file.startsWith('/') || file.includes('\\') || file.split('/').some(x => x === '..' || x === '.') || !/\.(ts|js|vue|tsx)$/.test(file))
            throw Error('Invalid plugin source');
        const loader = this.modules[`/src/admin/modules/${module.code}/${file}`];
        if (!loader)
            throw Error(`Plugin source missing from build: ${module.code}/${file}`);
        return loader;
    }
    action(point: string, payload?: unknown, context: HookHostContext = {}): Promise<void> {
        return this.dispatch('action', point, payload, context, []) as Promise<void>;
    }
    filter<T>(point: string, value: T, context: HookHostContext = {}): Promise<T> {
        return this.dispatch('filter', point, value, context, []) as Promise<T>;
    }
    private scope(host: AbortSignal | undefined, registration: AbortSignal) {
        const controller = new AbortController();
        const cleanups = new Set<() => void>();
        const dispose = () => {
            if (controller.signal.aborted)
                return;
            controller.abort();
            host?.removeEventListener('abort', dispose);
            registration.removeEventListener('abort', dispose);
            for (const fn of cleanups) {
                try {
                    fn();
                }
                catch (e) {
                    this.reportSafely(e);
                }
            }
            cleanups.clear();
        };
        if (host?.aborted || registration.aborted)
            dispose();
        else {
            host?.addEventListener('abort', dispose, { once: true });
            registration.addEventListener('abort', dispose, { once: true });
        }
        return { signal: controller.signal, onDispose: (fn: () => void) => {
                if (controller.signal.aborted) {
                    try {
                        fn();
                    }
                    catch (e) {
                        this.reportSafely(e);
                    }
                }
                else
                    cleanups.add(fn);
            } };
    }
    private async dispatch(type: 'action' | 'filter', point: string, value: unknown, host: HookHostContext, chain: string[]): Promise<unknown> {
        if (chain.length >= 32)
            throw Error(`Plugin hook maximum depth 32: ${[...chain, point].join(' → ')}`);
        const selected = this.entries.filter(e => e.hook.type === type && e.hook.point === point);
        if (!selected.length) {
            if (host.signal?.aborted)
                throw new HookCancelledError();
            return type === 'filter' ? value : undefined;
        }
        const registration = this.controller.signal;
        const scope = this.scope(host.signal, registration);
        const nested = [...chain, point];
        const check = () => {
            if (scope.signal.aborted)
                throw new HookCancelledError();
        };
        for (const entry of selected) {
            try {
                check();
                const exports = await this.source(entry.module, entry.hook.file)();
                check();
                const fn = exports[entry.hook.export!];
                if (typeof fn !== 'function')
                    throw Error(`Missing hook export: ${entry.hook.export}`);
                const context: HookContext = { ...host, ...scope, pluginCode: entry.module.code, point, id: entry.hook.id, hooks: { action: (p, v, c = {}) => this.dispatch('action', p, v, { ...host, ...c, signal: scope.signal }, nested) as Promise<void>, filter: <T>(p: string, v: T, c: HookHostContext = {}) => this.dispatch('filter', p, v, { ...host, ...c, signal: scope.signal }, nested) as Promise<T> } };
                const result = await fn(value, context);
                check();
                if (type === 'filter')
                    value = result;
            }
            catch (error) {
                if (error instanceof HookCancelledError) {
                    if (type === 'action')
                        return;
                    throw error;
                }
                this.reportSafely({ pluginCode: entry.module.code, point, id: entry.hook.id, error });
                if (type === 'filter')
                    throw error;
            }
        }
        check();
        return type === 'filter' ? value : undefined;
    }
    components(point: string) {
        return this.entries.filter(e => e.hook.type === 'component' && e.hook.point === point);
    }
    async loadComponent(entry: Entry, host: HookHostContext = {}) {
        const registration = this.controller.signal;
        const scope = this.scope(host.signal, registration);
        const imported = await this.source(entry.module, entry.module.components[entry.hook.component!])();
        if (scope.signal.aborted)
            throw new HookCancelledError();
        return { component: imported.default as Component, context: { ...host, ...scope, pluginCode: entry.module.code, point: entry.hook.point, id: entry.hook.id } };
    }
}
export const hooks = new HookRuntime();
