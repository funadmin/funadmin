# CMS editor completion

User approved implementing the five improvements in the preceding code review. Work in the current dirty workspace; preserve unrelated changes, no commit/push, no production migrations or role changes.

## Approach
Extend existing Vue/Element Plus content editor and versioned model JSON schema. This preserves schema versions and attachment controls; a separate form builder would duplicate existing infrastructure. Keep eight existing types; dates/multiselect/new rich text model types remain future work.

## Behavior
- Initialize new/missing fields from declared defaults, else boolean=false/gallery=[]/number=null/text-select=empty string/attachment=null. Preserve existing values, including false,0 and explicit null. Switching new-content models resets data and selects compatible enabled categories.
- Inline field validation for title/path, required, finite numbers, declared min/max, text max_length and valid select values. Required boolean false and number0 are valid. Backend enforces identical model constraints and rejects invalid schema definitions/defaults. Fixed existing type limits remain.
- Model UI exposes default, description, placeholder, number min/max and text max_length. Constraints are versioned JSON; no SQL changes. Server validates metadata and typed defaults; the editor exposes defaults for scalar fields, with existing attachment validation retained for any API-supplied media default.
- Three editor tabs: basic, model fields, SEO/display. First error selects tab and focuses/scrolls the field.
- Separate editor category list from table context; fetch by target edit locale, filter enabled categories by model. Existing missing/unavailable category is visible as disabled fallback and must be corrected before save. Failed language/category loads retain old editor; generations suppress late responses.
- Dirty tracking uses editable content plus active loaded locale. Before close, language switch or navigating away, ask whether to discard unsaved changes; cancel retains draft. Before unload warns. Previews/publication explicitly warn and require saving current changes first; no silent autosave/publish. Save snapshot marks only the submitted form as clean, preserving changes made while saving. Upload/language busy guards remain.
- Preserve exact string IDs, revision/CAS and historical schema behavior; edits use saved field schema, updates require explicit adoption.

## Verification
Pure helper regression tests for defaults/rules; rendered component tests for field controls/tabs, category+language state, defaults and save payload, unsaved guard/preview/publish; isolated SQLite backend tests for schema metadata and enforced constraints. Full CMS/frontend and PHP suites, source/mirror parity, typecheck/build.
