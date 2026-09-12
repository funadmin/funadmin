import type { ComposerTranslation } from 'vue-i18n';

export type AiEnumGroup = 'statuses' | 'riskLevels' | 'operations' | 'fileStatuses' | 'taskStages' | 'taskTypes' | 'changeSetStatuses';

export function aiEnumLabel(t: ComposerTranslation, group: AiEnumGroup, value: string): string {
  const key = `aiDevelopment.enums.${group}.${value}`;
  const translated = t(key, {}, { missingWarn: false, fallbackWarn: false });
  return translated === key ? value : translated;
}
