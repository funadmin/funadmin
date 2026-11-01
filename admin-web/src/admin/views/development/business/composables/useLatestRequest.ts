import { getCurrentInstance, onBeforeUnmount, ref, shallowRef } from 'vue';

export function useLatestRequest<T, A extends unknown[]>(request: (...args: A) => Promise<T>) {
  const data = shallowRef<T>();
  const error = shallowRef<unknown>();
  const loading = ref(false);
  let sequence = 0;
  let active = true;

  async function execute(...args: A): Promise<T | undefined> {
    const requestId = ++sequence;
    loading.value = true;
    error.value = undefined;
    try {
      const result = await request(...args);
      if (active && requestId === sequence) data.value = result;
      return result;
    } catch (reason) {
      if (active && requestId === sequence) error.value = reason;
      if (requestId === sequence) throw reason;
      return undefined;
    } finally {
      if (active && requestId === sequence) loading.value = false;
    }
  }

  function invalidate() {
    sequence += 1;
    loading.value = false;
  }

  if (getCurrentInstance()) {
    onBeforeUnmount(() => {
      active = false;
      sequence += 1;
    });
  }

  return { data, error, loading, execute, invalidate };
}
