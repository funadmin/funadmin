import type { FormValueType } from './types';

export const decodeValue = (type: FormValueType, value: unknown): unknown => {
  if (value === null || value === undefined || value === '') {
    if (type === 'array') return [];
    if (type === 'boolean') return false;
    return value;
  }
  if (type === 'boolean') return value === true || value === 1 || value === '1';
  if (type === 'number') return Number(value);
  if ((type === 'array' || type === 'object') && typeof value === 'string') {
    try {
      const parsed = JSON.parse(value);
      if (type === 'array') return Array.isArray(parsed) ? parsed : [];
      return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch {
      return type === 'array' ? [] : {};
    }
  }
  return value;
};

export const encodeValue = (type: FormValueType, value: unknown): unknown => {
  if (type === 'boolean') return value ? 1 : 0;
  if (type === 'number') return value === '' || value === null || value === undefined ? null : Number(value);
  if (type === 'array' || type === 'object') return JSON.stringify(value ?? (type === 'array' ? [] : {}));
  return value;
};
