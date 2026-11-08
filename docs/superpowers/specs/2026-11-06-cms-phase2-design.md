# CMS phase two: template and site-building productivity

## Intent and scope

The user requested learning from PbootCMS and starting phase two after reviewing current CMS weaknesses. The current CMS already provides isolated sites/locales, versioned publishing, site roles, typed list configuration and a corporate theme. Choose a bounded first phase-two delivery focused on building websites efficiently. An optional priority question was offered; pending steering, this is the agent's recommended scope.

## Official research

Consulted PbootCMS official manuals: public formatting tags (docs/296.html), content listing/sorting/offset (docs/221.html), multi-condition filtering (docs/232.html), slides (docs/228.html), links (docs/229.html), forms (docs/231.html) and languages (docs/234.html). These show useful workflows: bounded list fetching, reusable formatting, grouped promotional data and complete template examples. Reimplement those capabilities using the current CMS protocol; do not copy its source/templates or introduce compatibility aliases.

## Approaches

1. Replace existing CMS with a PbootCMS-compatible parser: rejected because it changes an established plugin and template contract.
2. Extend the current CMS tags, queries, typed site configuration and default theme: selected as the agent's implementation choice. Existing templates keep working and no production migration is required.
3. Add forms/cache/upgrades together: defer to subsequent roadmap increments; this delivery must be coherent and verifiable.

## Deliverables

1. Template productivity: extend `cms:text` with default, strip_html, length and suffix; add `cms:date` with explicit format and invalid-date fallback; expose a reserved `loop` object (index, number, first, last, count and parent for nesting). Only allow registered formatters, never arbitrary functions or unescaped output. Existing variable syntax stays unchanged.
2. Content and config fetching: bounded offset and whitelisted content ordering by publish time/title/id with deterministic tie-breaks, preserve site/locale/published/model/category filters and correct totals/page bounds. Offset is nonnegative, max 10000; reject offset combined with pagination. Only one paginated content list may own a page's pagination; report duplicates instead of silently replacing state. Config offset applies after disabled-row filtering.
3. Ready-to-use website modules: quick creation of slides, partners, contacts and ads configuration models using the existing presets; default theme consumes enabled ordered rows from those exact keys. Slides replace the decorative hero only when configured, other modules render only when configured. No demo content or customer site settings are inserted. Layout remains the current corporate theme, with responsive cards and local slider controls that enhance native horizontal scrolling; no external dependencies or autoplay.

Default-theme projection is bounded to8 slides,12 partners,6 contacts and4 ads so a full page stays within the existing200-call budget; stored lists keep their100-row capacity. This implementation choice was made after reproducing a budget failure and is documented in the user guide.
4. Usage docs and examples, a PbootCMS gap matrix and phased roadmap. Forms, complex custom-field search, caching, backups, PbootCMS syntax compatibility and automatic deployment of changed DB records are not part of this delivery.

## Interfaces and constraints

- `TemplateFormatting` produces ordinary strings; TemplateEngine retains final HTML escaping and all output/call budgets. Plain text may consume TrustedHtml only by dropping its trust and escaping again. Dates accept timestamps or well-formed ISO/SQL dates, never relative language.
- `ContentService::listing` keeps its current signature/shape; new optional filter keys order/offset are validated. Published-title ordering uses the published revision.
- `BuiltinTags::content` defaults remain latest-first, offset=0 and limit=12. `config` scalar behavior remains compatible.
- `ThemePresentation` prepares bounded module arrays and booleans; `Renderer` retains site/locale isolation and supplies those lists to the current theme.
- Plugin source under admin/plugins/cms is authoritative; mirror only changed files into admin/app/cms, web/src/admin/plugins/cms and public assets. Preserve concurrent editor/permission/marketplace work.
- Verify using SQLite memory/temp files and actual frontend builds; never run production DB migrations or create demo site data.

## Acceptance

Old CMS tests stay green; new tests cover formatting/escaping, invalid parameters, published ordering, offsets, duplicate pagination, current site/locale, empty modules, enabled/disabled module rows, source/published parity, preset payloads and no-JS HTML structure. Reload Webman and verify public responses after publishing source/build artifacts.
