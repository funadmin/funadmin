declare global {
  interface Window {
    __APP_INFO__: {
      name: string;
      version: string;
      buildTime: string;
    };
  }

  type Recordable<T = any> = Record<string, T>;
  type Nullable<T> = T | null;
  type Awaitable<T> = T | Promise<T>;
  type DeepPartial<T> = { [P in keyof T]?: DeepPartial<T[P]> };
}

export {};
