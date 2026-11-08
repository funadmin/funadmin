# Document Reader Visual Redesign

**Spec:** docs/superpowers/specs/2026-11-08-document-reader-visual-design.md

- [x] Add browser interaction and asset/controller regressions, watch them fail.
- [x] Separate reader CSS/JS assets, implement brand header, home/reader layouts and genuine search/theme/copy interactions.
- [x] Run document and browser tests, synchronize installed plugin, reload and inspect desktop/mobile/light/dark/index/detail/search/empty views.

## Validation

User steering: simplify to the supplied official documentation reference; remove the previously proposed marketing hero and cards.

- PHP document and auth snapshot coverage: 63 tests, 273 assertions passed.
- JS interactions and document frontend regressions: 43 tests passed.
- Live HTML, stylesheet and script endpoints return 200; assets use a content-hashed URL.
- Browser inspection covers real empty home and rendered article at 1440px, 390px and dark theme. At 390px scroll width equals viewport width. Search opens the mobile directory and receives focus; copy uses the original code text.
- Local plugin upgraded to 1.2.0 and remains enabled. Source and installed public reader files match. No private document visibility was changed.
