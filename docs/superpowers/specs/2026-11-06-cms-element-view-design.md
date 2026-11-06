# CMS Element components in view templates

The user confirmed that component structure belongs in `cms/view`; public assets hold common styling, library files, initialization and interactions. Continue without another approval round. Keep the existing four themes and browser-only local global scripts.

## Approach

Use explicit, fully closed `<el-*>` tags in PHP-rendered theme files. A generic browser adapter resolves those declared components, literal props and named slots without compiling templates or evaluating expressions. It never chooses page structure from CSS classes. Keep PHP text, semantic HTML and real anchors in the initial document. The alternative of compiling the entire page as a Vue template would execute untrusted CMS expressions; the previous CSS-driven construction hides markup in JavaScript.

Use `data-cms-model`, `data-cms-open`, `data-cms-close` for shared interaction state; slot wrappers use `data-cms-slot`. Native mobile/language/input/carousel fallbacks remain in the templates, marked for removal only during successful enhancement. Rich content has a static boundary where component tags and interaction markers are inert. All server text remains literal.

Navigation's optional template parameter renders each node through a theme partial with safely escaped variables and trusted rendered children; retain the existing native navigation mode. Do not change the template engine or its recursion limits. Layout, cards, images, grids, menus, drawer, language selector, carousel, breadcrumb, buttons, search and pagination must be visible in the theme HTML. Pagination uses Element previous/next with a native anchor slot, retaining encoded PHP links after mounting.

## Constraints

No bundler, dependency install, CDN, database edits, theme switching or plugin activation. Update source and installed copies narrowly. Work in the existing checkout because this continues the uncommitted CMS implementation; preserve unrelated changes. No commit or push requested.
