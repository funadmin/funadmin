import { isMockModeEnabled } from '@/config';
import type { AiEventSourceLike } from './ai';

export interface AiEventSourceFactory {
  create(url: string): AiEventSourceLike;
}

export type AiEventSourceConstructor = new (url: string) => AiEventSourceLike;

class MockAiEventSource implements AiEventSourceLike {
  readonly listeners = new Map<string, Set<EventListener>>();
  readonly timers = new Set<ReturnType<typeof setTimeout>>();
  onopen: ((event: Event) => void) | null = null;
  onerror: ((event: Event) => void) | null = null;
  closed = false;

  constructor(private readonly url: string) {
    this.schedule(() => this.onopen?.(new Event('open')));
    this.scheduleApprovalRequired();
  }

  addEventListener(type: string, listener: EventListener): void {
    const listeners = this.listeners.get(type) ?? new Set<EventListener>();
    listeners.add(listener);
    this.listeners.set(type, listeners);
  }

  close(): void {
    this.closed = true;
    this.timers.forEach((timer) => clearTimeout(timer));
    this.timers.clear();
    this.listeners.clear();
  }

  private schedule(callback: () => void): void {
    const timer = setTimeout(() => {
      this.timers.delete(timer);
      if (!this.closed) callback();
    }, 0);
    this.timers.add(timer);
  }

  private scheduleApprovalRequired(): void {
    const parsed = new URL(this.url, window.location.origin);
    const taskId = Number(parsed.pathname.match(/\/tasks\/(\d+)\/events$/)?.[1] ?? 0);
    const cursor = Number(parsed.searchParams.get('cursor') ?? 0);
    this.schedule(() => {
      const event = {
        type: 'approval.required',
        lastEventId: String(cursor + 1),
        data: JSON.stringify({ taskId, status: 'pending' })
      } as MessageEvent<string>;
      this.listeners.get(event.type)?.forEach((listener) => listener(event));
    });
  }
}

const nativeAiEventSourceFactory: AiEventSourceFactory = {
  create(url) {
    return new EventSource(url);
  }
};

export const mockAiEventSourceFactory: AiEventSourceFactory = {
  create(url) {
    return new MockAiEventSource(url);
  }
};

export function resolveAiEventSourceFactory(EventSourceImpl?: AiEventSourceConstructor): AiEventSourceFactory | null {
  if (EventSourceImpl) return { create: (url) => new EventSourceImpl(url) };
  if (isMockModeEnabled()) return mockAiEventSourceFactory;
  return typeof EventSource === 'undefined' ? null : nativeAiEventSourceFactory;
}
