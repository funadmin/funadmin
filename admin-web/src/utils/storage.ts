/**
 * 本地存储封装：自动 JSON 序列化、过期时间支持
 */
interface StorageData<T> {
  value: T;
  expire?: number;
}

class Storage {
  constructor(private storage: globalThis.Storage) {}

  set<T>(key: string, value: T, expireSeconds?: number): void {
    const data: StorageData<T> = { value };
    if (expireSeconds) {
      data.expire = Date.now() + expireSeconds * 1000;
    }
    this.storage.setItem(key, JSON.stringify(data));
  }

  get<T>(key: string, defaultValue: T | null = null): T | null {
    const raw = this.storage.getItem(key);
    if (!raw) return defaultValue;
    try {
      const data = JSON.parse(raw) as StorageData<T>;
      if (data.expire && data.expire < Date.now()) {
        this.remove(key);
        return defaultValue;
      }
      return data.value;
    } catch {
      return defaultValue;
    }
  }

  remove(key: string): void {
    this.storage.removeItem(key);
  }

  clear(): void {
    this.storage.clear();
  }
}

export const local = new Storage(window.localStorage);
export const session = new Storage(window.sessionStorage);
