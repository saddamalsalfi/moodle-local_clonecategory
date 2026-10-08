# Clone Category (`local_clonecategory`)

Clone Moodle category hierarchies using Moodle's background task, course creation and backup/restore APIs.

## Version 1.3.2

Stable release of the three clone scopes, responsive category selectors and safe background operations. See [release notes](RELEASE_NOTES.md), [change history](CHANGELOG.md) and [validation evidence](QA.md).

Download the Moodle installation ZIP from the [v1.3.1 release](https://github.com/saddamalsalfi/moodle-local_clonecategory/releases/tag/v1.3.1). GitHub-generated tag archives are source archives: extract and rename their root folder to `clonecategory` before manual installation.

### Rollback correction

Standard log entries are flushed before a resource fingerprint is captured or checked. Version 1.3.0-beta could capture its baseline before Moodle persisted buffered section/enrol-instance creation logs, causing rollback to be blocked even after viewing only. For existing settings-only jobs, rollback can reconstruct the exact stored fingerprint by omitting only late initial creation logs for already recorded resources, matching the original SHA-256. It never replaces missing baselines or ignores later mutation logs or changes to resource records. The latest-job and 24-hour limits still apply.

### Copy scopes

| Scope | Copied resources |
| --- | --- |
| Categories only | Category hierarchy and category descriptions/files; no courses. |
| Categories and course settings | Category hierarchy plus empty courses, names, general settings and course-format options. |
| Full content | Category hierarchy plus course content restored through Moodle backup/restore, with learner data excluded. |

Settings mode excludes activities, course summary content/files, overview files, learner enrolments, grades, submissions, groups, custom fields and activity completion rules. Moodle may create empty sections and default blocks/enrolment methods. Third-party course formats require independent compatibility tests.

Full mode excludes users, role assignments, enrolments, logs and grade histories from the backup/restore plan. Installed activity plugins must support Moodle backup and restore. It does not copy students or their submissions.

### Category selection and accessibility

- Source and destination use nested, searchable lists with their own category names, without repeating ancestor paths.
- Clicking a parent name expands/collapses its children; separate controls expand/collapse all nodes.
- Source checkboxes permit one selected source; destination radio buttons permit one destination.
- Native form fields remain available if JavaScript cannot load.
- Names wrap within bounded scroll areas. Styles support Arabic/RTL, narrow screens, keyboard focus and reduced motion.

### Background operations

Configure Moodle cron normally. Copies run as native ad-hoc tasks; the browser polls authorised progress approximately every five seconds. Large course restores are not run inside the browser request.

Pause and cancel requests stop at safe category/course boundaries. An ongoing course restore finishes before stopping. Retry queues a paused or failed job and skips completed tracked resources. Failed restores retain a record of the partial course; cleanup on retry is permitted only when its snapshot still matches. A process killed during restoration can require manual inspection if the snapshot no longer matches.

### Permissions and safety

`local/clonecategory:clone` and `moodle/category:manage` are required in the source category; destination category management is also required. Course modes additionally require course creation, and full mode requires source backup and destination restore capabilities. Permissions are checked again when queued work executes. `local/clonecategory:managejobs` grants system-level job administration.

Actions use POST and a Moodle session token. A global operation lock prevents competing starts; workers, retries, rollback and audit deletion share a per-job lock. Destinations within the source subtree are rejected.

Rollback is available for the latest job, within 24 hours, after its worker has stopped. An incomplete rollback has a distinct state and cannot be resumed as a cloning task; only rollback may be retried. All recorded resources are checked before deletion. Changed resources, additional courses/subcategories, missing original snapshots or insufficient delete permissions block rollback. The fingerprint is a conservative check of core resource records and files, enrolments, activity instances and mutation logs; it is not an atomic lock against edits made by other Moodle plugins or a guarantee for arbitrary third-party tables. Stop editing/using the copied resources while rolling back.

Audit deletion removes only inactive tracking records, leaving copied resources. Privacy export/deletion includes job and item audit records. A busy privacy deletion requests cancellation and fails explicitly, preserving tracking for retry after the worker stops. Moodle core handles course resources, queued-task metadata and task logs through its own Privacy API.

### Upgrading

Install the ZIP into `local/clonecategory`, or `public/local/clonecategory` in Moodle versions using a public directory, then run the normal Moodle upgrade and purge caches. Existing jobs default to full scope. Old items retain empty snapshots because their original state cannot be reconstructed reliably; destructive rollback of those items is blocked.

### Validation and compatibility

Minimum declared Moodle version remains 4.0. This session's actual test results are recorded in `QA.md`; the declared range inherited from the previous release is not evidence that every combination has passed. The CI workflow covers the declared Moodle branches with appropriate PHP versions, MariaDB, PostgreSQL, PHPUnit, PHP lint, Moodle coding/doc checks, upgrade savepoints, validation and JavaScript checks. CI must run on GitHub before release; a configured workflow is not a successful CI run.

Run local selector tests with `node tests/category_tree_test.cjs`. Run integration tests within a configured Moodle PHPUnit environment using the `local_clonecategory_testsuite` suite. Build AMD modules using Moodle's JavaScript toolchain after changing sources.

### Marketplace badges

Privacy support and automated tests provide evidence for applicable badges. Responsive web layouts do not constitute Moodle App integration. Early bird badges require a tested, compatible release published before Moodle's specified deadline. Certified Integration has a separate application and review. Badge decisions belong to Moodle; this release does not claim badges it has not been awarded.

## Licence

GNU GPL v3 or later. Maintainer: Saddam Al-Slfi. Source: https://github.com/saddamalsalfi/moodle-local_clonecategory
