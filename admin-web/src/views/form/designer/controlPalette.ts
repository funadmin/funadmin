import type { ControlMeta } from '../registry';

const icons: Record<string, string> = {
  input: 'i-ep-edit', password: 'i-ep-lock', textarea: 'i-ep-document', mention: 'i-ep-chat-dot-round', number: 'i-ep-sort-up',
  select: 'i-ep-arrow-down', selectV2: 'i-ep-menu', treeSelect: 'i-ep-share', cascader: 'i-ep-guide', radio: 'i-ep-aim',
  checkbox: 'i-ep-check', switch: 'i-ep-switch-button', transfer: 'i-ep-d-arrow-right', date: 'i-ep-calendar', datetime: 'i-ep-date',
  daterange: 'i-ep-date', datetimerange: 'i-ep-date', time: 'i-ep-timer', timeSelect: 'i-ep-watch', slider: 'i-ep-operation',
  rate: 'i-ep-star', color: 'i-ep-price-tag', image: 'i-ep-picture', images: 'i-ep-picture-rounded', file: 'i-ep-document-copy',
  files: 'i-ep-files', dictionary: 'i-ep-collection-tag', relation: 'i-ep-connection', department: 'i-ep-office-building', user: 'i-ep-user',
  richtext: 'i-ep-edit-pen', json: 'i-ep-brackets', hidden: 'i-ep-view', readonly: 'i-ep-reading', repeatable: 'i-ep-copy-document',
  subform: 'i-ep-tickets', group: 'i-ep-folder', grid: 'i-ep-grid', divider: 'i-ep-minus', text: 'i-ep-document',
  collapse: 'i-ep-fold', tabs: 'i-ep-menu'
};

export const controlIcon = (control: Pick<ControlMeta, 'type'>): string => icons[control.type] || 'i-ep-component';
