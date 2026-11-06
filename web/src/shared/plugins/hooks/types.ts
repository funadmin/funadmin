export type HookAudience = 'public' | 'member' | 'admin';
export interface HookDescriptor {
    id: string;
    point: string;
    type: 'action' | 'filter' | 'component';
    priority?: number;
    file?: string;
    export?: string;
    component?: string;
    audiences?: HookAudience[];
    permission?: string;
}
export interface HookModule {
    code: string;
    version: string;
    generation?: string;
    components: Record<string, string>;
    clientHooks: HookDescriptor[];
    routes?: unknown[];
}
export interface HookCalls {
    action(point: string, payload?: unknown, context?: HookHostContext): Promise<void>;
    filter<T>(point: string, value: T, context?: HookHostContext): Promise<T>;
}
export interface HookHostContext {
    signal?: AbortSignal;
    [key: string]: unknown;
}
export interface HookContext extends HookHostContext {
    pluginCode: string;
    point: string;
    id: string;
    signal: AbortSignal;
    hooks: HookCalls;
    onDispose(cleanup: () => void): void;
}
export type SourceModules = Record<string, () => Promise<any>>;
