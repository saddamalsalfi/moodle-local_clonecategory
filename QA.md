# Quality evidence — 1.3.1

Prepared on 7 October 2026. Stable package version: `2026100705`. Base source: `v1.1.2`, commit `810ed1b9cd26a4bb83ab9afc2c1e624c83546089`. The results below distinguish executed local checks from the GitHub CI matrix. GitHub and Marketplace publication are separate operations.

## Stable release verification

Build `2026100705` declares release `1.3.1` and `MATURITY_STABLE`. The stable package was re-tested on Moodle 5.2.2 (Build: 20260810), PHP 8.3.6 and MariaDB 10.11.14: **27 tests, 90 assertions, passed**. A native upgrade from the preview preserved category/course fingerprints and job/item audit records. The stable upgrade adds no fields or resource mutations. PHP syntax, Moodle coding standards, the selector test, workflow YAML parsing and the diff whitespace check passed.

The release workflow runs the reusable CI matrix before publishing the GitHub Release. No external CI success is claimed merely because the workflow is configured.

## Rollback regression correction

Reported environment: Moodle **5.2.2 (Build: 20260810)** and plugin 1.3.0-beta. The old ZIP reproduced the failure in a real Chromium browser after viewing copies in topics, weeks and social formats. Comparing complete fingerprint input showed no resource changes: only buffered `course_section_created` and `enrol_instance_created` entries appeared after the worker process ended. The correction flushes the standard log store before snapshotting.

The patched code successfully rolled back the **same old job**, preserving its original fingerprint, and a newly cloned job after viewing all three courses. Legacy verification removes only eligible initial creation-log suffixes when the resulting snapshot exactly matches the original SHA-256; it leaves other mutation logs and all resource records intact.

## Executed integration runs for the release implementation (1.3.1-beta)

| Moodle | PHP | Database | PHPUnit | Result |
| --- | --- | --- | --- | --- |
| 5.2.2, build 20260810 | 8.3.6 | MariaDB 10.11.14 | 11.5.55 | 27 tests, 90 assertions, passed |
| 5.2.4, build 20261005 | 8.3.6 | MariaDB 10.11.14 | 11.5.55 | 27 tests, 90 assertions, passed |
| 4.5.15, build 20261005 | 8.3.6 | MariaDB 10.11.14 | 9.6.34 | 27 tests, 90 assertions, passed |
| 5.3, build 20261005 | 8.3.6 | MariaDB 11.8.6 | 11.5.56 | 27 tests, 90 assertions, passed |

New regression tests exercise buffered-log shutdown, existing baselines with all or partial creation logs missing, mutation events after changing a name and reverting it, and absent fingerprints. All PHP files pass syntax checks and Moodle PHPCS reports no errors or warnings. The existing selector DOM test also passes.

## Previous 1.3.0-beta integration runs

| Moodle | PHP | Database | PHPUnit | Result |
| --- | --- | --- | --- | --- |
| 4.5.15, build 20261005 | 8.3.6 | MariaDB 10.11.14 | 9.6.34 | 22 tests, 71 assertions, passed |
| 5.2.4, build 20261005 | 8.3.6 | MariaDB 10.11.14 | 11.5.55 | 22 tests, 71 assertions, passed |
| 5.3, build 20261005 | 8.3.6 | MariaDB 11.8.6 | 11.5.56 | 22 tests, 71 assertions, passed |

Tests cover all three scopes, full course content, exclusion of learner enrolments, stale task idempotence, failed restore tracking and retry, safe rollback of unchanged copies, protection of modified/untracked content, access isolation, revoked permissions, locks, cancellation, incomplete rollback state, and privacy discovery/export/erasure.

The native Moodle 1.1.2 XMLDB schema was installed in the isolated QA site and upgraded through the plugin's upgrade function. Legacy job/item records remained intact, scope defaulted to `full`, completion timestamps remained unknown/zero, and legacy snapshots remained empty. The final migration completed without XMLDB warnings.

## Browser checks

Chromium 154 against real Moodle Boost pages on 4.5 and 5.3, in Arabic/RTL. Widths 320, 360, 390, 768 and 1280 pixels passed page-width checks with no horizontal page overflow. The hierarchy contained seven nested levels and long Arabic names. Expand/collapse, filtering, single-source selection, three visible scope choices, real form submission, authorised progress JSON, invalid-token rejection and Moodle modal cancellation passed. No browser JavaScript errors were observed in these checks. This is responsive web support, not native Moodle App integration.

## Static and package checks

- PHP syntax: 15 PHP files passed.
- Moodle coding standard: no errors or warnings in the final source run.
- Moodle ESLint: AMD source passed with no errors or warnings.
- Moodle Stylelint: CSS passed; valid CSS math declarations have targeted exceptions for the older grammar checker. The upstream configuration itself emits deprecation notices under Stylelint 15.
- JavaScript selector regression test passed.
- English/Arabic language keys match.
- Full GPL headers are included in source files; the GPL licence file is retained.
- The install archive uses one `clonecategory/` root directory. Native ZIP validator evidence is included in the handoff report.

## Closed review issues

Source: https://github.com/saddamalsalfi/moodle-local_clonecategory/issues?q=is%3Aissue+state%3Aclosed

| Issue | Resolution in this build |
| --- | --- |
| #1 Privacy API | Metadata, context/user discovery, export and all required deletion methods, with integration tests and worker protection. |
| #2 Repository name | Existing `moodle-local_clonecategory` name retained. |
| #3 Translation | UI strings use Moodle language APIs; Arabic and English keys match. Cron diagnostics remain protected technical logs. |
| #4 Boilerplate | Standard full GPL headers in PHP/AMD sources and selector test; copyright/licence declarations included. |
| #5 CI | Official Moodle CI template adapted to compatible branch/PHP/database combinations and actual PHPUnit tests. GitHub execution is still pending. |
| #6 Licence | Root `LICENSE` included in the archive. |
| #7 XMLDB index | Non-unique status index and existing corrective upgrade retained; native installation and legacy migration passed. |

## Additional compatibility checks

Run GitHub CI before publishing, especially the older Moodle branches, PostgreSQL and PHP 8.0/8.1/8.4 combinations not executed locally. Test production-specific themes, course formats and activity plugins on a staging copy. The snapshot protects core state and files but cannot provide atomic exclusion of arbitrary concurrent edits or guarantee coverage of all third-party tables. Active copies should not be edited while rolling back. A hard-killed restore with a changed partial snapshot requires manual review. Missing snapshots cannot be reconstructed safely. Settings-only snapshots affected solely by deferred creation logs can be matched against the exact original hash as described above.

## Badge evidence

Current Marketplace listing: https://marketplace.moodle.com/plugins/4011

- Privacy friendly: already displayed on the listing; this build adds complete audit-data export/erasure and verification.
- Automated testing support: executable Moodle PHPUnit tests and a CI workflow are provided, with actual local results above. Awarding it remains Moodle's decision after review/publication.
- Early bird 5.3: official deadline is 12 October 2026, 23:59 UTC. A tested compatible release must be available before that deadline. Local testing does not constitute publication or an awarded badge.
- Mobile app support: requires actual Moodle App support and the Moodle mobile team's review. Responsive browser styling alone does not qualify.
- Certified Integration: separate application/review programme; code changes do not automatically grant it.
- Historical Early bird badges cannot be obtained retroactively by changing supported-version metadata. Not every award applies to every plugin.

Official references:
https://moodle.org/mod/forum/view.php?id=8149
https://moodle.org/mod/forum/discuss.php?d=332574
https://moodledev.io/general/app/development/plugins-development-guide
https://moodle.com/become-moodle-partner/apply-certified-integration/
https://moodlehq.github.io/moodle-plugin-ci/
