# Clone Category usage

1. Open **Clone Category** from the permitted category or plugin navigation.
2. Under **Choose what to copy**, select categories only, empty courses with settings, or full course content.
3. Expand or search the **Source Category** tree and tick one checkbox. Selecting another source replaces it.
4. Under **Target Category (Parent)**, select the parent for the copied hierarchy. A source category or its descendant cannot be its own destination.
5. Expand **Name the new copies** and optionally enter category/course suffixes. Leave a suffix blank to retain the original public name.
6. Select **Start Cloning** and open **Scheduled Tasks & Operations**.
7. Keep Moodle cron running. Check the job status, progress and category/course counts until completion.
8. Pause or cancel between safe work units when needed. A failed job must be retried explicitly; already completed copies are tracked to avoid duplication.
9. To remove eligible copies, select **Rollback & Undo** and confirm. Only the latest job within 24 hours is eligible. Changed resources and untracked additions are preserved by blocking rollback.
10. **Delete Log** removes inactive tracking records; it does not delete the cloned institutional resources.

Settings mode produces empty courses. Full-content mode excludes learner enrolments, grades and submissions. Native selects remain available if JavaScript is disabled. Phone and Arabic/RTL layouts use the same selection rules.

For a settings-only preview job blocked after merely viewing a course, update to 1.4.2 (the buffered-log fix originally shipped in 1.3.1) and retry rollback of the same eligible job. The buffered-log correction preserves the original fingerprint; it cannot recreate a missing historical baseline.

## Production-path benchmark CLI in 1.4.2

The benchmark is an explicit CLI administration tool, separate from normal UI cloning. PHP 8.1+, `proc_open`, POSIX resource accounting and Linux `/proc` are required for the documented full metrics. Moodle's core copy helper, core ad-hoc runner and the installed plugin must provide the production APIs. The executed live evidence is Moodle 5.3 only; no new cross-version benchmark qualification is implied.

Run from the Moodle root (omit `public/` on older layouts):

```bash
php public/local/clonecategory/cli/run_scientific_benchmark.php --help
php public/local/clonecategory/cli/run_scientific_benchmark.php --source-category=2
php public/local/clonecategory/cli/run_scientific_benchmark.php --source-category=2 --execute --runs=1 --warmups=0
php public/local/clonecategory/cli/run_scientific_benchmark.php --source-category=2 --execute --runs=6 --warmups=1
```

`--execute` authorises the creation of actual hidden copies. The default command only writes a private plan and inventory. At 432 source courses, six measured pairs plus one warmup pair produce **6,048 temporary course copies**, across 14 trials. The schedule and intended workload are printed before copying.

| Option | Meaning |
| --- | --- |
| `--source-category=ID` | Existing source root and all descendants; required for a site experiment. |
| `--execute` | Execute actual creation/copy/verification, otherwise plan only. |
| `--runs=6` | Measured paired blocks, 1 to 1000; not course count. |
| `--warmups=1` | Warmup paired blocks, 0 to 100; raw evidence is retained. |
| `--seed=20261028` | Reproducible balanced ordering seed, up to 2147483647. |
| `--timeout=7200` | Per-phase timeout in seconds, 10 to 86400. |
| `--output-dir=/private/new/path` | New, nonempty-output reuse is rejected; outside served code. |
| `--retain` | Retain verified destinations instead of default guarded core cleanup. |
| `--execute --cleanup-run=/experiment/runs/RUN` | Guarded cleanup of a previously verified owned run. |

Internal `--phase`, `--experiment-dir` and `--run` options are worker implementation details, not a supported shortcut for resuming failed work. A failure stops further scheduled trials and preserves its evidence; inspect it before any recovery. No general cron sweep or unrelated task execution is performed. Keep the source stable and prevent other workers from claiming the experiment's queued tasks during measurement.

Readiness includes preparation, production copying and the owned grade/calendar tasks. Verification and cleanup have separate metrics. Both methods use one fresh copy-worker process per trial; core course tasks are sequential, not artificially parallel. Default cleanup is shared Moodle core deletion guarded by ownership and final content fingerprints; it is **not** a comparison of the plugin's rollback feature.

See [BENCHMARK.md](BENCHMARK.md) for units, eligibility, supported content checks, raw-file mapping and statistical limitations. The old `--tier`, `--mode=baseline|ahtd`, `--pipeline`, `--output`, `--metadata` and `--run-offset` flags are removed, not aliases. Store real outputs outside the plugin tree. Unchanged legacy CSV is historical only.
