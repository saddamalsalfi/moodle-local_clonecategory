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

For a settings-only preview job blocked after merely viewing a course, update to 1.4.0 and retry rollback of the same eligible job. The buffered-log correction preserves the original fingerprint; it cannot recreate a missing historical baseline.

## Scientific Benchmark Tool

To evaluate duplication performance and measure lock durations, throughput, and memory consumption across synthetic trees:

```bash
php local/clonecategory/cli/run_scientific_benchmark.php --tier=1 --runs=20 --pipeline=optimized
```

Options include:
- `-t, --tier=1..5`: Benchmark complexity presets (Tier 1: 5 courses up to Tier 5: 100 courses).
- `-r, --runs=N`: Number of repetitions per mode (default: 20).
- `-m, --mode=both|ahtd|baseline`: Modes to evaluate (default: both).
- `-p, --pipeline=standard|optimized`: Standard identical isolation or full optimized AHTD streaming pipeline.
- `-o, --output=filename.csv`: Output CSV file (default: scientific_benchmark.csv).
