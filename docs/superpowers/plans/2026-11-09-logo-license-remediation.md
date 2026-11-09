# Logo License Remediation Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans for inline execution.

**Goal:** Replace unverified typography and redraw the symbol with traceable source material, while reporting trademark clearance limits honestly.

**Architecture:** Pin and archive upstream Manrope with its OFL license, outline the licensed wordmark, construct a new F symbol from a documented grid, and synchronize all existing brand surfaces and exports.

**Tech Stack:** CoreText, SVG, PNG/ICO, Vue, PHP, existing build tools.

**Spec:** `docs/superpowers/specs/2026-11-09-logo-license-remediation-design.md`

## Global Constraints

- Preserve unrelated staged and unstaged workspace changes.
- Retain the full font license and unmodified font source with exact version and hash.
- Never claim zero infringement risk or completed trademark clearance without evidence.
- Do not rename the FunAdmin brand or file a trademark application.

## Review Focus

- Avenir-derived contours must not remain in the active delivery assets.
- Every public copy and ZIP must contain the current symbol and font license.
- Historical concepts must be clearly marked as non-current material.
- Blocked database searches remain unresolved in the review record.

## Tasks

- [x] Download and checksum pinned upstream font and OFL; document permitted logo artwork use with authoritative sources.
- [x] Redraw the icon on a documented grid and outline Manrope wordmark; replace application, loader and documentation brand assets.
- [x] Export transparent PNG, favicon and a review proof; package license, font provenance and graphic source record.
- [x] Run existing branding/reader tests, PHP lint, source-to-runtime checks and frontend build; record trademark search limitations and prepare professional-review materials.

This is a reversible visual/asset change; use existing tests and rendered inspection rather than adding implementation-mirroring tests. No commit or push.

## Verification and remaining limitation

Pinned upstream TTF and OFL SHA-256 verified. Current logo glyph paths match the Manrope source JSON; symbol paths match the recorded grid and active surfaces. PNG transparency, ICO output, archive integrity, required license attachments, unchanged extracted copies and published/source asset equality verified. Existing frontend branding/document tests: 6 passed; PHP PublicReaderTest: 6 tests / 29 assertions passed. Webman build exit 0, with existing vendor chunk warning. Reader template changed to Blade in a parallel session, so logo synchronization follows `reader.blade.php` without reverting that migration. Scoped whitespace check clean. No commit, push, trademark filing or paid service.

Trademark clearance is NOT COMPLETED. WIPO verification and automated-query restrictions prevented a usable registry result. Source traceability and font license verification do not establish exclusive rights or guarantee non-infringement.
