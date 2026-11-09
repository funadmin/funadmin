# Form v1 Projection Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make FormSchema v2 nodes the only field model in FunAdmin. Delete the v1 field projection (`field_name`/`control_props`/`options_source`/`list_*`/`form_*` rows) from the designer, the publish/DDL pipeline, the runtime data API, generated code and the `fun_form_field` table (FORM-6, FORM-8, plus FORM-9).

**Architecture:** On the backend, `FormSchema::fields()` returns `FormFieldView` objects: read-only wrappers over the compiled field nodes, and the only place where field defaults live. Validation, DDL, publish, the CRUD factory and `FormDataService` all read views. On the frontend, consumers read `FormSchemaNode` through `fieldNodes()`, and the designer edits nodes directly through shared `nodeEdits` helpers. Frontend consumers move off the `fields` API keys before the backend stops sending them, so the app works after every task.

**Tech Stack:** webman PHP 8 (PHPUnit 11, Illuminate DB, SQLite in tests), Vue 3 + TypeScript + Element Plus (Vitest, vue-tsc).

**Spec:** `docs/superpowers/specs/2026-11-09-form-v1-projection-removal-design.md`

## Global Constraints

- The FormSchema v2 canonical document must not change. Every existing `schema_hash` stays valid, and `fun_form_schema_version` is never rewritten.
- No v1 key names (`field_name`, `label` as a field key, `column_type`, `control_props`, `options_source`, `validate_rules`, `link_rules`, `relation_*`, `list_show|sort|filter|formatter|width`, `form_show|required|group|span|readonly`, `sort_order` as a field key) in new code, types, API responses or generated files.
- No compat shims: no fallbacks for old payloads, no dual reads, no `?? $legacy`.
- Field traversal rule, the same on both sides: document order. A `kind: 'layout'` node is skipped and its children are visited. Any other node is a field. Children of `repeatable`/`subform` are not visited.
- Backend defaults (in `FormFieldView` only): `nullable` true, `unsigned` false, `index` `'none'`, `relationType` `'none'`, `relationOnDelete` `'restrict'`, `relationValueField` raw (`''` when absent), `span` 24 when `layout.span` is not an int, `listWidth` 0, strings `''`, `defaultValue` null.
- The DDL keeps today's fallbacks: an empty `columnType` uses the field capability's `defaultColumnType`, and an empty belongs_to `relationValueField` references `id`.
- Tasks never run git. "Commit" steps are handed to the controller.
- Backend commands run in `/Users/admin/min/funadmin/admin`; frontend commands run in `/Users/admin/min/funadmin/web`.
- `npx vue-tsc --noEmit` must show no errors outside the known MenuItemsEditor / ProviderSettingsDrawer baseline.
- Before touching `web/src/admin/views/form/components/SchemaNodeRenderer.vue` or `SchemaRenderer.vue`, check `git status` for edits from another session. Stop and report if they are dirty.

## Review Focus

1. **Editing a field in the designer must not wipe node data.** Today `updateField` resets `events` to `{}` and recomputes `valueType`. After Task 9, a PropsPanel edit must keep `events`, `access`, `attrs`, `info`, `valueType`. Test in Task 9.
2. **Field access now comes from `node.access`, not `control_props.schemaAccess`.** A field with `access.include: 'never'`, or with a `write` permission the user lacks, must be left out of the submitted payload (frontend) and rejected or ignored on write (backend). Tests in Task 5 and Task 11.
3. **DDL fallbacks.** A created-table belongs_to field without `relation.valueField` must still emit `REFERENCES … (\`id\`)`. A field with an empty `columnType` must still get its capability default. Test in Task 2.
4. **Nested fields.** A field inside a `group`/`tabs` layout must appear in runtime list columns, filters, import mapping and submission. A child of `repeatable`/`subform` must not appear at top level. Tests in Task 1 and Task 4.
5. **Layout edits land in the v2 slot.** Changing span/group on a layout or field node must write `layout.span`/`layout.group`, never `props.form_span`/`props.form_group`. Test in Task 9.

---

### Task 1: `FormFieldView` and `FormSchema::fields()`

**Files:**
- Create: `admin/app/common/form/schema/FormFieldView.php`
- Modify: `admin/app/common/form/schema/FormSchema.php` (add `fields()`, `field()`; keep `fieldProjection()` for now)
- Delete: `admin/app/common/form/schema/FormNode.php` (no references; confirm with `grep -rn "FormNode\b" app tests`)
- Test: `admin/tests/FormFieldViewTest.php`, `admin/tests/FormFieldViewParityTest.php` (temporary, deleted in Task 12)

**Interfaces:**
- Produces: `FormFieldView` with the full method list in spec §7; `FormSchema::fields(): list<FormFieldView>` (memoised); `FormSchema::field(string $name): ?FormFieldView`.

- [ ] **Step 1: Write the failing tests.** Build schemas with `app\common\form\builder\Form` or literal v2 arrays compiled via `new FormSchemaRepository()`.
  - `test_defaults_for_bare_field_node`: a node with only `id/kind/type/field/title` gives `nullable() === true`, `unsigned() === false`, `index() === 'none'`, `relationType() === 'none'`, `relationOnDelete() === 'restrict'`, `relationValueField() === ''`, `span() === 24`, `listWidth() === 0`, `defaultValue() === null`, `props() === []`, `access() === []`.
  - `test_traversal_skips_layout_and_hides_container_children`: nodes `[group{children:[a]}, b, repeatable r{children:[c]}]` give names `['a','b','r']`.
  - `test_required_and_rules`: validation `[{type:'required'},{type:'maxLength',value:32},{type:'email'}]` gives `required() === true` and `rules() === ['maxLength' => 32, 'email' => true]`.
  - `test_responsive_span_falls_back_to_24`: `layout.span = {xs:24, md:12}` gives `span() === 24`; `layout.span = 8` gives `8`.
  - `test_field_lookup`: `field('b')?->name() === 'b'`, `field('missing') === null`.
  - Parity test `test_views_match_projection`: for a fixture that uses relation, list, layout, access, dataSource, conditions and validation, compare each view with the projection row of the same name: `columnType↔column_type`, `nullable↔nullable(1/0)`, `unsigned`, `index↔index_type`, `relation*↔relation_*`, `list*↔list_*`, `hidden↔!form_show`, `disabled↔form_readonly`, `required↔form_required`, `span↔form_span`, `group↔form_group`, `rules↔validate_rules ?? []`, `access↔control_props.schemaAccess`, `props↔control_props minus schemaAccess`, `dataSource↔options_source`, `conditions↔link_rules.rules`, `title↔label`. Projection rows for layout nodes are excluded.
- [ ] **Step 2: Run, expect failure.** `vendor/bin/phpunit --filter 'FormFieldViewTest|FormFieldViewParityTest'`. Expected: class `FormFieldView` not found.
- [ ] **Step 3: Implement `FormFieldView` and `FormSchema::fields()/field()`.** `fields()` walks `document()['nodes']` with the traversal rule above and caches the result in a private nullable property.
- [ ] **Step 4: Run, expect pass.** Same command. Then `vendor/bin/phpunit --filter 'FormSchema|FormModelAttributeSave'`. Expected: PASS.
- [ ] **Step 5: Commit** (controller): `feat(form): FormFieldView read-only view over v2 field nodes`.

### Task 2: Validation, DDL and dynamic publish read views

**Files:**
- Modify: `admin/app/admin/form/service/FormDesignerService.php` (`validateDefinition` → `validateFields`, `save`, `previewMigration`, `applyDynamicDdl`, `createTableSql`, `additiveSql`, `isVirtualField`, `columnDdl`, `migrationPath`; delete `fieldRow`, `inferFields`, `controlFromDbType`, `indexFromSchema`, plus `listing/remove/setStatus` if `grep -rn` finds no caller in `app` or `tests`; drop the `FormField` writes in `save()`; `persistCurrent` still writes them until Task 12)
- Modify: `admin/app/admin/form/service/FormPublishService.php` (delete `compatiblePayload`, `definitionPayload`; `previewDynamic`, `publishDynamic`, `assertStructure` and the injected closures use `FormSchema`)
- Test: `admin/tests/FormDynamicDdlTest.php`, `admin/tests/Console/CrudFormSchemaExecutionTest.php`, `admin/tests/BusinessCreationIntegrationTest.php` (adjust injected closures and calls)

**Interfaces:**
- Consumes: `FormSchema::fields()`, `FormFieldView` (Task 1).
- Produces:
  - `FormDesignerService::validateFields(FormSchema $schema, string $sourceType): void`. It keeps the per-field checks of the old `validateDefinition`: name regex `^[a-z][a-z0-9_]{0,60}$`, duplicates, non-empty title, known type, index enum, relation enum and identifiers, container ⇒ has_many, span 1–24, column type regex and numeric default for `created`.
  - `FormDesignerService::save(array $payload): array`. It still takes `schema_document`, `publish_config`, `status`, `remark`, `sort_order`, `schema_origin`, `schema_actor`, `schema_change_summary`, `id`, `updated_at`, `expected_schema_hash`. It no longer reads or writes `fields`, `list_config` or `form_config`. Identity regexes (form key, name, table) are checked against the compiled document.
  - `FormDesignerService::previewMigration(FormSchema $schema): array` (same return shape: `mode, sql, file, message`).
  - `FormDesignerService::applyDynamicDdl(FormSchema $schema, string $expectedDdlHash): array`.
  - `FormPublishService` constructor closures: `saveForm(array $payload): array`, `previewDdl(FormSchema): array`, `applyDdl(FormSchema, string): array`, `verifyStructure(FormSchema): void`. `assertStructure(FormSchema $schema): void` checks the physical table for each non-virtual `name()`.

- [ ] **Step 1: Write the failing tests** in `FormDynamicDdlTest`:
  - `test_belongs_to_without_value_field_references_id`: created table, field `owner_id` with `database.relation = {type:'belongs_to', table:'admin', labelField:'username'}` and no `valueField`; `previewMigration($schema)['sql']` contains ``REFERENCES `admin` (`id`)``.
  - `test_empty_column_type_uses_capability_default`: an `input` field with `database.columnType = ''` on an adopted→created path that reaches `columnDdl`; the SQL contains `` `title` varchar(255) ``.
  - Convert the existing cases to `previewMigration($compiled)` / `applyDynamicDdl($compiled, $hash)` and keep their SQL assertions byte-identical.
- [ ] **Step 2: Run, expect failure.** `vendor/bin/phpunit --filter FormDynamicDdlTest`. Expected: TypeError (array given).
- [ ] **Step 3: Implement.** Map every old key to the `FormFieldView` getter using the parity table from Task 1. `columnDdl` casts `defaultValue()` with `is_scalar($v) ? (string) $v : ''`.
- [ ] **Step 4: Run, expect pass.** `vendor/bin/phpunit --filter 'FormDynamicDdlTest|CrudFormSchemaExecutionTest|BusinessCreationIntegrationTest|BusinessDatabaseSyncTest|FormModelAttributeSaveTest'`. Expected: PASS.
- [ ] **Step 5: Verify no projection reads remain in these two services.** `grep -n "fieldProjection\|field_name\|compatiblePayload" app/admin/form/service/FormDesignerService.php app/admin/form/service/FormPublishService.php`. Expected: no output.
- [ ] **Step 6: Commit** (controller): `refactor(form): publish and DDL read FormFieldView, drop compatiblePayload`.

### Task 3: CRUD definition factory reads views; drop `layoutSchema`

**Files:**
- Modify: `admin/app/admin/development/service/FormCrudDefinitionFactory.php` (`createFromSchema`, `field`, `options`, `relation`, `rules`; delete `formSchemaNode`)
- Modify: `admin/app/common/crud/CrudDefinition.php:83`, `admin/app/common/crud/DefinitionValidator.php:21`, `admin/app/common/crud/schema/crud-definition-v1.schema.json:74`, `admin/app/common/crud/ProductionTemplateContext.php:1261-1267` (use only `formSchema.nodes`, or the existing fields→nodes fallback when there is no `formSchema`)
- Test: `admin/tests/Console/CodeGeneratorRelationsTest.php` (`PLAIN_BASELINE`), `admin/tests/Console/CodeGeneratorFormSizeTest.php`, other `tests/Console/Crud*` that assert definition hashes

**Interfaces:**
- Consumes: `FormFieldView` (Task 1).
- Produces: `createFromSchema(FormSchema $formSchema, array $form, array $publishConfig = [], array $schema = []): CrudDefinition` (same signature). `$form` supplies only `name`, `remark`, `publish_config`, `connection`. The definition has no `layoutSchema` key.

- [ ] **Step 1: Write the failing test.** In `CodeGeneratorRelationsTest`, add `test_definition_has_no_layout_schema`: `assertArrayNotHasKey('layoutSchema', $definition->toArray())` for the plain fixture.
- [ ] **Step 2: Run, expect failure.** `vendor/bin/phpunit --filter CodeGeneratorRelationsTest`.
- [ ] **Step 3: Implement.** Iterate `$formSchema->fields()`. Has-many detection uses `relationType() === 'has_many'`. `rules()` comes from `FormFieldView::rules()` with the existing string filter. Option source keys stay `label_field`/`value_field`, read from `dataSource()`; those are v2 data-source keys, not projection keys.
- [ ] **Step 4: Re-record `PLAIN_BASELINE`.** Update only the two `definitionHash` values. Every `files` hash must stay unchanged; if one changes, stop and investigate.
- [ ] **Step 5: Run, expect pass.** `vendor/bin/phpunit --filter 'CodeGenerator|Crud|BusinessGeneration|ManagedGeneration'`. Expected: PASS.
- [ ] **Step 6: Commit** (controller): `refactor(codegen): CRUD factory reads FormFieldView; remove layoutSchema`.

### Task 4: Frontend node types, `fieldNodes`, `nodeEdits`

**Files:**
- Modify: `web/src/admin/views/form/schema/types.ts` (add `access?`, `FormSchemaDatabase`, `FormSchemaRelationConfig`, `FormSchemaListColumn`; type `database?`/`list?`)
- Modify: `web/src/admin/api/form.ts` (delete the local `FormSchemaNode`, `FormSchemaNodeKind`, rule types; re-export from `views/form/schema/types`)
- Create: `web/src/admin/views/form/schema/fieldNodes.ts`, `web/src/admin/views/form/schema/nodeEdits.ts`
- Modify: `web/src/admin/views/development/business/codegen/fieldTable.ts` (import `changeControl`/`withRequired` from `nodeEdits`)
- Test: `web/src/admin/views/form/schema/fieldNodes.spec.ts`, `web/src/admin/views/form/schema/nodeEdits.spec.ts`, existing `codegen/fieldTable.spec.ts`

**Interfaces:**
- Produces:
  - `fieldNodes(nodes: FormSchemaNode[]): FormSchemaNode[]`
  - `isSensitiveNode(node): boolean` (`type === 'password' || props.sensitive || props.writeOnly`, where truthy means `true | 1 | '1'`)
  - `isRequiredNode(node): boolean`
  - `relationTypeOf(node): 'none' | 'belongs_to' | 'has_many'`
  - `columnTypeOf(node): string`
  - `withRequired(node, required): FormSchemaNode`
  - `patchList(node, Partial<FormSchemaListColumn>): FormSchemaNode`
  - `patchDatabase(node, Partial<FormSchemaDatabase>): FormSchemaNode`
  - `patchRelation(node, Partial<FormSchemaRelationConfig>): FormSchemaNode`
  - `patchLayout(node, { span?: number; group?: string }): FormSchemaNode`
  - `changeControl(node, type, opts: { resetColumnType: boolean }): FormSchemaNode`
  - All edit helpers are pure and return the same reference when nothing changes.

- [ ] **Step 1: Write the failing tests.**
  - `fieldNodes`: `[group{children:[a]}, b, repeatable r{children:[c]}]` gives `['a','b','r']` by `.field`.
  - `isSensitiveNode`: true for `password`, `props.writeOnly: 1`, `props.sensitive: '1'`; false otherwise.
  - `patchLayout(n, {span: 12})` gives `n.layout.span === 12` and leaves `props` untouched. `patchRelation` merges into `database.relation` and keeps `columnType`.
  - `changeControl(n, 'select', {resetColumnType: true})` sets `database.columnType` to `'varchar(100)'`; with `false` it keeps the old column type. Both reset `props`/`dataSource` to the registry defaults.
  - `withRequired(n, true)` puts `required` first; `withRequired(n, false)` removes it; unchanged input returns the same reference.
- [ ] **Step 2: Run, expect failure.** `npx vitest run src/admin/views/form/schema/fieldNodes.spec.ts src/admin/views/form/schema/nodeEdits.spec.ts`.
- [ ] **Step 3: Implement both modules and retype the node.** Fix the type errors this causes with node-typed reads only, without behaviour changes.
- [ ] **Step 4: Run, expect pass.** `npx vitest run src/admin/views/form/schema src/admin/views/development/business/codegen && npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** (controller): `feat(form): typed v2 node slots, fieldNodes and nodeEdits helpers`.

### Task 5: Submission policy on nodes; generator stops emitting `submissionFields`

**Files:**
- Modify: `web/src/admin/views/form/runtime/submissionPolicy.ts` (node-based; drop the `form_config` fallback in `resolveSubmissionInclude`; `isSensitiveField` is replaced by `isSensitiveNode` from Task 4)
- Modify: `web/src/admin/views/form/components/ListSourceTree.vue:121,135` (source fields are `{field_name,label}` until Task 8; pick the values by name locally and stop calling `buildSubmissionPayload`/`emptyRuntimeValues` with them)
- Modify: `web/src/admin/views/form/data.vue`, `web/src/admin/views/form/published.vue` (call sites only: pass `meta.schema.nodes`)
- Modify: `admin/app/common/crud/ProductionTemplateContext.php:1341-1356,1374-1376,1383`
- Modify (generated): `web/src/admin/views/business/order/components/OrderForm.vue`, `web/src/admin/plugins/example/test/components/TestForm.vue`, `web/src/admin/views/business/i18n-demo/components/I18nDemoForm.vue` (hand-apply the new template output; per owner decision Q2, delete the i18n-demo directory, its `api/business/i18n-demo.ts` and its references in `menu-tree.spec.ts:261`, `generatedListButtons.spec.ts:135`, `MenuHierarchyIntegrationTest.php:33` instead)
- Test: `web/src/admin/views/form/runtime/submissionPolicy.spec.ts`, `web/tests/contracts/form-publish.spec.ts`, `admin/tests/Console/CrudFrontendWorkspaceTest.php`, `admin/tests/Console/CodeGeneratorRelationsTest.php` (`*Form.vue` hashes)

**Interfaces:**
- Consumes: `fieldNodes`, `isSensitiveNode` (Task 4).
- Produces:
  - `buildSubmissionPayload(nodes: FormSchemaNode[], values, include?: string[], can?: (p: string) => boolean): Record<string, unknown>`. It flattens with `fieldNodes`, excludes `hidden` type, `disabled` nodes, `props.disabled/computed/primary/system`, `dataSource.kind === 'computed'`, and anything with `access.include === 'never'` or a missing `access.write` permission, unless the node is in `include` or has `access.include === 'always'`.
  - `sanitizeRuntimeRecord(nodes, record)`, `emptyRuntimeValues(nodes)` (same per-type defaults as today, using `defaultValue ?? ''`), `resolveSubmissionInclude(form?: { schema_document?: { submit?: { include?: unknown } } | null }): string[]`.
  - Generated `*Form.vue` calls `buildSubmissionPayload(sourceSchema.nodes, form, resolveSubmissionInclude({schema_document:sourceSchema}), hasPermission)` and has no `submissionFields` and no `FormFieldDef` import.

- [ ] **Step 1: Write the failing tests.**
  - `submissionPolicy.spec.ts`: rewrite the fixtures as nodes. New cases: `excludes access.include never`, `excludes node whose access.write permission is missing`, `includes nested field inside group`, `ignores children of repeatable`, `resolveSubmissionInclude ignores form_config`.
  - `CrudFrontendWorkspaceTest`: the generated form source `assertStringNotContainsString('submissionFields', …)`, `assertStringNotContainsString('FormFieldDef', …)`, `assertStringContainsString('buildSubmissionPayload(sourceSchema.nodes,', …)`.
- [ ] **Step 2: Run, expect failure.** `npx vitest run src/admin/views/form/runtime/submissionPolicy.spec.ts` and `vendor/bin/phpunit --filter CrudFrontendWorkspaceTest`.
- [ ] **Step 3: Implement** the policy, the template change, and the call sites in `data.vue`/`published.vue` (`meta.value.schema.nodes`).
- [ ] **Step 4: Update the generated files** (order, example; i18n-demo per Q2) so they match the template exactly. Re-record only the `*Form.vue` hashes in `PLAIN_BASELINE`.
- [ ] **Step 5: Run, expect pass.** `npx vitest run src/admin/views/form tests/contracts && npx vue-tsc --noEmit`, then `vendor/bin/phpunit --filter 'CrudFrontendWorkspaceTest|CodeGeneratorRelationsTest|CrudFormSchemaExecutionTest'`.
- [ ] **Step 6: Commit** (controller): `refactor(form): submission policy on nodes; generated forms drop submissionFields`.

### Task 6: Runtime pages, import dialog and member dialog read schema nodes

**Files:**
- Modify: `web/src/admin/views/form/data.vue`, `web/src/admin/views/form/published.vue` (`formFields` → `computed(() => fieldNodes(meta.value?.schema.nodes ?? []))`; templates read `node.field`, `node.title`, `node.list?.filter|formatter|width|sort|show`, `relationTypeOf(node)`, `columnTypeOf(node)`, `node.hidden`)
- Modify: `web/src/admin/views/form/runtime/fieldPresentation.ts` (delete `presentationNodes`; callers pass field nodes directly)
- Modify: `web/src/admin/views/form/components/FormDataImportDialog.vue` (prop `fields: FormSchemaNode[]`)
- Modify: `web/src/admin/views/system/member/components/MemberFormDialog.vue` (options come from `schema.nodes[].dataSource.options`; the load guard no longer requires `fields`)
- Test: `web/src/admin/views/form/runtime/fieldPresentation.spec.ts`, `web/tests/views/form-page-host-lifecycle.spec.ts`, `web/src/admin/views/system/member/components/MemberFormDialog.spec.ts`, `web/src/shared/components/DataTable/unifiedPages.spec.ts`

**Interfaces:**
- Consumes: `fieldNodes`, `isSensitiveNode`, `relationTypeOf`, `columnTypeOf` (Task 4); node-based submission policy (Task 5).
- Produces: runtime pages and the member dialog no longer read `meta.fields` / `result.fields`. The backend may still send them until Tasks 11–12.

- [ ] **Step 1: Write the failing tests.** In the page lifecycle spec, mock `meta` **without** `fields` and with a field nested in a `group` that has `list.show: true, list.filter: 'like'`. Assert that the column and the filter input render. `MemberFormDialog.spec.ts`: a response without `fields` still loads the group options from the schema.
- [ ] **Step 2: Run, expect failure.** `npx vitest run tests/views/form-page-host-lifecycle.spec.ts src/admin/views/system/member`.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, expect pass.** `npx vitest run src/admin/views/form src/admin/views/system/member tests/views src/shared/components/DataTable && npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** (controller): `refactor(form): runtime pages and member dialog read schema nodes`.

### Task 7: Renderer chain takes `node`; delete `SchemaForm.vue`

**Files:**
- Modify: `web/src/admin/views/form/components/SchemaNodeRenderer.vue` (delete `legacyField` and its `FormFieldDef` import), `RegisteredControlRenderer.vue`, `CoreControlAdapter.vue`, `FormControlRenderer.vue` (prop `node: FormSchemaNode`; the `schemaNode` prop goes; reads `node.type/title/props/defaultValue/list?.formatter/database?.relation?.multiple/database?.relation?.valueField`; placeholder is `props.placeholder || title`), `RepeatableField.vue` (prop `node`)
- Modify: `web/src/admin/views/form/designer/components/DesignerCanvasNode.vue` (pass `node`; preview options from `node.dataSource?.options`)
- Delete: `web/src/admin/views/form/components/SchemaForm.vue`, `SchemaForm.spec.ts`, `web/src/admin/views/form/linkRules.ts`
- Test: `web/src/admin/views/form/components/FormControlRenderer.spec.ts`, `web/src/admin/views/form/designer/components/DesignerCanvas.spec.ts`

**Interfaces:**
- Produces: `FormControlRenderer` props `{ node: FormSchemaNode; modelValue?; options?; dataSourceState?; disabled?; readonly?; inputAttrs?; preview?; controlProps? }`. Plugin renderers still receive `schema-node` and no longer receive `field`.

- [ ] **Step 0: Run `git status web/src/admin/views/form/components`.** Stop if `SchemaNodeRenderer.vue` or `SchemaRenderer.vue` has changes from another session.
- [ ] **Step 1: Write the failing test.** In `FormControlRenderer.spec.ts`, mount with only `node` (no `field`) for `select` with `database.relation.multiple: true`. Assert that the select renders as `multiple`, and that `readonly` with `list.formatter: 'money'` formats the value.
- [ ] **Step 2: Run, expect failure.** `npx vitest run src/admin/views/form/components/FormControlRenderer.spec.ts`.
- [ ] **Step 3: Implement and delete the dead files.**
- [ ] **Step 4: Run, expect pass.** `npx vitest run src/admin/views/form && npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** (controller): `refactor(form): renderer chain consumes FormSchemaNode; remove SchemaForm`.

### Task 8: List configuration reads field nodes; `sourceMeta` DTO `{field, title}`

**Files:**
- Modify: `admin/app/admin/form/service/FormDataService.php:84-102` (`sourceMeta` items `['field' => …, 'title' => …]`)
- Modify: `web/src/admin/api/formData.ts` (`FormSourceField = { field: string; title: string }`)
- Modify: `web/src/admin/views/form/designer/components/ListConfigurationPanel.vue`, `CategoryWorkspace.vue`, `ButtonsWorkspace.vue` (if typed), `web/src/admin/views/form/components/ListSourceTree.vue` (prop `fields: FormSchemaNode[]` for designer fields; source fields `{field,title}`)
- Modify: `web/src/admin/views/form/designer/index.vue:136` (pass `fieldNodes(store.nodes.value)`)
- Test: `ListConfigurationPanel.spec.ts`, `CategoryWorkspace.spec.ts`, `ListSourceTree.spec.ts`, `admin/tests/Console/GeneratedLeftTreePermissionTest.php` (or the test that covers `sourceMeta`; find it with `grep -rln sourceMeta tests`)

**Interfaces:**
- Consumes: `fieldNodes`, `relationTypeOf`, `columnTypeOf`, `isSensitiveNode` (Task 4).
- Produces: `ListConfigurationPanel` prop `fields: FormSchemaNode[]`; `sourceMeta().fields: list<{field: string, title: string}>`.

- [ ] **Step 1: Write the failing tests.** The PHP `sourceMeta` assertion becomes `['field' => 'title', 'title' => '标题']`. `ListConfigurationPanel.spec.ts`: given nodes where one scalar field sits inside `tabs`, the target-field options include it, and a `password` node or a `props.multiple` node is excluded.
- [ ] **Step 2: Run, expect failure.** `npx vitest run src/admin/views/form/designer/components/ListConfigurationPanel.spec.ts src/admin/views/form/designer/components/CategoryWorkspace.spec.ts src/admin/views/form/components/ListSourceTree.spec.ts`, plus the PHP filter.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, expect pass.** The same commands, then `npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** (controller): `refactor(form): list configuration reads field nodes; sourceMeta uses field/title`.

### Task 9: Designer store edits nodes only (FORM-6 core, FORM-8)

**Files:**
- Modify: `web/src/admin/views/form/composables/useDesigner.ts` (rewrite per spec §7; delete everything listed in spec §6)
- Modify: `web/src/admin/views/form/registry.ts` (`createField` → `createNode(type: string, index: number, id: string): FormSchemaNode`)
- Modify: `web/src/admin/views/form/designer/components/PropsPanel.vue`, `PropsFieldFolds.vue`, `propsSummary.ts` (prop `node`, emit `Partial<FormSchemaNode>` built with the `nodeEdits` helpers; `validationEntries(node, tr)`; the conditions summary reads `node.conditions`)
- Modify: `web/src/admin/views/form/designer/index.vue` (`store.selectedNode`, `store.updateNode`; `previewValues` from `fieldNodes(store.nodes)`; `catalogDiagnostics` on nodes; `definition()` without `fields`; `load(data.form)` and `markSaved(...)` without `fields`; conflict review without `fields`)
- Test: `PropsPanel.spec.ts`, `designerShell.spec.ts`, `saveConflict.spec.ts`, `DesignerCanvas.spec.ts`, `DesignerLeftPanel.spec.ts`, `pluginCatalog.spec.ts`, `structuredEditor.spec.ts`, new `web/src/admin/views/form/composables/useDesigner.spec.ts`

**Interfaces:**
- Consumes: `nodeEdits`, `fieldNodes` (Task 4); node-based `FormControlRenderer` (Task 7); node-based `ListConfigurationPanel` (Task 8).
- Produces: `useDesigner()` returns `{ form, nodes, selectedNodeId, selectedNode, flattenedNodes, fieldNodes, schemaDocument, dirty, saveStatus, beginSave, failSave, acknowledgeSave, canUndo, canRedo, historyDepth, undo, redo, findNode, selectNode, addNode, removeNode, duplicateNode, moveNode, moveNodeByKeyboard, updateNode, updateForm, updateList, replaceSchema, load, markSaved }`. Snapshots hold `{ nodes, form }`. `load(definition)` throws `Error` when `definition.schema_document?.schemaVersion !== 2`. `addNode` gives new fields a unique `field_<n>` across `fieldNodes(nodes)`; `duplicateNode` gives each copied field a unique `<name>_copy[_n]` and a title of `copySuffix`.

- [ ] **Step 1: Write the failing tests** in `useDesigner.spec.ts`:
  - `update keeps events, access, attrs, info and valueType`: load a node with `events: {change: [{type:'x'}]}`, `access: {write: ['a']}`, `attrs: {id:'k'}`, `info: 'i'`, `valueType: 'string'`; `updateNode(patchList(node, {show: false}))`; all five are still deep-equal.
  - `layout span edit writes layout.span`: select a `group` node, apply `patchLayout(node, {span: 12})`; the node has `layout.span === 12` and `props.form_span === undefined`.
  - `load rejects non-v2`: `expect(() => store.load({ ...form, schema_document: null })).toThrow()`.
  - `duplicate gives unique field names`: duplicating twice gives `a_copy` and `a_copy_2`.
  - `undo restores nodes after rename`.
  - `PropsPanel.spec.ts`: rewrite the fixtures as nodes. Each `data-prop`/`data-toggle` emits the matching node patch (`field_name` input → `{field}`, `list_show` toggle → `{list:{…show}}`, `form_required` toggle → `{validation}`, `relation_table` → `{database:{relation}}`, `form_span` → `{layout}`). The `data-*` attribute values do not change.
- [ ] **Step 2: Run, expect failure.** `npx vitest run src/admin/views/form/composables src/admin/views/form/designer`.
- [ ] **Step 3: Implement** the store, `createNode`, the PropsPanel family and the `index.vue` wiring.
- [ ] **Step 4: Run, expect pass.** `npx vitest run src/admin/views/form && npx vue-tsc --noEmit`.
- [ ] **Step 5: Commit** (controller): `refactor(form-designer): edit FormSchema nodes directly; drop v1 field model and v1 load branch`.

### Task 10: Delete the frontend v1 types

**Files:**
- Modify: `web/src/admin/api/form.ts` (delete `FormFieldDef`; delete `fields`, `fields_count`, `list_config`, `form_config` from `FormDefinition`; delete `projection` from `FormSchemaCompileResult`), `web/src/admin/api/formData.ts` (`FormDataMeta.fields`), `web/src/admin/api/development/business.ts:166` (`BusinessModuleDetail.fields`), `web/src/admin/api/system/member.ts:18`
- Modify: `web/src/admin/mock/modules/developmentBusiness.ts:20` (inspect mock returns `{name, dbType, …}` like the backend `inspectDatabase`)
- Modify: any spec fixture still typed as `FormFieldDef`
- Test: `npx vue-tsc --noEmit` is the test

**Interfaces:**
- Produces: no `FormFieldDef` symbol anywhere under `web/src` and `web/tests`.

- [ ] **Step 1: Delete the types.** Run `npx vue-tsc --noEmit` and expect errors only where specs or fixtures still use them.
- [ ] **Step 2: Fix the remaining fixtures.**
- [ ] **Step 3: Verify.** `grep -rnE "FormFieldDef|field_name|control_props|options_source|list_show|form_readonly|form_config|list_config|legacyField|projectFields|fieldToNode" src tests --include='*.ts' --include='*.vue'` gives no hits outside `src/admin/plugins/cms`. Then `npx vitest run && npx vue-tsc --noEmit`.
- [ ] **Step 4: Commit** (controller): `chore(form): remove v1 field types from the frontend`.

### Task 11: `FormDataService` runtime reads views; drop `meta.fields` and member `fields`

**Files:**
- Modify: `admin/app/admin/form/service/FormDataService.php` (`fields()`, `publishedRuntime()` → `list<FormFieldView>`; every helper listed in spec §3.2 takes `FormFieldView`; the public row helpers `copyCreateValues`, `buildRules`, `revalidateAsync`, `splitPayload`, `filterPayload`, `sanitizeRecord` have no external caller (`grep` confirms), so they take `list<FormFieldView>` and become `private`; `meta()` no longer returns `fields`; `assertLeftTreeUnreferenced` iterates `$compiled->fields()`)
- Modify: `admin/app/admin/system/service/MemberFormDefinition.php:39` (`['schema' => …]` only)
- Test: `admin/tests/Console/GeneratedLeftTreePermissionTest.php`, `GeneratedListActionPermissionTest.php`, `ListActionCatalogPendingKeysTest.php`, `CrudFormSchemaExecutionTest.php`, `BusinessGenerationArchiveTest.php`, `MemberHttpTest.php`, `MemberIntegrationTest.php`, new `admin/tests/FormDataRuntimeFieldViewTest.php`

**Interfaces:**
- Consumes: `FormFieldView` (Task 1). The frontend no longer reads `meta.fields` (Task 6).
- Produces: `meta()` keys `form, primaryKey, schema, schemaHash, etag, categoryOptions, recycleCapable`.

- [ ] **Step 1: Write the failing tests** in `FormDataRuntimeFieldViewTest` (SQLite, `tests/Support/FormMetadataTables.php`):
  - `meta_has_no_fields_key`.
  - `write_ignores_field_with_access_include_never`: create a record with a value for that field; the stored value is the column default.
  - `write_drops_field_without_write_permission`: with `access.write: ['x:y:z']` and a permission checker that returns false, `create()` succeeds and the stored value is the column default. This is today's `fieldSubmissionAllowed` behaviour: the field is dropped silently, with no error.
  - `nested_group_field_is_filterable`: `list.filter: 'like'` on a field inside `group` filters rows.
  - `belongs_to_list_join_uses_label`: a list row carries `__label_<field>`.
- [ ] **Step 2: Run, expect failure** (only `meta_has_no_fields_key` should fail; the others pin current behaviour and pass). `vendor/bin/phpunit --filter FormDataRuntimeFieldViewTest`.
- [ ] **Step 3: Implement**, one helper group at a time (filters → options → left tree → list actions → write rules → payload filter → joins/sort → sub-rows → access helpers), running the filter set from Step 5 after each group.
- [ ] **Step 4: Verify.** `grep -n "field_name\|control_props\|options_source\|fieldProjection\|FormField\b" app/admin/form/service/FormDataService.php app/admin/system/service/MemberFormDefinition.php` gives no output.
- [ ] **Step 5: Run, expect pass.** `vendor/bin/phpunit --filter 'FormDataRuntimeFieldView|Generated|ListAction|CrudFormSchemaExecution|BusinessGeneration|Member'`.
- [ ] **Step 6: Commit** (controller): `refactor(form-runtime): FormDataService reads FormFieldView; meta drops fields`.

### Task 12: Delete the projection, `fun_form_field` and the denormalised columns

**Files:**
- Modify: `admin/app/common/form/schema/FormSchemaCompiler.php` (delete `projection()`), `FormSchema.php` (delete the constructor arg and `fieldProjection()`)
- Modify: `admin/app/admin/form/repository/FormSchemaRepository.php` (`compilePayload()` returns `document, hash`; `persistCurrent()` also writes `name` from `title` and no `FormField`; delete `projectionRow()`)
- Modify: `admin/app/admin/form/service/FormBuilderRegistryService.php` (delete `compatiblePayload`)
- Modify: `admin/app/admin/form/model/Form.php` (delete `fields()` and the `list_config/form_config` casts)
- Delete: `admin/app/admin/form/model/FormField.php`, `admin/tests/FormFieldViewParityTest.php`
- Modify: `admin/app/admin/development/service/BusinessModuleService.php:127` (no `fields`), `BusinessDevelopmentService.php:530` (no `list_config/form_config` payload keys; the values still go into the document via `creationDocument`)
- Modify: `admin/database/core/migrations/001_baseline.sql` (drop the `fun_form_field` CREATE, drop `list_config`/`form_config`, set `schema_version` `DEFAULT '2'`)
- Modify: `admin/tests/Support/FormMetadataTables.php`, `admin/tests/BusinessDatabaseSyncTest.php:123-128`, `admin/tests/BusinessCreationIntegrationTest.php:52-57,100-101`, `admin/tests/BusinessGenerationArchiveTest.php:327-335`, `admin/tests/FormModelAttributeSaveTest.php:52-71`
- Test: `admin/tests/FormModelAttributeSaveTest.php` (the builder test becomes `testBuilderRegistrySyncsName`), `BusinessCreationIntegrationTest`, `MySqlCoreMigrationTest` (checksum, if it pins one)

**Interfaces:**
- Produces: `FormSchema(array $document, string $canonicalJson)`. The `fun_form_field` table, its model and the projection no longer exist.

- [ ] **Step 1: Write the failing tests.** `BusinessCreationIntegrationTest`: replace the `form_field` assertions at lines 100-101 with `assertSame('订单标题', $form->fresh()->schema_document['nodes'][0]['title'])` and `assertFalse(Db::schema()->hasTable('form_field'))`. `FormModelAttributeSaveTest::testBuilderRegistrySyncsName`: after `register()`, `name === '新名称'` and the attributes have no `list_config` key. Add `FormSchemaRepositoryTest::test_save_version_syncs_name_from_title` (business_api save with a new `title` updates `fun_form.name`).
- [ ] **Step 2: Run, expect failure.** `vendor/bin/phpunit --filter 'BusinessCreationIntegrationTest|FormModelAttributeSaveTest|FormSchemaRepositoryTest'`.
- [ ] **Step 3: Implement the deletions and the baseline edit.** Record the new `sha256sum database/core/migrations/001_baseline.sql` and put it, with the dev-DB SQL from spec §8, in the task report. Do not run the SQL.
- [ ] **Step 4: Verify.** `grep -rnE "fieldProjection|projectionRow|compatiblePayload|FormField\b|form_field|form_config|list_config" app database tests --include='*.php' --include='*.sql'` gives no hits outside `app/cms`, `plugins/cms` and `database/business/*.sql` history.
- [ ] **Step 5: Run, expect pass.** `vendor/bin/phpunit`, the full suite. Expected: 0 failures, 0 errors.
- [ ] **Step 6: Commit** (controller): `refactor(form): delete v1 field projection, fun_form_field and denormalised form columns`.

### Task 13: Regenerate the order module, final grep gate, full suites

**Files:**
- Regenerate: `admin/app/admin/business/order/**`, `web/src/admin/views/business/order/**`, `web/src/admin/api/business/order.ts`, new `admin/database/business/order_*` files (formal generation for module id 2, using the published schema; no schema change)
- Check: `web/src/admin/plugins/example/test/**` and (if kept) i18n-demo match the current template byte for byte, apart from the module-specific constants

**Interfaces:**
- Consumes: everything above.

- [ ] **Step 1: Regenerate the order module** through the business generation flow (preview → formal generation) against the dev DB. Then `php tests/business/OrderGeneratedTest.php`. Expected: `business order PHP contract: PASS`.
- [ ] **Step 2: Grep gate.** From the repo root: `grep -rnE "fieldProjection|compatiblePayload|projectionRow|FormFieldDef|field_name|control_props|options_source|validate_rules|link_rules|list_show|form_readonly|form_required|form_span|legacyField|projectFields|fieldToNode|layoutSchema|submissionFields|form_config|list_config|fun_form_field|'form_field'" admin/app admin/plugins admin/tests admin/database web/src web/tests`. Every hit must be in CMS code (`app/cms`, `plugins/cms`, `web/src/admin/plugins/cms`) or in `admin/database/business/*.sql` history. List any other hit in the report and fix it.
- [ ] **Step 3: Full suites.** `vendor/bin/phpunit` (0 failures/errors), `npx vitest run` (no failures beyond any pre-existing CMS failures you can show are unrelated), `npx vue-tsc --noEmit` (baseline only).
- [ ] **Step 4: Report.** Include the dev-DB SQL, the new baseline checksum, the regenerated file list, the new order `definitionHash`, and the grep output.
- [ ] **Step 5: Commit** (controller): `chore(business): regenerate order module without v1 field projection`.
