# Release v1.4.2

**Prepared:** 29 October 2026  
**Component/build:** `local_clonecategory` / `2026102900`  
**Release/maturity:** `1.4.2` / `MATURITY_STABLE`  
**Licence:** GNU GPL v3 or later  
**Maintainer:** Saddam Al-Slfi

This maintenance update replaces only the benchmark infrastructure and documentation/metadata. Production cloning, all three scopes, capability checks, task pause/cancel/retry, guarded rollback, privacy, category UI and compiled assets remain unchanged. No database schema or existing audit/resource data is changed by its upgrade savepoint.

## Benchmark changes

The entry point calls real Moodle core async copy tasks and the actual installed plugin production task. It freezes a real source tree, uses isolated per-phase workers, records warmups separately, exports raw CSV/JSON/logs and checks mapped content plus file SHA256. Readiness includes preparation and owned grade/calendar work. Verification and common ownership-checked cleanup are timed separately. The final environment inventory reopens the idle controller database connection outside measured work.

The old BASELINE/AHTD synthetic loop and --tier/--pipeline flags are removed. See [migration and command options](docs/USAGE.md). PHP 8.1+ is required by this optional CLI; the production plugin retains its existing Moodle 4.0 minimum and 4.0–5.3 declared range. Actual benchmark evidence covers Moodle 5.3 / PHP 8.3.36 / MariaDB 11.8.9 only.

## Observed live result

On 28 October 2026, R4 completed one full-tree pair: 136 categories and 432 courses per method, 7,344 sections including General, 1,706 modules and 1,274 file references. Native readiness was **227.381416529 seconds**, plugin readiness **95.298894300 seconds** (58.09% lower elapsed time in that observation). Both passed content/source checks and guarded cleanup.

This is not a universal performance claim, a significance result, a lock-duration experiment or an asymptotic complexity proof. Native ran first; caches were not flushed and production backup options differ. The earlier synthetic 28.5%/85.5% claims are not carried forward as product evidence. See [full protocol, metrics and limitations](docs/BENCHMARK.md).

## Installation and upgrade

1. Preserve a matched site/database backup and existing job/evidence records.
2. Deploy the complete `clonecategory/` package to `local/clonecategory` or `public/local/clonecategory`.
3. Run the normal Moodle upgrade and purge caches. Savepoint `2026102900` changes metadata only.
4. Continue normal cron for production jobs. Benchmarking is explicit CLI work and is not scheduled by installing this update.

The local install archive is `local_clonecategory-1.4.2.zip`. Version metadata is not a successful CI run. New source hashes differ from the original R4 executable because of packaging and coding-style integration; the preserved R4 evidence identifies its exact tested code. Do not pool experiments across source revisions.

## Verification

See [QA.md](QA.md) for executed local checks versus historical integration evidence and pending external matrix runs. Only CRLF-to-LF line endings are normalised in the production engine files; their text/logic is unchanged and their final SHA256 matches the live R4 engine exactly. No new browser or multi-version live qualification is claimed by this metadata update.
