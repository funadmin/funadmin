# Adopt F + un Logo Implementation Plan

**Goal:** Apply the user-selected balanced F + un icon to all existing brand surfaces.

**Spec:** `docs/superpowers/specs/2026-11-09-fun-logo-adoption.md`

- [x] Read the accepted SVG and preserve the exact 64×64 geometry; replace shared Vue, static loader and both documentation templates.
- [x] Publish accepted assets under existing brand filenames; regenerate combined wordmarks, favicon formats and the asset archive; retain license and source records.
- [x] Inspect render, run existing login/reader tests and frontend build, verify runtime/public assets match sources; report any failures accurately.

This reversible visual change uses existing tests and asset verification, without new tests mirroring SVG implementation. Preserve all unrelated workspace edits; no commit or push.

## Verification

Accepted geometry verified in shared component, loader, both Blade reader templates and all icon variants. Existing frontend tests: 6 passed. PHP PublicReaderTest: 8 tests, 34 assertions passed. `npm --prefix web run build:webman`: exit 0, existing vendor chunk-size warning. Published brand directory, favicon formats and source copies match byte for byte. Document source/runtime templates match. Scoped diff whitespace check clean. Manrope license retained; trademark clearance remains incomplete. No commit or push.
