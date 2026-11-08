import { it, expect, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { defineComponent, h, KeepAlive, ref, nextTick, reactive } from 'vue';
import PluginSlot from './PluginSlot';
import { HookRuntime } from './runtime';
it('renders context, isolates broken components, unmounts on clear', async () => {
    const r = new HookRuntime({ '/src/admin/plugins/a/card.vue': async () => ({ default: defineComponent({ props: ['context'], setup: p => () => h('span', p.context.label) }) }), '/src/admin/plugins/a/bad.vue': async () => ({ default: defineComponent({ setup() {
                    throw Error('bad');
                } }) }) }, () => {
    });
    r.sync([{ code: 'a', version: '1', components: { good: 'card.vue', bad: 'bad.vue' }, clientHooks: [{ id: 'bad', point: 'cards', type: 'component', component: 'bad' }, { id: 'good', point: 'cards', type: 'component', component: 'good' }] }], 'x');
    const wrapper = mount(PluginSlot, { props: { name: 'cards', runtime: r, context: { label: 'hello' } } });
    await flushPromises();
    expect(wrapper.text()).toBe('hello');
    r.clear();
    await flushPromises();
    expect(wrapper.text()).toBe('');
    wrapper.unmount();
});

function slotRuntime(loader: () => Promise<any>) {
    const runtime = new HookRuntime({ '/src/admin/plugins/a/card.vue': loader }, () => {});
    runtime.sync([{ code: 'a', version: '1', components: { card: 'card.vue' }, clientHooks: [{ id: 'card', point: 'cards', type: 'component', component: 'card' }] }], 'admin:1');
    return runtime;
}
function widget(cleanup: () => void, mounted: () => void = () => {}) {
    return defineComponent({
        props: ['context'],
        setup(props) {
            mounted();
            props.context.onDispose(cleanup);
            return () => h('span', props.context.label);
        }
    });
}
function keepAliveSlot(runtime: HookRuntime) {
    const visible = ref(true);
    const page = defineComponent({ setup: () => () => h(PluginSlot, { name: 'cards', runtime, context: { label: 'card' } }) });
    const harness = defineComponent({ setup: () => () => h(KeepAlive, null, { default: () => visible.value ? h(page) : null }) });
    return { visible, wrapper: mount(harness) };
}
it('disposes kept-alive contributions once and creates a fresh scope on reactivation', async () => {
    const cleanup = vi.fn();
    const loader = vi.fn(async () => ({ default: widget(cleanup) }));
    const runtime = slotRuntime(loader);
    const { visible, wrapper } = keepAliveSlot(runtime);
    await flushPromises();
    expect(wrapper.text()).toBe('card');
    visible.value = false;
    await nextTick();
    expect(cleanup).toHaveBeenCalledTimes(1);
    visible.value = true;
    await flushPromises();
    expect(loader).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toBe('card');
    wrapper.unmount();
    expect(cleanup).toHaveBeenCalledTimes(2);
});
it('ignores imports completed while deactivated and reloads when activated', async () => {
    const pending: Array<(value: any) => void> = [];
    const mounted = vi.fn();
    const runtime = slotRuntime(() => new Promise(resolve => pending.push(resolve)));
    const { visible, wrapper } = keepAliveSlot(runtime);
    visible.value = false;
    await nextTick();
    pending[0]({ default: widget(() => {}, mounted) });
    await flushPromises();
    expect(mounted).not.toHaveBeenCalled();
    visible.value = true;
    await nextTick();
    expect(pending).toHaveLength(2);
    pending[1]({ default: widget(() => {}, mounted) });
    await flushPromises();
    expect(mounted).toHaveBeenCalledTimes(1);
    wrapper.unmount();
});
it('replaces host context and signal without stale imports or old signal ownership', async () => {
    const pending: Array<(value: any) => void> = [];
    const cleanup = vi.fn();
    const runtime = slotRuntime(() => new Promise(resolve => pending.push(resolve)));
    const oldHost = new AbortController();
    const newHost = new AbortController();
    const wrapper = mount(PluginSlot, { props: { name: 'cards', runtime, context: { label: 'old', signal: oldHost.signal } } });
    await wrapper.setProps({ context: { label: 'new', signal: newHost.signal } });
    expect(pending).toHaveLength(2);
    pending[1]({ default: widget(cleanup) });
    await flushPromises();
    expect(wrapper.text()).toBe('new');
    oldHost.abort();
    expect(cleanup).not.toHaveBeenCalled();
    pending[0]({ default: widget(cleanup) });
    await flushPromises();
    expect(wrapper.text()).toBe('new');
    newHost.abort();
    await nextTick();
    expect(cleanup).toHaveBeenCalledTimes(1);
    wrapper.unmount();
    expect(cleanup).toHaveBeenCalledTimes(1);
});

it('updates an already mounted widget when a context record or signal changes', async () => {
    const cleanup = vi.fn();
    const runtime = slotRuntime(async () => ({ default: widget(cleanup) }));
    const oldHost = new AbortController();
    const newHost = new AbortController();
    const context = reactive({ label: 'old record', signal: oldHost.signal });
    const wrapper = mount(PluginSlot, { props: { name: 'cards', runtime, context } });
    await flushPromises();
    expect(wrapper.text()).toBe('old record');
    context.label = 'new record';
    context.signal = newHost.signal;
    await flushPromises();
    expect(wrapper.text()).toBe('new record');
    expect(cleanup).toHaveBeenCalledTimes(1);
    oldHost.abort();
    expect(cleanup).toHaveBeenCalledTimes(1);
    newHost.abort();
    await nextTick();
    expect(wrapper.text()).toBe('');
    expect(cleanup).toHaveBeenCalledTimes(2);
    wrapper.unmount();
    expect(cleanup).toHaveBeenCalledTimes(2);
});

it('does not load revised contributions while the slot is deactivated', async () => {
    const cleanup = vi.fn();
    const loader = vi.fn(async () => ({ default: widget(cleanup) }));
    const runtime = slotRuntime(loader);
    const { visible, wrapper } = keepAliveSlot(runtime);
    await flushPromises();
    visible.value = false;
    await flushPromises();
    runtime.sync([{ code: 'a', version: '2', components: { card: 'card.vue' }, clientHooks: [{ id: 'card', point: 'cards', type: 'component', component: 'card' }] }], 'admin:1');
    await flushPromises();
    expect(loader).toHaveBeenCalledTimes(1);
    visible.value = true;
    await flushPromises();
    expect(loader).toHaveBeenCalledTimes(2);
    wrapper.unmount();
    expect(cleanup).toHaveBeenCalledTimes(2);
});
