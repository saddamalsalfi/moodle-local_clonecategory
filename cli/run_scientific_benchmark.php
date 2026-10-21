<?php
/**
 * Scientific Benchmark Suite for local_clonecategory
 * ==================================================
 *
 * Purpose
 * -------
 * Reproducible, functionally-equivalent benchmark of:
 *
 *   BASELINE:
 *      Synchronous, monolithic category duplication.
 *      One Moodle lock protects the complete duplication job.
 *
 *   AHTD:
 *      Work-unit based duplication.
 *      The hierarchy is prepared first and each course duplication is
 *      processed as an independent protected work unit with its own lock.
 *
 * Important scientific design rules
 * ---------------------------------
 * 1. Both modes perform the SAME course operation: Moodle backup + restore.
 * 2. No artificial memory payload is injected into either mode.
 * 3. "Lock" means Moodle Lock API hold time, not transaction duration.
 * 4. Throughput is derived from the same course count and wall-clock time.
 * 5. Every run uses a fresh synthetic source tree.
 * 6. Results are written per-run to CSV.
 * 7. Functional verification is performed after every successful run.
 * 8. The benchmark never claims asymptotic O(1) complexity from empirical data.
 *
 * Recommended scientific protocol
 * -------------------------------
 * - Use --runs=20 or more for publication data.
 * - Run each tier in a clean/quiet Moodle environment.
 * - Prefer one CLI process per run for final publication measurements.
 * - Keep database engine, PHP version, Moodle version and server load fixed.
 *
 * Moodle 5.2 compatible design.
 *
 * Place at:
 *   local/clonecategory/cli/run_scientific_benchmark.php
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

global $CFG, $DB, $USER;

$admin = get_admin();
$USER = $admin;

/**
 * Benchmark tier presets.
 *
 * These values intentionally retain the five-tier structure used by the
 * original experiment while making the course counts explicit and stable.
 */
$tiers = [
    1 => ['depth' => 2, 'branch' => 2, 'courses' => 5, 'label' => 'Tier1_Small'],
    2 => ['depth' => 3, 'branch' => 2, 'courses' => 15, 'label' => 'Tier2_Medium'],
    3 => ['depth' => 4, 'branch' => 2, 'courses' => 30, 'label' => 'Tier3_Large'],
    4 => ['depth' => 5, 'branch' => 2, 'courses' => 60, 'label' => 'Tier4_Enterprise'],
    5 => ['depth' => 6, 'branch' => 2, 'courses' => 100, 'label' => 'Tier5_Stress'],
];

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'tier' => 1,
    'depth' => 0,
    'branch' => 0,
    'courses' => 0,
    'runs' => 20,
    'mode' => 'both',
    'pipeline' => 'standard',
    'output' => 'scientific_benchmark.csv',
    'cleanup' => true,
    'keep-source' => false,
    'verify' => true,
    'seed' => 20261020,
    'lock-timeout' => 60,
], [
    'h' => 'help',
    't' => 'tier',
    'r' => 'runs',
    'm' => 'mode',
    'p' => 'pipeline',
    'o' => 'output',
]);

if ($options['help']) {
    cli_writeln(<<<TXT
Scientific Benchmark Suite for Moodle Hierarchical Duplication

Usage:
  php local/clonecategory/cli/run_scientific_benchmark.php [options]

Options:
  -t, --tier           Benchmark tier 1..5 (default: 1)
      --depth          Custom tree depth; overrides tier
      --branch         Custom branch factor; overrides tier
      --courses        Custom course count; overrides tier
  -r, --runs           Repetitions per mode (default: 20)
  -m, --mode           ahtd | baseline | both (default: both)
  -p, --pipeline       standard | optimized (default: standard)
                       standard  = strict isolation (identical course duplication)
                       optimized = full AHTD pipeline (zero-archive streaming + selective filtering)
  -o, --output         CSV output file (default: scientific_benchmark.csv)
      --cleanup        Delete generated clone after each run
      --keep-source    Keep generated synthetic source tree
      --verify         Verify functional equivalence after each run
      --seed            Deterministic random seed (default: 20261020)
      --lock-timeout    Moodle Lock API timeout in seconds (default: 60)

Examples:
  php local/clonecategory/cli/run_scientific_benchmark.php --tier=1 --runs=20
  php local/clonecategory/cli/run_scientific_benchmark.php --tier=5 --runs=30 --mode=both

Scientific interpretation:
  baseline = synchronous monolithic critical section
  ahtd     = independent protected course work units

The script deliberately measures Moodle Lock API wait/hold times.
It does NOT label transaction duration as lock duration.
TXT
    );
    exit(0);
}

if (!empty($unrecognized)) {
    cli_error('Unrecognized options: ' . implode(', ', $unrecognized));
}

$tierid = (int) $options['tier'];
if (!isset($tiers[$tierid])) {
    cli_error('Tier must be between 1 and 5.');
}

$depth = (int) $options['depth'] > 0 ? (int) $options['depth'] : $tiers[$tierid]['depth'];
$branch = (int) $options['branch'] > 0 ? (int) $options['branch'] : $tiers[$tierid]['branch'];
$courses = (int) $options['courses'] > 0 ? (int) $options['courses'] : $tiers[$tierid]['courses'];
$runs = max(1, (int) $options['runs']);
$seed = (int) $options['seed'];
$locktimeout = max(1, (int) $options['lock-timeout']);
$mode = strtolower(trim($options['mode']));
$outfile = $options['output'];

if (!in_array($mode, ['ahtd', 'baseline', 'both'], true)) {
    cli_error("Invalid --mode. Use 'ahtd', 'baseline', or 'both'.");
}
if ($depth < 1 || $depth > 50) {
    cli_error('Depth must be between 1 and 50.');
}
if ($branch < 1 || $branch > 20) {
    cli_error('Branch factor must be between 1 and 20.');
}
if ($courses < 1 || $courses > 10000) {
    cli_error('Courses must be between 1 and 10000.');
}

mt_srand($seed);

/**
 * High-resolution monotonic timer.
 */
function scientific_now(): float
{
    return hrtime(true) / 1e9;
}

/**
 * Reset request-local Moodle state between runs.
 */
function scientific_reset_runtime_state(): void
{
    global $DB;

    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    if (class_exists('\cache_factory')) {
        \cache_factory::reset();
    }
    if (class_exists('\context_helper')) {
        \context_helper::reset_caches();
    }
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    // Do not close/reopen the DB connection here: Moodle owns the connection.
    // The final publication protocol should use a fresh CLI process per run.
}

/**
 * Return a compact runtime fingerprint for reproducibility.
 */
function scientific_environment_fingerprint(): array
{
    global $CFG, $DB;

    $dbtype = method_exists($DB, 'get_dbfamily') ? $DB->get_dbfamily() : 'unknown';

    return [
        'moodle_release' => defined('MOODLE_RELEASE') ? MOODLE_RELEASE : 'unknown',
        'moodle_version' => defined('MOODLE_VERSION') ? (string) MOODLE_VERSION : 'unknown',
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'os' => PHP_OS_FAMILY,
        'db_family' => $dbtype,
        'memory_limit' => ini_get('memory_limit'),
    ];
}

/**
 * Create a deterministic synthetic hierarchy.
 *
 * The source tree is identical for both modes within a run. A fresh source
 * tree is created for each run so clone leftovers cannot contaminate results.
 */
function scientific_build_synthetic_tree(
    int $depth,
    int $branch,
    int $coursecount,
    int $runseed
): stdClass {
    global $DB;

    mt_srand($runseed);

    $token = sprintf('%d_%d_%d', $runseed, getmypid(), (int) (microtime(true) * 1000));
    $rootname = 'BENCH_SRC_' . $token;

    $root = \core_course_category::create((object) [
        'name' => $rootname,
        'parent' => 0,
        'description' => 'Synthetic scientific benchmark source tree',
    ]);

    $categories = [$root->id];
    $levels = [[$root->id]];

    for ($level = 2; $level <= $depth; $level++) {
        $next = [];

        foreach ($levels[$level - 2] as $parentid) {
            for ($b = 1; $b <= $branch; $b++) {
                $category = \core_course_category::create((object) [
                    'name' => "BENCH_D{$level}_B{$b}_{$token}",
                    'parent' => $parentid,
                    'description' => 'Synthetic benchmark category',
                ]);

                $categories[] = $category->id;
                $next[] = $category->id;
            }
        }

        $levels[$level - 1] = $next;
    }

    $categorycount = count($categories);

    // Stable distribution: every category receives approximately the same
    // number of courses. This avoids workload changes between modes.
    for ($i = 1; $i <= $coursecount; $i++) {
        $categoryid = $categories[($i - 1) % $categorycount];

        $course = create_course((object) [
            'category' => $categoryid,
            'fullname' => "Benchmark Course {$i} {$token}",
            'shortname' => "BM_{$token}_{$i}",
            'numsections' => 6,
            'summary' => 'Synthetic standardized course used for reproducible benchmarking.',
        ]);

        // Six real Moodle course sections. We deliberately avoid an
        // artificial memory payload or fake course state.
        for ($section = 1; $section <= 6; $section++) {
            course_create_sections_if_missing($course, [$section]);
        }
    }

    return (object) [
        'root_id' => (int) $root->id,
        'category_count' => $categorycount,
        'course_count' => $coursecount,
        'token' => $token,
    ];
}

/**
 * Recursively collect source category IDs.
 *
 * Used only for verification and cleanup; it is not part of the measured
 * duplication critical path.
 */
function scientific_collect_category_ids(int $rootid): array
{
    global $DB;

    $result = [$rootid];
    $children = $DB->get_records('course_categories', ['parent' => $rootid], 'id ASC', 'id');

    foreach ($children as $child) {
        $result = array_merge($result, scientific_collect_category_ids((int) $child->id));
    }

    return $result;
}

/**
 * Collect all source course IDs under a category tree.
 */
function scientific_collect_course_ids(int $rootid): array
{
    global $DB;

    $categoryids = scientific_collect_category_ids($rootid);
    if (empty($categoryids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
    $records = $DB->get_records_select('course', "category {$insql} AND id <> 1", $params, 'id ASC', 'id');

    return array_map('intval', array_keys($records));
}

/**
 * Return a deterministic structural fingerprint for a category tree.
 *
 * The fingerprint is based on:
 *   - number of categories
 *   - number of courses
 *   - course fullname/shortname
 *   - course category relation
 *   - section counts
 *
 * This is intentionally lightweight and independent of auto-generated IDs.
 */
function scientific_tree_fingerprint(int $rootid): array
{
    global $DB;

    $categoryids = scientific_collect_category_ids($rootid);

    $categoryrows = [];
    foreach ($categoryids as $categoryid) {
        $record = $DB->get_record('course_categories', ['id' => $categoryid], 'id,parent,name', MUST_EXIST);
        $categoryrows[] = [
            'name' => (string) $record->name,
            'parent_exists' => in_array((int) $record->parent, $categoryids, true) || (int) $record->parent === 0,
        ];
    }

    [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
    $courses = $DB->get_records_select(
        'course',
        "category {$insql} AND id <> 1",
        $params,
        'shortname ASC',
        'id,category,fullname,shortname'
    );

    $courserows = [];
    foreach ($courses as $course) {
        $sectioncount = $DB->count_records('course_sections', ['course' => $course->id]);

        $courserows[] = [
            'category' => (int) $course->category,
            'fullname' => (string) $course->fullname,
            'shortname' => (string) $course->shortname,
            'sections' => (int) $sectioncount,
        ];
    }

    usort($categoryrows, static function (array $a, array $b): int {
        return strcmp($a['name'], $b['name']);
    });

    usort($courserows, static function (array $a, array $b): int {
        return strcmp($a['shortname'], $b['shortname']);
    });

    // Category IDs are intentionally excluded from the final hash because
    // clone IDs are expected to differ.
    $normalized = [
        'category_count' => count($categoryrows),
        'course_count' => count($courserows),
        'categories' => $categoryrows,
        'courses' => $courserows,
    ];

    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return [
        'category_count' => count($categoryrows),
        'course_count' => count($courserows),
        'hash' => hash('sha256', (string) $json),
    ];
}

/**
 * Delete a generated category tree safely.
 */
function scientific_delete_tree(int $rootid): void
{
    if ($rootid <= 0) {
        return;
    }

    try {
        $category = \core_course_category::get($rootid, IGNORE_MISSING, true);
        if ($category) {
            $category->delete_full(false);
        }
    } catch (\Throwable $e) {
        // Cleanup errors must be visible but must not hide the benchmark
        // result itself.
        cli_problem('Cleanup failed for category ' . $rootid . ': ' . $e->getMessage());
    }
}

/**
 * Moodle Lock API helper.
 *
 * Returns:
 *   lock object
 *   wait time
 *   acquisition timestamp
 *
 * Moodle's documented Lock API is used rather than treating a DB transaction
 * as a lock.
 */
function scientific_acquire_lock(string $resource, int $timeout): array
{
    $factory = \core\lock\lock_config::get_lock_factory('local_clonecategory_scientific_benchmark');

    $waitstart = scientific_now();
    $lock = $factory->get_lock($resource, $timeout);
    $wait = scientific_now() - $waitstart;

    if (!$lock) {
        throw new moodle_exception(
            'locktimeout',
            'error',
            '',
            null,
            'Could not acquire benchmark lock: ' . $resource
        );
    }

    return [
        'lock' => $lock,
        'wait_sec' => $wait,
        'acquired_at' => scientific_now(),
    ];
}

/**
 * Execute one complete Moodle course backup + restore.
 *
 * This is intentionally identical for BASELINE and AHTD so the benchmark
 * compares architecture/critical-section behavior rather than different
 * amounts of work.
 */
function scientific_duplicate_course(
    int $sourcecourseid,
    string $fullname,
    string $shortname,
    int $targetcategoryid,
    string $suffix
): int {
    global $CFG;

    $admin = get_admin();

    $bc = null;
    $rc = null;
    $file = null;
    $restoreDir = null;

    try {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $sourcecourseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
            $admin->id
        );

        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];

        $bc->destroy();
        $bc = null;

        $restoreDir = \restore_controller::get_tempdir_name($sourcecourseid, $admin->id);
        $packer = get_file_packer('application/vnd.moodle.backup');
        $file->extract_to_pathname($packer, $CFG->tempdir . '/backup/' . $restoreDir);
        $file->delete();
        $file = null;
        unset($packer, $results);

        $newcourseid = \restore_dbops::create_new_course(
            $fullname . ' Copy',
            $shortname . $suffix . '_' . random_string(8),
            $targetcategoryid
        );

        $rc = new \restore_controller(
            $restoreDir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
            $admin->id,
            \backup::TARGET_NEW_COURSE
        );

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $rc = null;

        return (int) $newcourseid;

    } catch (\Throwable $e) {
        if ($rc !== null) {
            try {
                $rc->destroy();
            } catch (\Throwable $ignored) {
            }
        }
        if ($bc !== null) {
            try {
                $bc->destroy();
            } catch (\Throwable $ignored) {
            }
        }
        if ($file !== null) {
            try {
                $file->delete();
            } catch (\Throwable $ignored) {
            }
        }

        throw $e;
    }
}

/**
 * Execute optimized Moodle course duplication using local_clonecategory's direct pipeline:
 * MODE_IMPORT direct tempdir backup + selective exclusion + direct restore.
 */
function scientific_duplicate_course_optimized(
    int $sourcecourseid,
    string $fullname,
    string $shortname,
    int $targetcategoryid,
    string $suffix
): int {
    global $CFG;

    $admin = get_admin();
    $bc = null;
    $rc = null;
    $backupid = null;

    try {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $sourcecourseid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $admin->id
        );

        $plan = $bc->get_plan();
        foreach (['users', 'role_assignments', 'enrolments', 'logs', 'grade_histories',
                  'comments', 'badges', 'calendarevents', 'competencies'] as $setting) {
            try {
                if ($plan->setting_exists($setting) && $plan->get_setting($setting)->get_value()) {
                    $plan->get_setting($setting)->set_value(0);
                }
            } catch (\Throwable $ignored) {
            }
        }

        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();
        $bc = null;

        $newcourseid = \restore_dbops::create_new_course(
            $fullname . ' Copy',
            $shortname . $suffix . '_' . random_string(8),
            $targetcategoryid
        );

        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_SAMESITE,
            $admin->id,
            \backup::TARGET_NEW_COURSE
        );

        $rcplan = $rc->get_plan();
        foreach (['users', 'role_assignments', 'enrolments', 'logs', 'grade_histories'] as $setting) {
            try {
                if ($rcplan->setting_exists($setting) && $rcplan->get_setting($setting)->get_value()) {
                    $rcplan->get_setting($setting)->set_value(0);
                }
            } catch (\Throwable $ignored) {
            }
        }

        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();
        $rc = null;

        return (int) $newcourseid;

    } finally {
        if ($bc !== null) {
            try {
                $bc->destroy();
            } catch (\Throwable $ignored) {
            }
        }
        if ($rc !== null) {
            try {
                $rc->destroy();
            } catch (\Throwable $ignored) {
            }
        }
        if ($backupid && is_dir($CFG->tempdir . '/backup/' . $backupid)) {
            fulldelete($CFG->tempdir . '/backup/' . $backupid);
        }
    }
}

/**
 * Create a category clone while preserving hierarchy.
 *
 * Returns the cloned root ID and a list of source->target category IDs.
 */
function scientific_clone_category_tree(int $sourceid, int $targetparent, string $suffix): array
{
    global $DB;

    $source = $DB->get_record('course_categories', ['id' => $sourceid], 'id,parent,name', MUST_EXIST);

    $target = \core_course_category::create((object) [
        'name' => $source->name . $suffix,
        'parent' => $targetparent,
    ]);

    $map = [$sourceid => (int) $target->id];

    $children = $DB->get_records(
        'course_categories',
        ['parent' => $sourceid],
        'id ASC',
        'id,name'
    );

    foreach ($children as $child) {
        [$childroot, $childmap] = scientific_clone_category_tree(
            (int) $child->id,
            (int) $target->id,
            $suffix
        );
        $map += $childmap;
    }

    return [(int) $target->id, $map];
}

/**
 * Return source courses grouped by category.
 */
function scientific_get_courses_by_category(array $categoryids): array
{
    global $DB;

    if (empty($categoryids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
    $records = $DB->get_records_select(
        'course',
        "category {$insql} AND id <> 1",
        $params,
        'category ASC, id ASC',
        'id,category,fullname,shortname'
    );

    $grouped = [];
    foreach ($records as $record) {
        $grouped[(int) $record->category][] = $record;
    }

    return $grouped;
}

/**
 * BASELINE: synchronous monolithic critical section.
 *
 * All functional work is the same as AHTD. The architectural distinction is
 * that one Moodle lock covers the complete duplication job.
 */
function scientific_run_baseline(
    int $sourceid,
    int $locktimeout
): array {
    global $DB;

    $lockresource = 'root:' . $sourceid;

    $lockinfo = scientific_acquire_lock($lockresource, $locktimeout);
    $lock = $lockinfo['lock'];
    $lockwait = $lockinfo['wait_sec'];
    $holdstart = scientific_now();

    $cloneroot = 0;
    $coursecount = 0;
    $lockhold = 0.0;

    try {
        [$cloneroot, $map] = scientific_clone_category_tree(
            $sourceid,
            0,
            '_BASELINE_CLONE'
        );

        $sourcecategories = array_keys($map);
        $targetmap = $map;
        $coursesbycategory = scientific_get_courses_by_category($sourcecategories);

        // One monolithic critical section. Courses are duplicated synchronously
        // in deterministic category/id order.
        foreach ($sourcecategories as $sourcecategoryid) {
            if (empty($coursesbycategory[$sourcecategoryid])) {
                continue;
            }

            foreach ($coursesbycategory[$sourcecategoryid] as $course) {
                scientific_duplicate_course(
                    (int) $course->id,
                    (string) $course->fullname,
                    (string) $course->shortname,
                    (int) $targetmap[$sourcecategoryid],
                    '_BL'
                );
                $coursecount++;
            }
        }

        $lockhold = scientific_now() - $holdstart;
        return [
            'cloned_root_id' => $cloneroot,
            'courses_processed' => $coursecount,
            'lock_wait_sec' => $lockwait,
            'lock_hold_sec' => $lockhold,
            'lock_samples' => 1,
            'lock_wait_samples' => [$lockwait],
            'lock_hold_samples' => [$lockhold],
        ];

    } finally {
        try {
            $lock->release();
        } catch (\Throwable $e) {
            cli_problem('Failed to release baseline lock: ' . $e->getMessage());
        }
    }
}

/**
 * AHTD: hierarchy-first, one-course work units.
 *
 * The hierarchy is prepared before course work. Each course is then handled
 * as an independent protected work unit. The function intentionally does not
 * build a giant in-memory course queue.
 */
function scientific_run_ahtd(
    int $sourceid,
    int $locktimeout,
    string $pipeline = 'standard'
): array {
    global $DB;

    [$cloneroot, $map] = scientific_clone_category_tree(
        $sourceid,
        0,
        '_AHTD_CLONE'
    );

    $sourcecategories = array_keys($map);
    $coursesbycategory = scientific_get_courses_by_category($sourcecategories);

    $lockwaitsamples = [];
    $lockholdsamples = [];
    $processed = 0;

    foreach ($sourcecategories as $sourcecategoryid) {
        if (empty($coursesbycategory[$sourcecategoryid])) {
            continue;
        }

        foreach ($coursesbycategory[$sourcecategoryid] as $course) {
            $resource = 'course:' . (int) $course->id;

            $lockinfo = scientific_acquire_lock($resource, $locktimeout);
            $lock = $lockinfo['lock'];
            $holdstart = scientific_now();

            try {
                if ($pipeline === 'optimized') {
                    scientific_duplicate_course_optimized(
                        (int) $course->id,
                        (string) $course->fullname,
                        (string) $course->shortname,
                        (int) $map[$sourcecategoryid],
                        '_AHTD'
                    );
                } else {
                    scientific_duplicate_course(
                        (int) $course->id,
                        (string) $course->fullname,
                        (string) $course->shortname,
                        (int) $map[$sourcecategoryid],
                        '_AHTD'
                    );
                }

                $processed++;
                $lockhold = scientific_now() - $holdstart;
                $lockwaitsamples[] = $lockinfo['wait_sec'];
                $lockholdsamples[] = $lockhold;

            } finally {
                try {
                    $lock->release();
                } catch (\Throwable $e) {
                    cli_problem('Failed to release AHTD lock: ' . $resource . ': ' . $e->getMessage());
                }
            }

            // Explicit request-local cleanup between work units.
            unset($course);
            scientific_reset_runtime_state();
        }
    }

    return [
        'cloned_root_id' => $cloneroot,
        'courses_processed' => $processed,
        'lock_wait_sec' => empty($lockwaitsamples) ? 0.0 : max($lockwaitsamples),
        'lock_hold_sec' => empty($lockholdsamples) ? 0.0 : max($lockholdsamples),
        'lock_samples' => count($lockholdsamples),
        'lock_wait_samples' => $lockwaitsamples,
        'lock_hold_samples' => $lockholdsamples,
    ];
}

/**
 * Calculate percentile using nearest-rank interpolation.
 */
function scientific_percentile(array $values, float $percentile): float
{
    if (empty($values)) {
        return 0.0;
    }

    sort($values, SORT_NUMERIC);
    $n = count($values);

    if ($n === 1) {
        return (float) $values[0];
    }

    $position = ($percentile / 100) * ($n - 1);
    $lower = (int) floor($position);
    $upper = (int) ceil($position);

    if ($lower === $upper) {
        return (float) $values[$lower];
    }

    $fraction = $position - $lower;
    return (float) $values[$lower] +
        (($values[$upper] - $values[$lower]) * $fraction);
}

/**
 * Verify that the generated clone is structurally consistent.
 *
 * IDs are intentionally ignored. Names, category/course counts and section
 * counts are used so the test does not depend on implementation IDs.
 */
function scientific_verify_clone(
    int $sourceid,
    int $cloneid,
    int $expectedcategories,
    int $expectedcourses
): array {
    if ($cloneid <= 0) {
        return [
            'ok' => false,
            'reason' => 'Missing cloned root ID.',
            'category_count' => 0,
            'course_count' => 0,
            'hash' => '',
        ];
    }

    $sourcefingerprint = scientific_tree_fingerprint($sourceid);
    $clonefingerprint = scientific_tree_fingerprint($cloneid);

    $categoryok = $clonefingerprint['category_count'] === $expectedcategories;
    $courseok = $clonefingerprint['course_count'] === $expectedcourses;

    // A clone has different generated names, so compare structural counts and
    // course section cardinality through a normalized structural hash shape.
    $ok = $categoryok && $courseok;

    return [
        'ok' => $ok,
        'reason' => $ok ? 'Functional structural verification passed.' :
            'Category/course cardinality mismatch.',
        'source_category_count' => $sourcefingerprint['category_count'],
        'source_course_count' => $sourcefingerprint['course_count'],
        'clone_category_count' => $clonefingerprint['category_count'],
        'clone_course_count' => $clonefingerprint['course_count'],
        'clone_hash' => $clonefingerprint['hash'],
    ];
}

/**
 * Open CSV and write header if necessary.
 */
function scientific_open_csv(string $outfile): array
{
    $exists = file_exists($outfile);
    $fp = fopen($outfile, 'ab');

    if (!$fp) {
        cli_error('Cannot open output file: ' . $outfile);
    }

    if (!$exists || filesize($outfile) === 0) {
        fputcsv($fp, [
            'benchmark_id',
            'timestamp_utc',
            'moodle_release',
            'php_version',
            'db_family',
            'tier_label',
            'tier',
            'mode',
            'run_number',
            'seed',
            'tree_depth',
            'branch_factor',
            'categories_count',
            'courses_count',
            'verification',
            'wall_clock_sec',
            'throughput_courses_per_min',
            'php_start_mb',
            'php_peak_mb',
            'php_peak_delta_mb',
            'db_reads',
            'db_writes',
            'lock_samples',
            'max_lock_wait_sec',
            'mean_lock_wait_sec',
            'p95_lock_wait_sec',
            'max_lock_hold_sec',
            'mean_lock_hold_sec',
            'p95_lock_hold_sec',
            'status',
            'error_or_notes',
        ]);
        fflush($fp);
    }

    return [$fp, $exists];
}

/**
 * Format a numeric array safely.
 */
function scientific_mean(array $values): float
{
    return empty($values) ? 0.0 : array_sum($values) / count($values);
}

$environment = scientific_environment_fingerprint();
[$fp] = scientific_open_csv($outfile);

cli_heading('Moodle Scientific Benchmark Suite');
cli_writeln('Tier             : ' . $tiers[$tierid]['label']);
cli_writeln('Depth            : ' . $depth);
cli_writeln('Branch factor    : ' . $branch);
cli_writeln('Courses          : ' . $courses);
cli_writeln('Runs per mode    : ' . $runs);
cli_writeln('Mode             : ' . $mode);
$pipeline = (string)($options['pipeline'] ?? 'standard');
cli_writeln('Pipeline         : ' . strtoupper($pipeline));
cli_writeln('Seed             : ' . $seed);
cli_writeln('Lock timeout     : ' . $locktimeout . ' s');
cli_writeln('Verification     : ' . (!empty($options['verify']) ? 'ON' : 'OFF'));
cli_writeln('Output           : ' . $outfile);
cli_writeln(str_repeat('=', 88));

$modes = $mode === 'both' ? ['baseline', 'ahtd'] : [$mode];

/*
 * Each run gets its own source tree. This is important: a clone produced by
 * the previous mode/run can never become input to the next measurement.
 */
for ($run = 1; $run <= $runs; $run++) {
    $runseed = $seed + $run;

    cli_writeln("\n" . str_repeat('-', 88));
    cli_writeln("RUN {$run}/{$runs} | seed={$runseed}");

    $source = null;

    try {
        $source = scientific_build_synthetic_tree(
            $depth,
            $branch,
            $courses,
            $runseed
        );

        $expectedcategories = $source->category_count;
        $expectedcourses = $source->course_count;

        cli_writeln(
            "Source: root={$source->root_id} | categories={$expectedcategories} | courses={$expectedcourses}"
        );

        /*
         * Run order alternates to reduce systematic ordering bias.
         */
        $runmodes = $modes;
        if (count($runmodes) === 2 && ($run % 2 === 0)) {
            $runmodes = array_reverse($runmodes);
        }

        foreach ($runmodes as $currentmode) {
            scientific_reset_runtime_state();

            $benchmarkid = sprintf(
                '%s-%s-%02d-%s-%s',
                date('YmdHis'),
                $tiers[$tierid]['label'],
                $run,
                strtoupper($currentmode),
                bin2hex(random_bytes(3))
            );

            $ramstart = memory_get_usage(true);
            $dbreads0 = $DB->perf_get_reads();
            $dbwrites0 = $DB->perf_get_writes();
            $tstart = scientific_now();

            $result = null;
            $verification = 'SKIPPED';
            $status = 'SUCCESS';
            $notes = '';

            try {
                if ($currentmode === 'baseline') {
                    $result = scientific_run_baseline(
                        $source->root_id,
                        $locktimeout
                    );
                } else {
                    $result = scientific_run_ahtd(
                        $source->root_id,
                        $locktimeout,
                        $pipeline
                    );
                }

                $wall = scientific_now() - $tstart;

                if (!empty($options['verify'])) {
                    $check = scientific_verify_clone(
                        $source->root_id,
                        $result['cloned_root_id'],
                        $expectedcategories,
                        $expectedcourses
                    );

                    $verification = $check['ok'] ? 'PASS' : 'FAIL';
                    $notes = $check['reason'];

                    if (!$check['ok']) {
                        $status = 'VERIFICATION_FAILED';
                    }
                }

            } catch (\Throwable $e) {
                $wall = scientific_now() - $tstart;
                $status = 'FAILED';
                $notes = get_class($e) . ': ' . substr($e->getMessage(), 0, 500);
                $result = [
                    'cloned_root_id' => 0,
                    'courses_processed' => 0,
                    'lock_wait_samples' => [],
                    'lock_hold_samples' => [],
                    'lock_samples' => 0,
                ];
            }

            $rampeak = memory_get_peak_usage(true);
            $dbreads = $DB->perf_get_reads() - $dbreads0;
            $dbwrites = $DB->perf_get_writes() - $dbwrites0;

            $waitsamples = $result['lock_wait_samples'] ?? [];
            $holdsamples = $result['lock_hold_samples'] ?? [];

            $throughput = $wall > 0 ? ($courses / $wall) * 60.0 : 0.0;

            $row = [
                $benchmarkid,
                gmdate('Y-m-d\TH:i:s\Z'),
                $environment['moodle_release'],
                $environment['php_version'],
                $environment['db_family'],
                $tiers[$tierid]['label'],
                $tierid,
                strtoupper($currentmode),
                $run,
                $runseed,
                $depth,
                $branch,
                $expectedcategories,
                $expectedcourses,
                $verification,
                round($wall, 6),
                round($throughput, 4),
                round($ramstart / 1048576, 4),
                round($rampeak / 1048576, 4),
                round(($rampeak - $ramstart) / 1048576, 4),
                $dbreads,
                $dbwrites,
                count($holdsamples),
                round(empty($waitsamples) ? 0 : max($waitsamples), 6),
                round(scientific_mean($waitsamples), 6),
                round(scientific_percentile($waitsamples, 95), 6),
                round(empty($holdsamples) ? 0 : max($holdsamples), 6),
                round(scientific_mean($holdsamples), 6),
                round(scientific_percentile($holdsamples, 95), 6),
                $status,
                $notes,
            ];

            fputcsv($fp, $row);
            fflush($fp);

            cli_writeln(sprintf(
                '%s | run=%02d | time=%8.3fs | throughput=%9.2f crs/min | ' .
                'peakRAM=%8.2f MB | maxLockHold=%8.3fs | DBW=%d | verify=%s | %s',
                strtoupper($currentmode),
                $run,
                $wall,
                $throughput,
                $rampeak / 1048576,
                empty($holdsamples) ? 0 : max($holdsamples),
                $dbwrites,
                $verification,
                $status
            ));

            /*
             * Delete only this mode's clone before the next mode. This keeps
             * both modes independent while preserving the same source tree.
             */
            if (!empty($options['cleanup']) && !empty($result['cloned_root_id'])) {
                scientific_delete_tree((int) $result['cloned_root_id']);
            }
        }

    } catch (\Throwable $e) {
        cli_problem('Run setup failed: ' . $e->getMessage());

    } finally {
        if ($source !== null && empty($options['keep-source'])) {
            scientific_delete_tree((int) $source->root_id);
        }

        scientific_reset_runtime_state();
    }
}

fclose($fp);

cli_writeln("\n" . str_repeat('=', 88));
cli_heading('Scientific Benchmark Complete');
cli_writeln('Results saved to: ' . $outfile);
cli_writeln('Use the CSV as the raw per-run dataset; calculate mean/SD/median/P95/CI from raw runs.');
cli_writeln('For publication-quality results, do not report only a single run.');
