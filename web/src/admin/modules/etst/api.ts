import http from '@/utils/http';
import type { Item } from './types';

export const listItems = () => http.get<Item[]>('/plugin/etst/index');
