import { useI18n } from 'vue-i18n';
import { i18n } from '@/locales';

/** Resolve the host's installed Composer, including in external plugin sources. */
export function useCmsI18n() {
  return useI18n({ useScope: 'global' });
}

/** Standalone validation and API helpers resolve the active locale when called. */
export function cmsTranslate(key: string, values: Record<string, unknown> = {}): string {
  return i18n.global.t(key, values);
}
