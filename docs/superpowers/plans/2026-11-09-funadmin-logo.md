# FunAdmin Logo Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for inline execution.

**Goal:** Apply the selected geometric F across FunAdmin and deliver reusable vector and raster assets.

**Architecture:** Keep the shared LogoMark API and change only its SVG paths. Use matching geometry for the static loader, favicon, document reader and downloadable assets.

**Tech Stack:** Vue SVG, static SVG, PHP templates, Pillow PNG/ICO export.

**Spec:** `docs/superpowers/specs/2026-11-09-funadmin-logo-design.md`

## Global Constraints

- Use the approved geometric F, with currentColor support and the existing size prop.
- Preserve unrelated Schema component changes.
- Keep document plugin source and installed template consistent.

## Review Focus

- Small favicon remains readable at 16px.
- White, blue and monochrome versions are visible on their intended backgrounds.
- First paint and mounted app use the same symbol.
- Runtime assets match source after building.

## Tasks

- [x] Replace `web/src/shared/components/LogoMark.vue`, loader in `web/index.html`, static favicons and documentation brand mark. Remove obsolete moon mask and branding comments.
- [x] Export SVG/PNG/ICO assets to `web/public/brand/`; keep a usage guide and rendered proof in `docs/design/logo/`.
- [x] Inspect a rendered proof of exact SVG paths at normal and small sizes; run existing login branding and document reader tests, PHP lint and `npm --prefix web run build:webman`; check source/runtime copies and diff whitespace.

This is a reversible visual asset change; existing tests, a build and visual inspection cover it without adding implementation-mirroring tests. No automatic commit or push.

## Verification

- Visual inspection: `docs/design/logo/preview.png`, generated from actual exported assets on light/dark backgrounds at 16–64px.
- Existing frontend branding/document UI tests: 6 passed. PHP PublicReaderTest: 6 tests, 29 assertions, passed.
- `npm --prefix web run build:webman`: exit 0; existing large vendor chunk warning.
- Verified identical geometry in the component, boot loader and reader; SVG parsing, transparent PNGs, ICO sizes and ZIP integrity; runtime brand assets match sources byte for byte.
- Reviewed only the branding changes; parallel plugin and Schema edits were preserved. No commit or push.

## Refinement verification

User requested refinement of the selected F. Reviewed `refined-preview.png` using actual exports, including 16–64px sizes. Updated geometry across all surfaces, outlined wordmark, PNG/ICO and archive. Frontend tests: 6 passed; PHP reader tests: 6 tests / 29 assertions passed; Webman build exit 0. Published brand assets and icon cache version verified. Scoped diff check clean; unrelated changes left intact.

Final asset location updated after the user selected geometric F + geometric un and requested deletion of earlier proposals. The current adoption spec and plan describe the accepted edition.
