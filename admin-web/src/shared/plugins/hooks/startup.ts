import type { Router } from 'vue-router';
import { watch } from 'vue';
import { APP_CONFIG } from '@/config';
import { hooks, HookRuntime } from './runtime';
import type { HookAudience, HookModule } from './types';
export function routeAudience(route: {
    path: string;
    meta: Record<string, unknown>;
}): HookAudience {
    if (route.meta.public || ['/admin/login', '/admin/install'].includes(route.path))
        return 'public';
    return route.meta.member ? 'member' : 'admin';
}
export class HookSynchronizer {
    private identity = '';
    private sequence = 0;
    private pending?: Promise<void>;
    constructor(private runtime: HookRuntime, private fetchModules: (audience: HookAudience) => Promise<{
        generation: string;
        modules: HookModule[];
    }>) {
    }
    sync(identity: string, audience: HookAudience, force = false): Promise<void> {
        if (force)
            this.clear();
        if (identity === this.identity && this.pending)
            return this.pending;
        if (identity !== this.identity) {
            this.runtime.clear();
            this.identity = identity;
        }
        const sequence = ++this.sequence;
        const pending = this.fetchModules(audience).then(result => {
            if (sequence !== this.sequence)
                return;
            if (!result || typeof result.generation !== 'string' || !Array.isArray(result.modules))
                throw Error('Invalid hook response');
            this.runtime.sync(result.modules.map(module => ({ ...module, generation: module.generation ?? result.generation })), identity);
        }).catch(error => {
            if (sequence === this.sequence) {
                this.runtime.clear();
                console.error('[plugin hooks sync]', error);
            }
        }).finally(() => {
            if (sequence === this.sequence)
                this.pending = undefined;
        });
        this.pending = pending;
        return pending;
    }
    clear() {
        this.sequence++;
        this.pending = undefined;
        this.identity = '';
        this.runtime.clear();
    }
}
let refresh: (force?: boolean) => Promise<void> = async () => {
};
export const refreshPluginHooks = () => refresh(true);
export function startPluginHooks(router: Router, session: () => {
    admin: string;
    member: string;
    memberToken: string;
}): () => void {
    const synchronizer = new HookSynchronizer(hooks, async (audience) => {
        const token = audience === 'member' ? session().memberToken : '';
        const origin = /^https?:\/\//.test(APP_CONFIG.baseApi) ? new URL(APP_CONFIG.baseApi).origin : '';
        const endpoint = audience === 'admin'
            ? `${APP_CONFIG.baseApi.replace(/\/+$/, '')}/plugins/hooks/frontend`
            : `${origin}/plugins/hooks/frontend`;
        const response = await fetch(`${endpoint}?audience=${audience}`, {
            credentials: audience === 'admin' ? 'include' : 'omit',
            headers: token ? { Authorization: `Bearer ${token}` } : {}
        });
        const body = await response.json();
        if (!response.ok || body.code !== 200)
            throw Error(body.message || 'Hook descriptors unavailable');
        return body.data;
    });
    const current = () => {
        const audience = routeAudience(router.currentRoute.value);
        const user = session();
        return { audience, identity: `${audience}:${audience === 'admin' ? user.admin : audience === 'member' ? user.member : 'anonymous'}` };
    };
    refresh = (force = false) => {
        const state = current();
        const user = session();
        if ((state.audience === 'admin' && !user.admin) || (state.audience === 'member' && !user.memberToken)) {
            synchronizer.clear();
            return Promise.resolve();
        }
        return synchronizer.sync(state.identity, state.audience, force);
    };
    const stop = watch(() => current().identity, () => void refresh(), { immediate: true, flush: 'sync' });
    const focus = () => {
        if (document.visibilityState === 'visible')
            void refresh();
    };
    const sessionChanged = () => {
        synchronizer.clear();
        void refresh();
    };
    window.addEventListener('focus', focus);
    window.addEventListener('plugin-hooks-session', sessionChanged);
    document.addEventListener('visibilitychange', focus);
    const interval = window.setInterval(focus, 30000);
    return () => {
        stop();
        synchronizer.clear();
        window.clearInterval(interval);
        window.removeEventListener('focus', focus);
        window.removeEventListener('plugin-hooks-session', sessionChanged);
        document.removeEventListener('visibilitychange', focus);
        refresh = async () => {
        };
    };
}
