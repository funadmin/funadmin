const toString = Object.prototype.toString;

export const is = (val: unknown, type: string): boolean =>
  toString.call(val) === `[object ${type}]`;

export const isDef = <T>(val: T): val is NonNullable<T> => val !== undefined && val !== null;
export const isNull = (val: unknown): val is null => val === null;
export const isUndef = (val: unknown): val is undefined => val === undefined;
export const isEmpty = (val: unknown): boolean => {
  if (val === null || val === undefined || val === '') return true;
  if (Array.isArray(val) && val.length === 0) return true;
  if (typeof val === 'object' && Object.keys(val as object).length === 0) return true;
  return false;
};

export const isString = (val: unknown): val is string => is(val, 'String');
export const isNumber = (val: unknown): val is number => is(val, 'Number');
export const isBoolean = (val: unknown): val is boolean => is(val, 'Boolean');
export const isArray = Array.isArray;
export const isObject = (val: unknown): val is Record<string, unknown> =>
  val !== null && is(val, 'Object');
export const isFunction = (val: unknown): val is Function => typeof val === 'function';
export const isPromise = <T = any>(val: unknown): val is Promise<T> =>
  is(val, 'Promise') && isObject(val) && isFunction((val as any).then) && isFunction((val as any).catch);

export const isExternal = (path: string): boolean => /^(https?:|mailto:|tel:|\/\/)/.test(path);
export const isClient = typeof window !== 'undefined';
