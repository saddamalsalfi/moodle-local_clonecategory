# Production-path benchmark protocol and observed results

## Evidence identity

- Experiment: `20261028_054258_d95d5889c1`, 28 October 2026, 05:42:59–05:49:08 UTC (08:42:59–08:49:08 Asia/Aden).
- Environment: Moodle 5.3 (build 20261005), PHP 8.3.36, MariaDB 11.8.9, shared Linux host, PHP memory limit 3072M, CLI OPcache enabled.
- Tested engine: plugin build 2026102600 / release 1.4.1, with separately installed benchmark revision `production-path-v2-20261028-r4`.
- 1.4.2 integrates that protocol with full source headers, PHPDoc, coding-style formatting and helper tests. It does **not** relabel an earlier live execution as a new 1.4.2 run. AST comparison normalises comments, whitespace and equivalent literal concatenation; it is not a new live compatibility/performance test.
- The compact unedited [evidence directory](benchmarks/2026-10-28-r4/) retains original timestamps, versions and hashes. Its `SHA256.json` verifies the bundled measurements.

## Workload and paths

Both methods copied the real category 2 tree: **136 categories, 432 courses, 7,344 sections including 432 General sections, 1,706 modules (432 forum and 1,274 resource), and 1,274 file references**. Logical file size was 9,769,724,793 bytes; 38 unique contents totalled 147,636,040 bytes. Maximum source absolute depth was 7, relative depth 6. This is not an assessment-heavy or learner-data workload.

Native: core category creation/file APIs, `copy_helper::create_copy`, then 432 actual `core\task\asynchronous_copy_task` executions. Plugin: `manager::create_job(full)`, then one actual `local_clonecategory\task\clone_category_task` executing the batch. No engine, manager, task, permissions or UI changes were needed for 1.4.2.

One fresh copy-worker PHP process per method/trial; actual core tasks run sequentially, not artificially in parallel. Each trial has a new hidden destination and the same frozen source. Users and kept roles are disabled. Optional production backup settings differ; this is a product-workflow comparison, not a controlled ablation of one engine parameter.

## Schedule and endpoints

Final R4 used **one paired block, zero warmups**, seed 20261028, actual order native then plugin. The runner supports counterbalanced seeded schedules across multiple blocks; that does not make this one-block observation order-balanced. Warmups, when requested, are labelled and retained rather than silently erased.

The primary endpoint is readiness from preparation launch through completion of owned grade/calendar tasks. Source inventory/hashing, verification and cleanup are outside it. Copy-workflow time covers preparation plus copying. Each phase has its own process/metrics/log record. Bootstrap and process launch costs are included in wall time, while database and process IO deltas begin after Moodle bootstrap. No selective outlier removal or automatic copy retry is used.

## Observed time and resource measurements

| Metric | Native | Clone Category |
| --- | ---: | ---: |
| Prepare and queue (s) | 99.151746148 | 0.473435575 |
| Copy phase (s) | 121.118289699 | 88.769321589 |
| Owned postprocessing (s) | 7.107034506 | 6.048613044 |
| Prepare plus copy workflow (s) | 220.272916915 | 89.246008050 |
| Primary readiness workflow (s) | 227.381416529 | 95.298894300 |
| Separate verification (s) | 4.419802071 | 4.693262068 |
| Separate cleanup (s) | 16.093895827 | 16.735104873 |
| Child CPU user (s) | 41.692779000 | 29.108812000 |
| Child CPU system (s) | 29.057240000 | 16.988102000 |
| Maximum child RSS (KiB) | 166,988 | 158,440 |
| Maximum PHP allocation (bytes) | 65,011,712 | 63,664,128 |
| Database queries | 893,442 | 688,190 |
| Database read queries | 717,525 | 521,295 |
| Database write queries | 175,917 | 166,895 |
| Process IO read_bytes | 86,016 | 0 |
| Process IO write_bytes | 399,114,240 | 187,379,712 |
| Process IO rchar | 524,226,007 | 399,592,457 |
| Process IO wchar | 91,837,312 | 47,063,795 |
| Production copy tasks | 432 | 1 |
| Owned grade/calendar tasks | 864 | 864 |

Readiness was 58.09% lower in the plugin observation (native/plugin ratio 2.386x). Preparation alone differed by approximately 99 seconds because the native path creates individual controllers/tasks. Do not attribute the entire workflow difference to course restoration speed or lock behaviour.

RSS is the maximum per-process high-water value, not a sum of process peaks or total host memory. PHP allocation is not RSS. Child CPU is not database-server CPU. Database query counts are not affected-row counts or database size. Kernel `read_bytes=0` can reflect cached reads, not absence of file reads. Logical file volume is not incremental disk consumption because Moodle content hashes deduplicate files. Verification may consume more memory than the measured copy endpoint; it is reported separately in phases.csv.

## Content checks and cleanup

Both methods reached `verified_cleaned`, `verification_pass=1`, `source_unchanged=1` and zero scope warnings. There are 568 category/course checks per method (136 + 432), **not** 568 individual file tests. The source and all mapped category parents/names/descriptions, normalised course/section/module design fields and design-file metadata/SHA256 are checked. Expected IDs, system timestamps, hiding and exact Moodle duplicate-name suffix rules are normalised explicitly. Learner enrolments and grade rows remain zero.

Default cleanup is a **common core deletion harness**, using owned mappings, exact membership and final-postprocessing fingerprints. It is not a benchmark of the plugin's own rollback. Changed content blocks deletion; existing plugin rollback guards are never relaxed. The original source and earlier retained copies remained. Final site inventory: 553 categories, 1,738 course records including the front page and the same 23 pre-existing queued task IDs. Engine hashes were unchanged.

Unsupported activities, question banks, nonempty forum posts, groups, blocks or category role/cohort data generate scope warnings that prevent summary eligibility. A zero warning count is scoped to this actual source; it does not establish correctness for every Moodle extension. Full independent quiz/grade/third-party audits require their own verifier.

## Statistical and environmental limits

The independent sample is **one paired batch**, not 432 independent method replications. No significance or general superiority claim is made; n<2 confidence intervals are explicitly unavailable. Ratios are undefined if paired observations contain zero/negative values; those pairs remain in difference/count reporting rather than being silently dropped. The supported exploratory bootstrap uses 10,000 paired resamples only when sufficient blocks exist; it does not remove shared-host confounding.

Native ran first, no cache flush was performed, host load changed, and optional production backup settings differ. No transaction/lock wait/hold timings, DB-server CPU/physical IO, network volume, energy or cross-host inference were measured. No empirical sample proves O(1) runtime or bounded memory at all scales. No publication, certification, new DOI deposit or badge is asserted.

## Reproduction and automation migration

See [USAGE.md](USAGE.md) for current commands. `--source-category=ID` defaults to plan only; mutation requires `--execute`. Use a NEW private output directory on each run. Default six measured pairs and one warmup pair create 14 batches; a 432-course source implies 6,048 temporary course copies, not 432. Preserve failures and raw evidence without selective reruns. Keep the source stable and isolate experiment tasks from concurrent Cron workers.

The optional runner requires PHP 8.1+, process creation, POSIX resource accounting and Linux procfs for full documented metrics. Only its --help and pure regression tests are intended to run without Moodle bootstrap. The plugin's production Moodle 4.0–5.3 declared range is unchanged; actual R4 runtime validation is Moodle 5.3 only.

Removed synthetic flags --tier/--mode/--pipeline/--output/--metadata/--run-offset are rejected, not silently reinterpreted. Historical `cli/scientific_benchmark.csv` remains unchanged and must not be pooled with R4. R1 small repeated validation, R2 name-verifier recovery and R3 final-inventory connection failure are development history, not extra R4 statistical repetitions. Full original evidence remains in the separate `Clonecategory_Benchmark_Revision_2026-10-28` audit package.

## Code provenance

Original R4 entry SHA256: `bc600e5301d5342066bedb0bbb87096c6f75bb8d32d4f45b34229b5ad065a9cf`.

Original R4 library SHA256: `700b7a40ac058751de8d58414c55d71ddf998bf0a85791e53a403469f1dd274c`.

These identify the live-tested files, not the newly formatted 1.4.2 files. Original engine SHA256 (LF-normalised for Windows comparison): manager `0d3246e058342a53d0b8b7f36707da6100c98a5aa609a15a0217db66908cd4a4`; clone task `a1e7604cb99f0afae30880693debd993c4667f9cb7affc83df3278343f82b94b`. The release handoff manifest identifies every final local file independently.
