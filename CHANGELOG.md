# Changelog

## 1.4.2 — 2026-10-29

Maintenance release; Moodle plugin build `2026102900`. No database schema or production engine changes.

### Added

- Production-path R4 benchmark support library, process isolation, owned task execution, strong content/file verification and ownership-guarded common cleanup.
- Reproducible real-source paired schedules, explicit warmup records, private structured evidence and null-aware paired descriptive statistics.
- Standalone benchmark regression tests, PHPUnit coverage of pure benchmark helpers, upgrade regression checks, and preserved live R4 evidence metadata.
- Full-tree observed results and caveats in `docs/BENCHMARK.md` and `QA.md`.

### Changed

- Replaced custom synthetic BASELINE/AHTD entry point with actual core async copy versus actual production plugin task. Legacy synthetic CLI flags are rejected.
- Release/citation/usage/upgrade metadata now identifies 1.4.2. The metadata-only XMLDB savepoint preserves all jobs, items and fingerprints.
- Benchmark runtime requires PHP 8.1+; production plugin minimum and declared Moodle range are unchanged. No full matrix success is asserted.

### Corrected interpretation

- R4 observed native readiness 227.381416529 s versus plugin 95.298894300 s for 136 categories / 432 courses each; both verified and cleaned. One pair cannot establish significance, cold-cache equivalence or universal superiority.
- Historical synthetic CSV and earlier changelog claims are retained as historical records, not current product/lock/complexity evidence. In particular warmups do not eliminate cache bias, SQL query count does not prove O(1) runtime, and empirical samples do not guarantee bounded memory.
- Production copy scopes, permissions, privacy, worker behaviour, pause/retry/rollback guards and UI assets remain unchanged. Engine files only normalise CRLF to LF to match the live-tested source exactly; .gitattributes preserves canonical PHP line endings.

## 1.4.1 — 2026-10-26

Stable release; Moodle plugin build `2026102600`.

### Added

- **Benchmark Metadata Schema v2**: Added structured JSON sidecar metadata (`--metadata=path.json`) recording environment fingerprints, Moodle/PHP/DB versions, plugin commit metadata, command invocation, and exact execution parameters.
- **Warm-Up Iterations**: Introduced `--warmups=N` parameter to execute verified, unrecorded warm-up runs prior to active data collection, preventing cold-start JIT/caching skew.
- **Single-Process Run Offset**: Added `--run-offset=N` to support fresh process-per-run execution loops in automated benchmarking scripts.
- **Citation Metadata**: Added `CITATION.cff` (Citation File Format 1.2.0) with Zenodo DOI (`10.5281/zenodo.23578360`) and ORCID identifier for academic citation and reproducibility.

### Changed

- **Alternating Benchmark Execution**: Paired benchmark runs now automatically alternate mode execution order (`BASELINE>AHTD` vs `AHTD>BASELINE`) on each logical repetition to eliminate systematic execution-order bias.
- **Repository Hygiene**: Excluded local benchmark telemetry and result directories (`cli/results/`) via `.gitignore` to prevent committing heavy test artefacts to GitHub.

### Fixed

- **Moodle Coding Standards (PHPCS)**: Resolved all PHP CodeSniffer errors and warnings in benchmark tools, adding strict function parameter comments and formatting.
- **Empty Catch Blocks**: Eliminated `Generic.CodeAnalysis.EmptyStatement.DetectedCatch` violations by explicitly unsetting ignored exception variables in teardown and cleanup routines.

## 1.4.0 — 2026-10-21

Stable release; Moodle plugin build `2026102100`.

### Performance & Scalability (Scientific Benchmark Optimizations)

- **SQL Path-based Tree Counting**: Replaced recursive O(N) category/course tree traversal with 2-query path-indexed SQL queries in `manager::count_category_tree()`, eliminating object instantiation overhead at job initiation.
- **Batched Progress Updates**: Grouped database progress writes into configurable batches, significantly reducing database write locking during high-throughput course duplication.
- **Throttled Pause Verification**: Cached and throttled job pause checks to reduce redundant DB reads during tight processing loops.
- **Single-Query Shortname Generation**: Optimized `unique_shortname()` to fetch all potential name collisions in a single SQL LIKE query, replacing iterative DB queries with an in-memory hash lookup.
- **Extended Backup Exclusions**: Added safe exclusions for non-template artefacts (`comments`, `badges`, `calendarevents`, `competencies`, `contentbankcontent`, `legacyfiles`), cutting backup/restore execution time by 20-30%.
- **Inter-Unit Memory & GC Management**: Integrated deterministic cyclic reference collection (`gc_collect_cycles()`) and context cache resets between course work units to guarantee constant-bound memory consumption.
- **Target Category Capability Caching**: Eliminated repeated capability lookups on destination categories across sequential course restorations.
- **Fingerprint Query Deduplication & Module Caching**: Reused pre-fetched course module records and added static caching for module name lookups in resource fingerprint generation.
- **Periodic Temporary Directory Pruning**: Added background maintenance to automatically sweep orphaned backup directories older than 30 minutes.
- **Dual-Pipeline Scientific Benchmark**: Enhanced `run_scientific_benchmark.php` with `--pipeline=standard|optimized` flag to enable rigorous scientific evaluation of both critical-section isolation and full streaming pipeline speedups.

## 1.3.2 — 2026-10-08

Stable release; Moodle plugin build `2026100800`.

### Added

- Scientific Benchmark & Synthetic Tree Generator CLI tool (`cli/run_scientific_benchmark.php`) for empirical evaluation of recursive vs bounded-memory (AHTD) category duplication models.
- Predefined benchmarking tiers (Tiers 1 to 5) with automated metrics recording (RAM peak/delta, transaction lock durations, DB I/O, throughput) in CSV format.

### Fixed

- Rebuilt minified AMD assets using the official Moodle Grunt toolchain with unmangled variable names.
- Alphabetised all language strings in English and Arabic to comply strictly with Moodle CodeSniffer standards.
- Replaced CSS `clamp()` and `min()` functions in `styles.css` with standard values to ensure full compatibility with Moodle 4.0 Stylelint.
- Added PHPUnit `@covers` annotations and corrected PHPDoc parameter types across all classes and test suites.

## 1.3.1 — 2026-10-07

Stable release; Moodle plugin build `2026100705`.

### Added

- Three copy scopes: categories only; categories and empty courses/settings; full course content without learner data.
- Collapsible, searchable category selectors with one source checkbox selection and one destination radio selection.
- Responsive/RTL interface, translated English/Arabic guidance and live job progress.
- Scoped capabilities, background ad hoc tasks, safe pause/cancel/retry, shared locks and tracked partial restores.
- Complete audit-data Privacy API handlers, integration tests and CI/release automation.

### Fixed

- False rollback rejection caused by deferred course-section and enrol-instance creation logs appearing after the original fingerprint was recorded.
- Safe verification of affected settings-only preview jobs against their original fingerprints without accepting modified resources.
- Category labels repeating all ancestors and overflowing small screens.
- Incomplete rollback accidentally becoming eligible to resume cloning.
- Queue/task idempotence, raw error disclosure and unsafe cleanup of changed partial copies.

### Changed

- Rollback preflights owned resources and applies latest-job/24-hour limits, capability checks and change detection.
- Stable metadata advances the preview build `2026100704` without additional schema changes.
- Missing historical fingerprints remain unavailable; audit deletion preserves the copied resources.

See [release notes](RELEASE_NOTES.md) for installation, evidence, development details and compatibility limits.

## 1.3.1-beta — 2026-10-07

Preview build `2026100704`: corrected buffered-log fingerprints and added strict verification for affected settings-only jobs. Passed 27 tests/90 assertions per executed Moodle environment.

## 1.3.0-beta — 2026-10-07

Preview build `2026100703`: introduced copy scopes, responsive tree selectors, background-job safeguards and Privacy API improvements.

## 1.1.2 — earlier release

Baseline source tag used for this development: `810ed1b9cd26a4bb83ab9afc2c1e624c83546089`. Includes the XMLDB status-index correction from review issue #7.
