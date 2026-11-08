import { cmsTranslate } from '@/composables/useCmsI18n';
import type { ConfigurationField, ConfigurationType, ConfigurationScalarType } from '../types';

export const scalarConfigurationTypes: Array<{ value: ConfigurationScalarType; label: string }> = [
  { value: 'text', get label() { return cmsTranslate('cms.labels.singleLineText'); } }, { value: 'textarea', get label() { return cmsTranslate('cms.labels.multilineText'); } },
  { value: 'image', get label() { return cmsTranslate('cms.labels.image'); } }, { value: 'url', get label() { return cmsTranslate('cms.labels.linkUrl'); } },
  { value: 'email', get label() { return cmsTranslate('cms.labels.email'); } }, { value: 'number', get label() { return cmsTranslate('cms.labels.number'); } },
  { value: 'boolean', get label() { return cmsTranslate('cms.labels.switch'); } }, { value: 'color', get label() { return cmsTranslate('cms.labels.color'); } }, { value: 'select', get label() { return cmsTranslate('cms.labels.options'); } },
];
export function configurationDefault(type: ConfigurationType): ConfigurationField['default'] {
  return type === 'list' ? [] : type === 'boolean' ? false : type === 'number' ? 0 : '';
}
export function configurationChild(key = '', label = '', type: ConfigurationScalarType = 'text'): ConfigurationField {
  return { key, label, type, group: cmsTranslate('cms.labels.itemFields'), localized: false, default: configurationDefault(type), options: [] };
}
export const configurationPresets = [
  { key: 'partners', get label() { return cmsTranslate('cms.labels.partners'); }, fields: () => [configurationChild('name', cmsTranslate('cms.labels.name2')), configurationChild('logo', 'Logo', 'image'), configurationChild('url', cmsTranslate('cms.labels.link'), 'url')] },
  { key: 'contacts', get label() { return cmsTranslate('cms.labels.contacts'); }, fields: () => [configurationChild('name', cmsTranslate('cms.labels.name')), configurationChild('phone', cmsTranslate('cms.labels.phone')), configurationChild('email', cmsTranslate('cms.labels.email'), 'email'), configurationChild('qrcode', cmsTranslate('cms.labels.contactQrCode'), 'image')] },
  { key: 'slides', get label() { return cmsTranslate('cms.labels.slideshow'); }, fields: () => [configurationChild('title', cmsTranslate('cms.labels.title')), configurationChild('image', cmsTranslate('cms.labels.slideImage'), 'image'), configurationChild('mobile_image', cmsTranslate('cms.labels.mobileImage'), 'image'), configurationChild('url', cmsTranslate('cms.labels.targetUrl'), 'url')] },
  { key: 'ads', get label() { return cmsTranslate('cms.labels.advertisements'); }, fields: () => [configurationChild('name', cmsTranslate('cms.labels.name2')), configurationChild('position', cmsTranslate('cms.labels.adPlacement')), configurationChild('image', cmsTranslate('cms.labels.adImage'), 'image'), configurationChild('url', cmsTranslate('cms.labels.targetUrl'), 'url')] },
];
