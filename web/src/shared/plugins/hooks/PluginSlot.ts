import {
    defineComponent, h, onActivated, onDeactivated, onErrorCaptured, onUnmounted,
    ref, shallowRef, watch, type PropType, type Component
} from 'vue';
import { hooks, HookRuntime, HookCancelledError } from './runtime';
import type { HookHostContext } from './types';

const Contribution = defineComponent({
    props: {
        entry: { type: Object, required: true },
        runtime: { type: Object as PropType<HookRuntime>, required: true },
        context: { type: Object as PropType<HookHostContext>, required: true }
    },
    setup(props) {
        const failed = ref(false);
        const loaded = shallowRef<Component>();
        const context = shallowRef<HookHostContext>();
        const renderKey = ref(0);
        let active = true;
        let sequence = 0;
        let scope: AbortController | undefined;
        let detachHost: (() => void) | undefined;

        function dispose() {
            sequence++;
            const detach = detachHost;
            const controller = scope;
            detachHost = undefined;
            scope = undefined;
            loaded.value = undefined;
            context.value = undefined;
            detach?.();
            controller?.abort();
        }

        function load() {
            dispose();
            if (!active) return;
            failed.value = false;
            const current = ++sequence;
            const controller = new AbortController();
            scope = controller;
            const hostSignal = props.context.signal;
            const hostAbort = () => dispose();
            if (hostSignal?.aborted) {
                dispose();
                return;
            }
            hostSignal?.addEventListener('abort', hostAbort, { once: true });
            detachHost = () => hostSignal?.removeEventListener('abort', hostAbort);
            void props.runtime.loadComponent(props.entry as any, { ...props.context, signal: controller.signal }).then(result => {
                if (current !== sequence || controller.signal.aborted || !active) return;
                renderKey.value++;
                loaded.value = result.component;
                context.value = result.context;
            }).catch(error => {
                if (current !== sequence) return;
                if (!(error instanceof HookCancelledError)) console.error('[plugin component]', error);
                failed.value = true;
                dispose();
            });
        }

        onErrorCaptured(error => {
            failed.value = true;
            dispose();
            console.error('[plugin component]', error);
            return false;
        });
        onActivated(() => {
            if (active) return;
            active = true;
            load();
        });
        onDeactivated(() => {
            active = false;
            dispose();
        });
        onUnmounted(() => {
            active = false;
            dispose();
        });
        watch(() => ({ ...props.context }), load, { immediate: true, flush: 'sync' });
        return () => !failed.value && loaded.value
            ? h(loaded.value, { key: renderKey.value, context: context.value })
            : null;
    }
});

export default defineComponent({
    name: 'PluginSlot',
    props: {
        name: { type: String, required: true },
        context: { type: Object as PropType<HookHostContext>, default: () => ({}) },
        runtime: { type: Object as PropType<HookRuntime>, default: () => hooks }
    },
    setup(props) {
        const active = ref(true);
        onActivated(() => { active.value = true; });
        onDeactivated(() => { active.value = false; });
        return () => {
            if (!active.value) return [];
            const revision = props.runtime.revision.value;
            return props.runtime.components(props.name).map(entry => h(Contribution, {
                key: `${revision}:${entry.module.code}:${entry.hook.id}`,
                entry,
                runtime: props.runtime,
                context: props.context
            }));
        };
    }
});
