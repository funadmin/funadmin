# Remove web CMS submission frontend

The user requests deletion of frontend CMS code under web. Scope is CMS content submission UI and its route/API; retain PHP CMS themes, CMS admin, generic member authentication/profile, the host landing page and the independent QA functionality. Optional question about also deleting QA was unanswered; proceed with this narrower scope.

- [x] Add a failing route assertion that CMS submission frontend is absent while question and member routes remain.
- [x] Delete the shared CMS portal source by extracting its existing QA behavior into QuestionsPortal.vue. Move the retained shared/QA API into questions.ts without CMS content submit/model-field definitions. Remove CMS-specific tests and update retained QA/localization tests and member links.
- [x] Update docs, scan for stale imports/routes and run affected tests plus TypeScript checking. Work in current checkout and preserve unrelated changes; no build/deploy, backend mutation, commit or push.

Scoped baseline and file backup: /tmp/cms-public-removal-before. Baseline tests: router + community API/portal + localization; type-check baseline is recorded separately. PHP frontend runtime tests under web/tests/cms remain tests of the retained PHP themes and are not obsolete product code.

## Verification

- Baseline affected tests: 29 passed; TypeScript checking passed before edits.
- RED: new route-removal assertion failed against the old CMS entry.
- Final CMS + QA + public-route suites: 164 passed, no failures (`/tmp/cms-public-removal-final.json`).
- `npm run type-check -- --pretty false`: exit 0.
- `node scripts/check-i18n.mjs`: exit 0, 3432 keys per locale with matching placeholders/references.
- Original CommunityPortal.vue, community.ts and their two mixed test files are physically removed. Retained QA behavior lives in QuestionsPortal.vue/questions.ts and tests/qa, which is explicitly included by the existing test whitelist.
- No old portal/client imports or CMS public route remain in product source. The removed URL appears only in the negative route regression and removal documentation.
- CMS admin modules, PHP public themes, shared member views, host landing and QA remain. QA still needs the existing CMS site context/member identity/history endpoints; their proxy and backend implementation remain.
- Scoped diff reviewed against `/tmp/cms-public-removal-before`; unrelated working changes preserved. No backend, runtime publication, database, build or push action performed.
