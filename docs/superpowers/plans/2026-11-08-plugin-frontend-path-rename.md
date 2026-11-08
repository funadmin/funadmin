# Plugin frontend path rename

User requested changing plugin frontend source directories from `admin/modules` to `admin/plugins` globally.

## Contract

- Plugin package frontend source: `admin/plugins/{code}/web/src/admin/plugins/{code}`.
- Host frontend source: `web/src/admin/plugins/{code}`.
- Manifest semantic field remains `adminWeb`; only its `source` path changes.
- Host alias changes from `@/modules` to `@/plugins`; unrelated `store/modules` paths remain unchanged.
- Publisher, modification detector, scaffolder, CRUD target validation, hook glob, build/check scripts, fixtures, docs and tests all use the new path.
- Preserve concurrent dirty work; no business DB, migrations, roles, commits or pushes.
