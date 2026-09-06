#!/usr/bin/env node

/**
 * 已弃用的 Node CRUD 入口。
 *
 * CRUD 的唯一权威实现位于 PHP app/common/crud；此入口故意不再生成文件，
 * 避免 PHP 与 Node 两套规则继续分叉。
 */
console.error(
  'Node CRUD 生成器已弃用。请在项目根目录使用 PHP CLI：php think crud <definition.json>；默认仅 dry-run。'
);
process.exitCode = 1;
