# Apply Final Geometric F + un Logo

**Goal:** Apply the user-selected geometric F + geometric un icon throughout the site and delete superseded proposals.

**Spec:** `docs/superpowers/specs/2026-11-09-fun-logo-adoption.md`

- [x] Preserve the accepted 64×64 geometry in shared Vue, loader and both Blade documentation templates.
- [x] Regenerate SVG/PNG/ICO assets and final-only preview/archive; retain font license and source records.
- [x] Delete superseded proposal folders and duplicate extracted assets; keep `docs/design/logo/` as the sole design folder.
- [x] Verify current render, focused frontend/PHP tests, build and source/runtime asset consistency.

Use existing tests and asset checks for this visual change. Preserve unrelated workspace edits. No commit or push.

## Verification

Final-only preview inspected. Focused frontend tests: 6 passed across 2 files. PHP PublicReaderTest: 8 tests and 34 assertions passed. `npm --prefix web run build:webman`: exit 0, with existing vendor chunk-size warning. Verified four geometric paths in every icon variant, horizontal wordmark symbols, shared Vue component, loader and both Blade templates. Source/runtime templates and published brand/favicon assets match byte for byte. Transparent PNGs, seven ICO sizes, final-only design folder and ZIP integrity verified. Scoped whitespace check passed. No commit or push.

## Open F refinement

User requested removal of the F middle bar and moving geometric un into the opening. Removed that bar, inset un by 7 viewBox units relative to the F, and recentered the whole symbol by 3.5 units. Updated source paths and all application surfaces; regenerated every design/public PNG, favicon and archive. New edition: `fun-inset-1`. Preview inspected; 6 frontend tests and 8 PHP tests (34 assertions) passed again. Build exited 0 with the existing chunk-size warning. Source/runtime assets and final archive integrity verified again. No commit or push.

## Apply approved option A

- [x] Copy the exact four paths from the selected A SVG into source JSON, shared LogoMark, web loader, both Blade reader templates, and all public/design SVG variants; retain themes and existing component API.
- [x] Update edition to `fun-inset-a-1`, source records and final design description; regenerate PNG/ICO, accepted-only preview and asset archive.
- [x] Run focused frontend/PHP tests and `npm --prefix web run build:webman`; inspect final preview and verify source/runtime assets, selected geometry and archive contents.

Preserve unrelated concurrent edits. Do not commit or push.

Option A verification: selected preview paths match source JSON exactly; final-only render inspected. 6 frontend tests and 8 PHP tests (34 assertions) passed. Build exited 0 with existing chunk-size warning. Source/runtime assets, PNG/ICO and archive integrity verified. Scoped diff check passed. No commit or push.
