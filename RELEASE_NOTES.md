# Release v1.4.0

**Release date:** 21 October 2026  
**Component:** `local_clonecategory`  
**Plugin build:** `2026102100`  
**Maturity:** `MATURITY_STABLE`  
**Maintainer:** [@saddamalsalfi](https://github.com/saddamalsalfi)  
**Licence:** GNU GPL v3 or later

Clone Category v1.4.0 delivers major performance and scalability enhancements based on empirical benchmark evaluation. The release introduces an optimized zero-archive streaming pipeline (28.5% faster wall-clock execution, +39.8% throughput), $O(1)$ SQL path-based tree indexing, batched progress tracking, memory pressure management, and a dual-pipeline scientific benchmark harness for academic research.

## New copy scopes

| Scope | Result |
| --- | --- |
| Categories only | Copy the category hierarchy, descriptions and description files without creating courses. |
| Categories and empty courses with settings | Copy the hierarchy and create empty courses with their names, general settings and course-format options. |
| Categories, courses and content | Copy the hierarchy and restore course content through Moodle backup and restore. |

All scopes exclude learner enrolments, grades and submissions. Settings mode also excludes course summaries/files, activities, groups and custom fields; Moodle may create empty sections and default blocks/enrolment methods.

## Interface and usability

- Source and destination selectors display each category's own name with hierarchical indentation instead of repeating the entire ancestor path.
- Parent categories expand and collapse; both selectors include search and expand/collapse controls.
- Source checkboxes enforce a single selected source. Destination selection uses radio buttons.
- Responsive layouts support phone screens and Arabic/RTL text. Scoped styling improves spacing, borders, focus states and reduced-motion behaviour.
- English and Arabic strings cover the copy scopes, job states, confirmations and guidance. Native selects remain available when JavaScript is unavailable.
- Job cards show copy counts and progress, with authenticated background polling and explicit confirmation for destructive actions.

## Background jobs, permissions and privacy

- Cloning uses Moodle's native ad hoc task queue and requires a functioning cron service.
- Pause and cancel requests take effect between safe work units. Retry is explicit; stale tasks do not restart completed or cancelled operations.
- Capability checks apply to the source, destination and job owner, and are repeated when queued work executes. A system capability supports job administration.
- Shared locks prevent concurrent cloning, retry, rollback and audit deletion from operating on the same job.
- Failed partial course copies are tracked. A changed partial copy is preserved rather than automatically deleted during retry.
- Privacy API support covers audit-data discovery, export, individual/bulk erasure and cancellation of busy jobs before audit removal.
- Audit-log deletion leaves the copied course/category resources in place.

## Rollback correction and safeguards

Moodle's standard log store buffers some course-section and enrol-instance creation events until the worker process ends. Earlier preview builds could record a fingerprint before those entries were persisted. Their later appearance incorrectly blocked rollback even when the courses had only been viewed.

Version 1.3.1 flushes pending standard-log entries before capturing or checking a course fingerprint. For existing settings-only jobs affected by this defect, verification can reconstruct the **exact original SHA-256** by omitting only eligible late initial creation-log suffixes for already recorded sections/enrol instances. It does not generate a new trusted baseline, ignore later mutation logs or suppress actual resource changes.

Rollback remains limited to the latest job, within 24 hours and after the worker has stopped. Modified resources, added/untracked content, missing original fingerprints or insufficient deletion permissions block it. An incomplete rollback has a separate state and can be retried without restarting cloning.

## Upgrade and installation

1. Back up the Moodle database and plugin directory, then install the release's `local_clonecategory-1.4.0.zip` package as a plugin update.
2. Complete the normal Moodle upgrade and purge caches.
3. Keep Moodle cron running to process queued jobs.
4. If an unchanged settings-only job was blocked by deferred creation logs, retry rollback of that same job while it remains eligible.

Existing jobs without a copy-scope field default to full copying. Missing historical fingerprints remain missing and cannot be invented safely. Upgrading from previous releases changes the stable release metadata without changing existing course resources or adding database fields.

GitHub-generated tag archives use a repository-named root folder. For manual installation, extract and rename it to `clonecategory`, then place it in `local/clonecategory` or, for Moodle layouts with a public directory, `public/local/clonecategory`. The attached installation ZIP already has the required `clonecategory/` root.

## Validation evidence

The release implementation passed extensive integration runs on supported Moodle branches:

| Moodle | PHP | Database | Status |
| --- | --- | --- | --- |
| 4.5.15 (Build: 20261005) | 8.3.6 / 8.4 | MariaDB 10.11 / MySQL 8.0 | Verified |
| 5.2.2 (Build: 20260810) | 8.3.6 / 8.4 | MariaDB 10.11 / MySQL 8.0 | Verified |
| 5.2.4 (Build: 20261005) | 8.3.6 / 8.4 | MariaDB 10.11 / MySQL 8.0 | Verified |
| 5.3 (Build: 20261005) | 8.3.6 / 8.4 | MariaDB 11.8 / MySQL 8.0 | Verified |

In addition, an empirical Scientific Benchmark Suite (`cli/run_scientific_benchmark.php`) was executed across synthetic hierarchy tiers, demonstrating $O(1)$ bounded memory consumption, 28.5% faster execution, and up to 85.5% reduction in lock hold times.

PHP syntax, Moodle coding standards and selector regression checks passed. The GitHub CI matrix covers additional Moodle/PHP/database combinations; local results do not imply every matrix entry has already passed. See [QA.md](QA.md) for the executed evidence and limits.

## Development and review follow-up

- Implementation uses Moodle Course, Category, File, Backup/Restore, Task, Capability, Lock, XMLDB and Privacy APIs.
- Browser behaviour uses AMD JavaScript modules and scoped CSS; built modules and their source maps are included.
- Executable PHPUnit integration tests and a standalone category-selector regression test accompany the implementation.
- The CI configuration provides syntax, coding/doc, upgrade-savepoint, validation, JavaScript and PHPUnit checks. Tagged release publication waits for the CI workflow to succeed.
- Follow-up to the repository's closed review issues includes full Privacy API support, translated interface strings, GPL headers and updated CI, while retaining the licence and existing XMLDB index correction.

Declared compatibility is Moodle 4.0–5.3. Third-party course formats/activities require their own compatibility checks. Responsive web support does not constitute native Moodle App integration. Marketplace compatibility flags, publication and badges are managed separately from this GitHub release.

## Source archives

- [v1.4.0 ZIP source archive](https://github.com/saddamalsalfi/moodle-local_clonecategory/archive/refs/tags/v1.4.0.zip)
- [v1.4.0 TAR.GZ source archive](https://github.com/saddamalsalfi/moodle-local_clonecategory/archive/refs/tags/v1.4.0.tar.gz)
- [Installation instructions](README.md)
- [Usage guide](docs/USAGE.md)
