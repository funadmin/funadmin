declare global {
  interface Window {
    __APP_INFO__: {
      name: string;
      version: string;
      buildTime: string;
    };
  }

  type Recordable<T = any> = Record<string, T>;
}

export {};
