# Form v1 field projection removal (FORM-6 + FORM-8): design

Date: 2026-11-09. Status: proposed. Audit: `.superpowers/compat-audit-2026-11-09.md` (FORM-6, FORM-8, FORM-9). Prior work: `.superpowers/compat-phase2-report.md` (FORM-1/2/5/7 are done; the designer saves Form Schema v2 only; stored documents no longer contain `extensions.legacy`).

## 1. Goal

FormSchema v2 nodes (`schema_document.nodes`) become the only field model in FunAdmin, both for editing and for reading. The v1 field *shape* (`field_name`, `label`, `column_type`, `control_props`, `options_source`, `validate_rules`, `link_rules`, `relation_*`, `list_*`, `form_*`, `sort_order`) stops existing:

- in the designer's live model and its undo history;
- as a server-side round-trip format (compile → flat rows → DB → read back → DDL);
- in any API response, generated file or DB table.

Wherever a consumer needs "the list of data fields", it gets a **read-only view derived from v2 nodes, with v2 names**. The only producer is `FormSchema::fields()` on the backend and `fieldNodes()` on the frontend.

## 2. Non-goals

- No change to the FormSchema v2 document format. Canonical documents and `schema_hash` values stay byte-identical, so `fun_form_schema_version` is not rewritten.
- No change to `CrudDefinition` field rows (`name`, `dbType`, `component`, …). That is the generator's own model, not v1 form compat. Only the `layoutSchema` key goes (it carries v1 rows).
- No change to runtime HTTP behaviour of dynamic forms: validation rules, filters, sorting, joins, sub-rows, option sources and access checks must give the same results.
- No UI redesign of PropsPanel or the runtime pages. Same controls, same `data-prop`/`data-toggle` hooks.
- Out of scope, flagged: `FormDataService::meta()` already returns the published `schema` unfiltered, so the `defaultValue` of sensitive nodes is visible there today. Dropping `fields` does not make this worse. Track it separately.

## 3. Inventory: producers and consumers of the projection

"Compat" = v1 shape used as live model / round-trip / persisted / API. "Legit" = derived read-only view with v2 naming and a single source path (keep or use as the pattern).

### 3.1 Backend producers

| Where | What | Verdict |
|---|---|---|
| `admin/app/common/form/schema/FormSchemaCompiler.php:30,84-140` `projection()` | flattens nodes (incl. layout nodes, not into repeatable/subform) to v1 rows, adds `control_props.schemaAccess`, `link_rules.rules`, `sort_order` | compat; delete |
| `admin/app/common/form/schema/FormSchema.php:12,41-44` `fieldProjection()` | exposes the rows | compat; replace with `fields()` |
| `admin/app/admin/form/repository/FormSchemaRepository.php:301-334` `persistCurrent()/projectionRow()` | persists rows into `fun_form_field` on every version save | compat (denormalised copy); delete |
| `admin/app/admin/form/service/FormDesignerService.php:206,247-250,434-473` `save()/fieldRow()` | writes the same rows a second time, filling `column_type` defaults | compat; delete |
| `admin/app/admin/form/service/FormDesignerService.php:288-318` `inferFields()` | builds v1 rows from a table; **no callers** | dead; delete |
| `admin/app/admin/form/service/FormPublishService.php:68,91,386-403` `compatiblePayload()` | payload + `fields => fieldProjection()` for validation and DDL preview | compat; delete |
| `admin/app/admin/form/service/FormPublishService.php:405-419` `definitionPayload()` | rebuilds `fields` from `fun_form_field` rows read back after save, feeding DDL apply and structure check | compat (DB round-trip); delete |
| `admin/app/admin/form/service/FormBuilderRegistryService.php:34,38-56` `compatiblePayload()` | copies `title`, `form`, `list` into `fun_form.name/form_config/list_config` (`form_key/table_name/connection/source_type` can never change here: `assertIdentity` runs first) | compat; delete, move `name` sync into `persistCurrent` |
| `admin/app/admin/form/repository/FormSchemaRepository.php:68-76` `compilePayload()['projection']` | API field; FE type `FormSchemaCompileResult.projection` has **no reader** | compat; delete |
| `admin/app/admin/system/service/MemberFormDefinition.php:39` | returns `fields` next to `schema`; FE reads only option lists from it | compat; delete |
| `admin/app/admin/development/service/BusinessModuleService.php:127` `detail()['fields']` | `fun_form_field` rows to the designer | compat; delete |
| `admin/app/admin/form/service/FormDataService.php:57-80` `meta()['fields']` | projection rows to runtime pages | compat; delete |
| `admin/app/admin/form/service/FormDataService.php:84-102` `sourceMeta()` | DTO `{field_name, label}` | v1 naming; rename to `{field, title}` |
| `admin/app/admin/development/service/FormCrudDefinitionFactory.php:32,53-70,131,397-409` | `$form['fields'] = fieldProjection()`, then `field()/options()/relation()` read v1 keys; `layoutSchema` = v1 rows padded by `formSchemaNode()` | compat; read views, drop `layoutSchema` |
| `admin/app/common/crud/ProductionTemplateContext.php:1341-1356,1374-1376,1383` | emits `submissionFields` (`field_name`, `form_readonly`, `control_props.schemaAccess`, `options_source`) and `import type { FormFieldDef }` into every generated `*Form.vue` | compat in generated code; delete |
| `admin/app/admin/form/model/FormField.php`, `Form.php:23,30` (`fields()` relation, `list_config/form_config` casts) | model of the v1 table | compat; delete |

### 3.2 Backend consumers (all read v1 keys today)

| Where | Keys used |
|---|---|
| `FormDesignerService.php:89-171` `validateDefinition()` | `field_name` regex/dupes, `label`, `type`, `index_type`, `relation_type/table/label_field/value_field/on_delete`, `options_source`, `form_span`, `column_type`, `default_value` |
| `FormDesignerService.php:320-338,482-570` `previewMigration()/createTableSql()/additiveSql()/columnDdl()/isVirtualField()` | `field_name`, `type`, `column_type`, `unsigned`, `nullable`, `default_value`, `comment`, `index_type`, `relation_type/table/value_field/on_delete` |
| `FormPublishService.php:332-345` `assertStructure()` | `type`, `relation_type`, `field_name` |
| `FormDataService.php` ~100 sites: `fields()` 1493-1508, `publishedRuntime()` 1512-1546 (wraps rows in the `FormField` model), `meta` 57-80, `sourceMeta` 84-102, list filters 156-200/1060-1115, left tree 270-290/865-890, `assertLeftTreeUnreferenced` 440-470, detail/children 519-620, options 768-830, list actions 993-1050, write rules 1195-1290, payload filtering 1398-1470, joins/sort 1560-1600/1672, sub-rows 1679-1830, access/sensitive/derived helpers 1815-1860 | every v1 key, plus `FormField` typed parameters (`selfTreeOptions`, `subRows`, `childContext`, `relationReadable`) |

### 3.3 Frontend

| Where | What | Verdict |
|---|---|---|
| `web/src/admin/api/form.ts:63-97` `FormFieldDef`; `:165-172` `FormDefinition.fields/fields_count/list_config/form_config`, `FormSchemaCompileResult.projection`; `:23-48` a second `FormSchemaNode` type | v1 type, duplicate node type | compat; delete, keep one node type in `views/form/schema/types.ts` |
| `api/formData.ts:14,23-26`, `api/development/business.ts:166`, `api/system/member.ts:18` | `fields: FormFieldDef[]`, `FormSourceField.field_name` | compat; delete / rename |
| `views/form/composables/useDesigner.ts:55-149` `validationFromField/fieldToNode/nodeToField/projectFields`; `:158-203` `fields` ref and synthetic `selected` for layout nodes; `:283-450` field-keyed mutations; `:489-493` v1 load branch (FORM-8); `:531-535` re-projection in `markSaved` | v1 live model that round-trips node → row → node on every edit. Side effect today: `updateField` runs `Object.assign(node, fieldToNode(...))`, which **resets `events` to `{}`** and rewrites `valueType`; layout span/group edits are written to `props.form_span/form_group`, which `sanitizeComponentBindings` then drops | compat; rewrite on nodes |
| `views/form/registry.ts:107-145` `createField()` | builds a v1 row for new controls | compat; replace with `createNode()` |
| `designer/components/PropsPanel.vue` (34 refs), `PropsFieldFolds.vue:72-126`, `propsSummary.ts:55-60` | edit `FormFieldDef`, emit `Partial<FormFieldDef>` | compat; edit node, emit node patches |
| `designer/components/ListConfigurationPanel.vue:21,38-39,75,106-107,119-120,150-156`, `CategoryWorkspace.vue:23,55,66,137,153` | read `field_name/column_type/relation_type/control_props/list_*/options_source`; mix designer rows with `sourceMeta` rows | compat; read field nodes and `{field,title}` |
| `designer/components/DesignerCanvasNode.vue:98-103` | looks up the store row for preview options and passes it to `FormControlRenderer` | compat |
| `designer/index.vue:126,136,142,260-267,329,405,429,526,628` | `store.fields`, `store.selected`, `definition().fields`, `load({...form, fields})` | compat |
| `components/SchemaNodeRenderer.vue:34,53,107-117` `legacyField` → `RegisteredControlRenderer.vue:12,31,40` → `CoreControlAdapter.vue:3,25` → `FormControlRenderer.vue:20,186,220,244-275` → `RepeatableField.vue:17,43,50-57` | renderer chain takes a fake v1 row as `field`; plugin renderers also receive `field` | compat; pass `node` only |
| `components/SchemaForm.vue` + `linkRules.ts` | v1 rows → nodes adapter; **only used by `SchemaForm.spec.ts`** | dead; delete |
| `runtime/submissionPolicy.ts:1-79` | `buildSubmissionPayload/sanitizeRuntimeRecord/emptyRuntimeValues/isSensitiveField` on `FormFieldDef`; `resolveSubmissionInclude` falls back to `form_config.submitInclude` | compat; take nodes, drop fallback |
| `runtime/fieldPresentation.ts:80-86` `presentationNodes(fields, nodes)` | merges rows with nodes | compat; delete, use nodes |
| `views/form/data.vue` (37 refs), `published.vue` (23), `components/FormDataImportDialog.vue:32-53`, `components/ListSourceTree.vue:121,135` | runtime pages read `meta.fields` | compat; read `fieldNodes(meta.schema.nodes)` |
| `views/system/member/components/MemberFormDialog.vue:37-73` | option lists from `fields[].options_source` | compat; read schema nodes |
| `mock/modules/developmentBusiness.ts:20` | inspect mock returns `field_name` rows (backend returns `name/dbType`) | stale mock; fix |
| `development/business/codegen/fieldTable.ts` | `FieldRow` derived from nodes with v2 naming, writes patches back to nodes by path | **legit**, already the target pattern; its `controlChange/withRequired` move to a shared module |
| Generated: `views/business/order/components/OrderForm.vue:14,22`, `views/business/i18n-demo/components/I18nDemoForm.vue:14,22`, `plugins/example/test/components/TestForm.vue:14,22` | `submissionFields` v1 rows + `FormFieldDef` import (the last two also still embed `extensions.legacy` and `mode`) | regenerate / patch |

### 3.4 Legitimate derived views (kept)

- `CrudDefinition.fields` (generator rows, own naming, produced by `FormCrudDefinitionFactory` from views).
- `codegen/fieldTable.ts` `FieldRow`.
- `BusinessDevelopmentService::adoptedFieldNodes()` (column → v2 node; already v2).
- The new `FormFieldView` (backend) and `fieldNodes()` (frontend) below.

## 4. Approaches

**A. Raw nodes everywhere.** Delete the projection; every consumer reads `$node['database']['relation']['type'] ?? 'none'` (PHP) or `node.database?.relation?.type ?? 'none'` (TS).
Cost: largest diff in `FormDataService` and DDL code; the defaults (`nullable` true, `index` none, `onDelete` restrict, `span` 24, …) are re-typed at ~120 sites. Risk: default drift between sites, silently different runtime behaviour. Frontend cost is fine (TS types the node).

**B. One derived read-only field view, v2 naming (recommended).**
Backend: `FormSchema::fields(): list<FormFieldView>`, a small typed wrapper over one canonical field node, the only place where defaults live. Every backend consumer (validation, DDL, publish, CRUD factory, runtime data service) reads views. Frontend: no wrapper, because TypeScript already types `FormSchemaNode`. Add `fieldNodes(nodes)` plus a few predicates, and make the designer edit nodes directly through shared node-edit helpers. All v1-shaped types, tables, API keys and generated constants go.
Cost: medium-large (13 tasks). Risk: concentrated in `FormDataService` and the designer store; both have good test coverage.

**C. Rename the projection.** Keep a flat DTO but camelCase it (`fieldName`, `listShow`, …), keep `fun_form_field` renamed.
Cost: lowest, mostly mechanical. But it keeps a second persisted model and a node → row → node round-trip in the designer, which is exactly what FORM-6 is about. Rejected.

**Recommendation: B.** It removes every v1 shape and round-trip, keeps defaults in one PHP class, and copies the pattern `fieldTable.ts` already uses.

## 5. Target data flow

```
designer (nodes[] + form meta) ──saveSchema(schema)──▶ FormSchemaRepository::compile ─▶ FormSchema
                                                                     │                    ├─ document()/hash()       (persisted: fun_form.schema_document, fun_form_schema_version)
                                                                     │                    └─ fields(): FormFieldView[] (memory only)
     ┌───────────────────────────────────────────────────────────────┼────────────────────────────┐
     ▼                                         ▼                     ▼                            ▼
FormFieldValidator / DDL preview+apply   FormPublishService     FormCrudDefinitionFactory    FormDataService (published snapshot)
(FormDesignerService)                    (assertStructure)      → CrudDefinition.fields       meta = {form, schema, …}  (no `fields`)
                                                                → generated code (no v1 rows)
runtime pages / member dialog / list config: fieldNodes(schema.nodes)  (frontend)
```

- `fun_form` keeps identity and index columns only; `persistCurrent()` is the only writer of the derived ones (`name`, `schema_*`, `publish_status`).
- The publish pipeline passes the compiled `FormSchema` to validation, DDL preview, DDL apply and structure check. Nothing is read back from the DB to rebuild fields.

## 6. What gets deleted

Backend: `FormSchemaCompiler::projection()`, `FormSchema::fieldProjection()` + constructor arg, `FormSchemaRepository::projectionRow()` and the `fun_form_field` writes, `compilePayload()['projection']`, `FormDesignerService::fieldRow()/inferFields()/controlFromDbType()/indexFromSchema()/listing()/remove()/setStatus()` (no callers once `inferFields` goes; verify with grep), the `FormField` writes in `save()`, `FormPublishService::compatiblePayload()/definitionPayload()`, `FormBuilderRegistryService::compatiblePayload()`, `FormCrudDefinitionFactory::formSchemaNode()` and `layoutSchema`, `CrudDefinition` `layoutSchema` normalisation, `DefinitionValidator` allowed key `layoutSchema`, `crud-definition-v1.schema.json` `layoutSchema`, `ProductionTemplateContext` `submissionFields` + `FormFieldDef` import + `layoutSchema` fallback source, `app/admin/form/model/FormField.php`, `Form::fields()`, `Form` casts for `list_config/form_config`, `MemberFormDefinition` `fields`, `BusinessModuleService::detail()['fields']`, `FormDataService::meta()['fields']`, unused `app/common/form/schema/FormNode.php` (no references).

Frontend: `FormFieldDef`, the duplicate `FormSchemaNode` in `api/form.ts`, `FormDefinition.fields/fields_count/list_config/form_config`, `FormSchemaCompileResult.projection`, `FormDataMeta.fields`, `BusinessModuleDetail.fields`, member `fields`, `useDesigner` `fields/selectedKey/selected/NODE_FIELD_PREFIX/fieldToNode/nodeToField/projectFields/validationFromField/addField/removeField/duplicateField/moveField/updateField/replaceFields`, the FORM-8 load branch, `registry.createField`, `SchemaNodeRenderer.legacyField`, the `field` prop on `RegisteredControlRenderer/CoreControlAdapter/FormControlRenderer/RepeatableField`, `SchemaForm.vue` + `SchemaForm.spec.ts` + `linkRules.ts`, `presentationNodes()`, `resolveSubmissionInclude`'s `form_config` fallback.

## 7. What gets renamed or retyped

Backend (new):

```php
namespace app\common\form\schema;

/** Read-only view of one data field node (kind !== 'layout'). Single home of field defaults. */
final class FormFieldView
{
    public static function fromNode(array $node): self;
    public function node(): array;              // canonical node as compiled
    public function nodeId(): string;
    public function name(): string;             // node.field
    public function title(): string;
    public function type(): string;
    public function isRelationContainer(): bool; // repeatable | subform
    public function isVirtual(): bool;          // isRelationContainer() || relationType() === 'has_many'
    public function columnType(): string;       // database.columnType ?? ''
    public function nullable(): bool;           // ?? true
    public function unsigned(): bool;           // ?? false
    public function index(): string;            // ?? 'none'
    public function comment(): string;          // ?? ''
    public function defaultValue(): mixed;      // ?? null
    public function props(): array;             // ?? []
    public function access(): array;           // ?? []
    public function validation(): array;        // list of rules, ?? []
    public function required(): bool;           // any rule type 'required'
    public function rules(): array;             // type => value (value ?? true), required excluded
    public function conditions(): array;
    public function dataSource(): ?array;
    public function dataSourceKind(): string;   // dataSource.kind ?? ''
    public function relationType(): string;     // database.relation.type ?? 'none'
    public function relationTable(): string;
    public function relationLabelField(): string;
    public function relationValueField(): string; // raw; '' when absent (callers that defaulted to 'id' keep `?: 'id'`)
    public function relationMultiple(): bool;
    public function relationOnDelete(): string; // ?? 'restrict'
    public function listShow(): bool; public function listSort(): bool;
    public function listFilter(): string; public function listFormatter(): string; public function listWidth(): int;
    public function hidden(): bool; public function disabled(): bool;
    public function span(): int;                // integer layout.span, otherwise 24
    public function group(): string;
}
// FormSchema
public function fields(): array;               // list<FormFieldView>, memoised; depth-first; descends layout nodes only
public function field(string $name): ?FormFieldView;
```

Traversal rule (same as the old projection, minus layout rows): visit nodes in document order. A `kind: 'layout'` node is skipped and its children are visited. Any other node becomes a view; its children are visited unless its type is `repeatable`/`subform`.

Other backend renames:
- `FormDesignerService::validateDefinition(array $payload, bool)` → `validateFields(FormSchema $schema, string $sourceType): void`. Identity checks (`form_key`, `name`, `table_name`, `source_type`) move into `save()`, which reads them from the compiled document.
- `previewMigration(array $payload)` → `previewMigration(FormSchema $schema): array`; `applyDynamicDdl(array $payload)` → `applyDynamicDdl(FormSchema $schema, string $expectedDdlHash): array`. `createTableSql/additiveSql/columnDdl/isVirtualField` take `FormFieldView`.
- `FormPublishService` closures: `saveForm(array $payload): array` (request payload incl. `schema_document`, no `fields`), `previewDdl(FormSchema): array`, `applyDdl(FormSchema, string): array`, `verifyStructure(FormSchema): void`.
- `FormDataService` private helpers take `FormFieldView` (or `list<FormFieldView>`) instead of `array`/`FormField`; `publishedRuntime()['fields']` is `list<FormFieldView>`.
- `sourceMeta()['fields']` items: `{field, title}`.

Frontend:
- `views/form/schema/types.ts`: the one `FormSchemaNode`, now with `access?`, `database?: FormSchemaDatabase` (`columnType`, `nullable`, `comment`, `unsigned`, `index`, `relation?: FormSchemaRelationConfig` = `{type, table, labelField, valueField, multiple, onDelete}`), `list?: FormSchemaListColumn` (`show`, `sort`, `filter`, `formatter`, `width`). `api/form.ts` re-exports it.
- New `views/form/schema/fieldNodes.ts`: `fieldNodes(nodes): FormSchemaNode[]` (same traversal rule as the backend), `isSensitiveNode`, `isRequiredNode`, `relationTypeOf`, `columnTypeOf`.
- New `views/form/schema/nodeEdits.ts`: `withRequired`, `patchList`, `patchDatabase`, `patchRelation`, `patchLayout`, `changeControl(node, type, { resetColumnType })` (moved out of `codegen/fieldTable.ts`).
- `registry.createField(type, index): FormFieldDef` → `createNode(type, index, id): FormSchemaNode`.
- `submissionPolicy`: `buildSubmissionPayload(nodes: FormSchemaNode[], values, include, can)`, `sanitizeRuntimeRecord(nodes, record)`, `emptyRuntimeValues(nodes)`, `isSensitiveField` → `isSensitiveNode` (from `fieldNodes.ts`). Each function flattens with `fieldNodes()` itself, so callers pass `schema.nodes`.
- `PropsPanel`/`PropsFieldFolds`: prop `node: FormSchemaNode`, event `update: [patch: Partial<FormSchemaNode>]`. Same `data-prop` / `data-toggle` attribute values, so the visible hooks stay; only the bound data changes.
- Renderer chain: `node: FormSchemaNode` replaces `field`; `FormControlRenderer` drops `schemaNode` (it *is* `node`). Plugin renderers receive `schemaNode` as today and no longer receive `field`. No plugin in the repo reads `field`.
- `FormSourceField` → `{ field: string; title: string }`.

## 8. Database (`fun_form`, `fun_form_field`)

Dev DB facts (SELECT only): 2 forms, both `schema_version = 2`; `form_config`/`list_config` are NULL or `[]`; `fun_form_field` has 17 rows (6 + 11), all derived; no stored document contains `form_span`, `form_group` or `legacy`.

| Column / table | Decision | Reason |
|---|---|---|
| `fun_form_field` (whole table) | **drop** | pure denormalised copy of the projection; no reader after this work |
| `fun_form.form_config`, `fun_form.list_config` | **drop** | duplicates of `schema_document.form/list`; only reader was the `form_config.submitInclude` fallback |
| `fun_form.schema_version` | keep, `DEFAULT 2` (FORM-9) | all rows are 2; the compiler rejects anything else |
| `form_key`, `table_name`, `connection`, `source_type` | keep | identity (`assertIdentity`), unique key, runtime lookups |
| `name` | keep; written only by `persistCurrent()` from `title` | listing/search, default menu name |
| `schema_document`, `schema_hash`, `schema_origin`, `publish_*`, `published_*`, `status`, `remark`, `sort_order`, `business_module_id`, `crud_generation_id` | keep | not projection-derived |

Edits: `admin/database/core/migrations/001_baseline.sql` (drop the `fun_form_field` CREATE, the two columns, `DEFAULT '2'`; record the new sha256). The PHP baseline does not create these tables, so it needs no edit. Test support tables (`tests/Support/FormMetadataTables.php`, `BusinessDatabaseSyncTest`, `BusinessCreationIntegrationTest`, `BusinessGenerationArchiveTest`) drop `form_field` and the two columns. Dev-DB SQL goes into the task report and is not run (same practice as phase 2):

```sql
DROP TABLE IF EXISTS `fun_form_field`;
ALTER TABLE `fun_form` DROP COLUMN `form_config`, DROP COLUMN `list_config`, MODIFY `schema_version` int NOT NULL DEFAULT 2;
UPDATE `fun_system_migration` SET `checksum` = '<new sha256>' WHERE `scope` = 'core' AND `version` = '001_baseline';
```

## 9. Generated code and stored hashes

- **`schema_hash`**: unchanged. The projection was never part of the canonical document. No `fun_form_schema_version` rewrite.
- **`definitionHash`**: changes for every module, because `layoutSchema` leaves `CrudDefinition`. Generated files do not embed it. `tests/Console/CodeGeneratorRelationsTest::PLAIN_BASELINE` gets new `definitionHash` values. In that task every file hash must stay identical; that is the proof that `layoutSchema` was dead for output.
- **Generated `*Form.vue`**: `submissionFields` and the `FormFieldDef` import go. The submit call becomes `buildSubmissionPayload(sourceSchema.nodes, form, resolveSubmissionInclude({schema_document:sourceSchema}), hasPermission)`. `sourceSchema` is already the pruned document with `disabled` set on non-writable fields, so the submitted keys are the same. `PLAIN_BASELINE` gets new `*Form.vue` hashes (core and plugin).
- **Order module** (`fun_business_module` 2, published v9): regenerate with formal generation after the generator tasks. That refreshes the generated-file baselines and `published_definition_hash`, and adds the usual `database/business/order_*` files. Commit `1f499354` is the precedent.
- **`I18nDemoForm.vue`** (frontend-only orphan: no backend, no form row) and **`plugins/example/test/components/TestForm.vue`** (no form row either) cannot be regenerated through the managed flow. Patch them by hand to the new template output (drop `submissionFields`, the `FormFieldDef` import, `extensions.legacy` and `mode`). Owner decision Q2 offers deleting i18n-demo instead.

## 10. Test strategy

- **Backend unit:** new `tests/FormFieldViewTest.php` covers the defaults table, traversal (layout skipped, repeatable children hidden, order), `required()/rules()`, `span()` with responsive span, and `field()` lookup.
- **Behaviour parity:** before deleting the projection, a characterisation test (`tests/FormFieldViewParityTest.php`) asserts that every view getter equals the matching projection key for a fixture document that uses every node feature, so the per-site rewrites can be checked against one table. The test is deleted together with the projection.
- **Existing suites as guards:** `FormDynamicDdlTest` (DDL text identical), `CrudFormSchemaExecutionTest`, `GeneratedLeftTreePermissionTest`, `GeneratedListActionPermissionTest`, `ListActionCatalogPendingKeysTest`, `BusinessCreationIntegrationTest`, `BusinessDatabaseSyncTest`, `BusinessGenerationArchiveTest`, `FormModelAttributeSaveTest`, `CodeGeneratorRelationsTest`, `CodeGeneratorFormSizeTest`, `MemberHttpTest`, `MemberIntegrationTest`.
- **Frontend:** new `schema/fieldNodes.spec.ts` and `schema/nodeEdits.spec.ts`; rewritten `submissionPolicy.spec.ts`, `PropsPanel.spec.ts`, `ListConfigurationPanel.spec.ts`, `CategoryWorkspace.spec.ts`, `FormControlRenderer.spec.ts`, `designerShell.spec.ts`, `saveConflict.spec.ts`, `DesignerCanvas.spec.ts`, `ListSourceTree.spec.ts`, `MemberFormDialog.spec.ts`, `tests/views/form-page-host-lifecycle.spec.ts`, `tests/contracts/form-publish.spec.ts`. New designer regression: editing a property keeps `events`, `access`, `attrs`, `info` and `valueType`; layout span edits land in `layout.span`.
- **Grep gate (final task):** 0 hits in `admin/app admin/plugins admin/tests admin/database web/src web/tests` for `fieldProjection|compatiblePayload|projectionRow|FormFieldDef|field_name|control_props|options_source|validate_rules|link_rules|list_show|form_readonly|form_required|form_span|legacyField|projectFields|fieldToNode|layoutSchema|submissionFields|form_config|list_config|fun_form_field|'form_field'`. Exclusions, reviewed one by one: CMS code (`app/cms`, `plugins/cms`, which have their own `fields`), `tests/fixtures` SQL dumps if any, and the business migration `.sql` history.
- **Every task:** the focused suites it names, then `vendor/bin/phpunit` and `npx vitest run` in full before the final task, and `npx vue-tsc --noEmit` with no errors beyond the known MenuItemsEditor / ProviderSettingsDrawer baseline.

## 11. Risks

1. `FormDataService` rewrite (~100 sites) can change runtime semantics: filters, joins, write rules, sensitive/access handling. Mitigation: the parity test plus the generated/runtime permission suites; one helper at a time.
2. DDL text drift. `fieldRow()` used to fill an empty `column_type` with the capability default and `relation_value_field` with `'id'`; the DDL must keep both fallbacks. `FormDynamicDdlTest` pins the SQL.
3. Designer store rewrite: undo/redo, duplicate (unique `field` names), move, rename, save reconciliation. Covered by the designer specs plus the new regression.
4. Generated-code churn: order module regeneration, hand-patched example/i18n files, `PLAIN_BASELINE` updates. Do these in one task each so the reviewer can diff.
5. Concurrent sessions: the audit warned about `SchemaNodeRenderer.vue`/`SchemaRenderer.vue` edits; check `git status` before the renderer task.
