import { describe, expect, it } from 'vitest';
import { suggestControl } from './suggestControl';

describe('suggestControl', () => {
  it.each([
    ['created_at', 'int', 'datetime'],
    ['published_at', 'datetime', 'datetime'],
    ['start_time', 'DATETIME', 'datetime'],
    ['login_time', 'timestamp', 'datetime'],
    ['status', 'tinyint', 'radio'],
    ['order_status', 'tinyint(1)', 'radio'],
    ['cover', 'varchar(500)', 'image'],
    ['image', 'varchar(255)', 'image'],
    ['avatar', 'varchar(255)', 'image'],
    ['user_avatar', 'varchar(255)', 'image'],
    ['content', 'text', 'richtext'],
    ['content', 'longtext', 'richtext'],
    ['article_content', 'mediumtext', 'richtext']
  ])('%s (%s) → %s', (field, columnType, expected) => {
    expect(suggestControl(field, columnType)).toBe(expected);
  });

  it.each([
    ['title', 'varchar(255)'],
    ['content', 'varchar(255)'],
    ['status_text', 'varchar(50)'],
    ['images', 'json'],
    ['price', 'decimal(10,2)']
  ])('%s (%s) → null', (field, columnType) => {
    expect(suggestControl(field, columnType)).toBeNull();
  });
});
