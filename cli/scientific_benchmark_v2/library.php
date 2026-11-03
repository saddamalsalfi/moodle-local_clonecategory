<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Independent metrics, verification and guarded cleanup for the benchmark.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_clonecategory\benchmark_v2;

/** @var string Identity of the preserved production-path protocol. */
const REVISION = 'production-path-v2-20261028-r4';
/** @var string[] Actual production methods compared by the experiment. */
const METHODS = ['native', 'clonecategory'];
/**
 * Parse explicit CLI options and reject removed synthetic benchmark flags.
 *
 * @param array $argv CLI argument vector.
 * @return array
 */
function options(array $argv): array {
    $o = [
        'help' => false,
        'execute' => false,
        'retain' => false,
        'source-category' => 0,
        'runs' => 6,
        'warmups' => 1,
        'seed' => 20261028,
        'timeout' => 7200,
        'output-dir' => '',
        'phase' => '',
        'experiment-dir' => '',
        'run' => '',
        'cleanup-run' => '',
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h') {
            $o['help'] = true;
            continue;
        }
        if (!str_starts_with($arg, '--')) {
            throw new \InvalidArgumentException('Use --option=value; unexpected argument: ' . $arg);
        }
        [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
        if (!array_key_exists($key, $o)) {
            throw new \InvalidArgumentException('Removed/unknown option --' . $key
                . '. Use --help; simulated BASELINE/AHTD/pipeline options are not product paths.');
        }
        if (is_bool($o[$key])) {
            if ($value !== null && !in_array($value, ['0', '1'], true)) {
                throw new \InvalidArgumentException('--' . $key . ' accepts a flag or =0|1.');
            }
            $o[$key] = $value === null || $value === '1';
        } else {
            if ($value === null || $value === '') {
                throw new \InvalidArgumentException('--' . $key . ' requires a value.');
            }
            $o[$key] = $value;
        }
    }
    foreach (
        [
        'source-category',
        'runs',
        'warmups',
        'seed',
        'timeout',
        ] as $k
    ) {
        if (!preg_match('/^\d+$/D', (string) $o[$k])) {
            throw new \InvalidArgumentException('Nonnegative integer required: ' . $k);
        }
        $o[$k] = (int) $o[$k];
    }
    if (
        $o['runs'] < 1 || $o['runs'] > 1000 || $o['warmups'] > 100 || $o['seed'] > 2147483647
        || $o['timeout'] < 10
        || $o['timeout'] > 86400
    ) {
        throw new \InvalidArgumentException('runs=1..1000; warmups=0..100; timeout=10..86400.');
    }
    return $o;
}

/**
 * Describe the production-path protocol and supported CLI options.
 *
 * @param string $script Absolute benchmark entry-point path.
 * @return string
 */
function help_text(string $script): string {
    return "Native Moodle / Clone Category production-path benchmark (" . REVISION . ")\n\n"
        . "PLAN (default, no cloning):\n  php {$script} --source-category=2\n"
        . "MEASURE:\n  php {$script} --source-category=32 --execute --runs=6 --warmups=1\n"
        . "FULL-SCALE SINGLE PAIR:\n  php {$script} --source-category=2 --execute --runs=1 --warmups=0 --retain\n\n"
        . "Options: --source-category=ID --runs=6 --warmups=1 --seed=20261028\n"
        . "         --output-dir=/private/new/path --timeout=7200 --retain\n"
        . "Cleanup a retained owned run: --execute --cleanup-run=/experiment/runs/RUN\n\n"
        . "Actual core asynchronous_copy_task vs actual clone_category_task.\n"
        . "One isolated PHP copy-worker process per method; sequential owned tasks.\n"
        . "Readiness includes own grading/calendar tasks; verification/cleanup are separate.\n"
        . "Warm-ups are recorded and excluded only from summaries. Paired order is balanced.\n"
        . ('No synthetic lock simulation, forced identical engine, fake parallelism or '
        . 'presumed winner.
')
        . ('Production optional backup settings differ; scope warnings prevent summary '
        . 'eligibility.
')
        . "No user enrolments requested. Output includes CSV, raw JSON/logs and script hashes.\n"
        . "Default cleanup is a common ownership-checked CORE harness, not plugin rollback.\n"
        . ('One run is a functional observation, not statistical evidence of universal '
        . 'superiority.
');
}

/**
 * Read one experiment record, rejecting invalid JSON.
 *
 * @param string $path Filesystem path to the evidence record.
 * @return array
 */
function read_json(string $path): array {
    $data = file_get_contents($path);
    if ($data === false) {
        throw new \RuntimeException('Read failed: ' . $path);
    }
    return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Atomically replace a private experiment record.
 *
 * @param string $path Filesystem path to the evidence record.
 * @param array $data Structured evidence values.
 * @return void
 */
function write_json(string $path, array $data): void {
    $tmp = $path . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new \RuntimeException('Write failed: ' . $path);
    }
    chmod($tmp, 0600);
    if (!rename($tmp, $path)) {
        throw new \RuntimeException('Atomic replace failed: ' . $path);
    }
}

/**
 * Sort associative keys recursively without changing list order.
 *
 * @param mixed $x Value to canonicalise or hash.
 * @return mixed
 */
function canonical($x) {
    if (is_object($x)) {
        $x = (array) $x;
    }
    if (is_array($x)) {
        if (!array_is_list($x)) {
            ksort($x, SORT_STRING);
        }
        foreach ($x as $k => $v) {
            $x[$k] = canonical($v);
        }
    }
    return $x;
}

/**
 * Hash canonical JSON for stable evidence comparison.
 *
 * @param mixed $x Value to canonicalise or hash.
 * @return string
 */
function hash_data($x): string {
    return hash('sha256', json_encode(canonical($x), JSON_THROW_ON_ERROR));
}

/**
 * Append a quoted CSV record while preserving null measurements.
 *
 * @param string $path Filesystem path to the evidence record.
 * @param array $row CSV record indexed by column name.
 * @param array|null $headers Ordered CSV columns, or null to infer them.
 * @return void
 */
function append_csv(string $path, array $row, ?array $headers = null): void {
    $headers = $headers ?? array_keys($row);
    $new = !is_file($path);
    $f = fopen($path, 'ab');
    if (!$f || !flock($f, LOCK_EX)) {
        throw new \RuntimeException('CSV open/lock failed: ' . $path);
    }
    if ($new) {
        fputcsv($f, $headers, ',', '"', '', "\r\n");
    }
    $values = [];
    foreach ($headers as $key) {
        $v = $row[$key] ?? null;
        $values[] = is_array($v) || is_object($v) ? json_encode($v, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : (
            is_bool($v) ? (int) $v : $v);
    }
    fputcsv($f, $values, ',', '"', '', "\r\n");
    fflush($f);
    flock($f, LOCK_UN);
    fclose($f);
}

/**
 * Require private output outside served code and restrict permissions.
 *
 * @param string $path Filesystem path to the evidence record.
 * @return void
 */
function private_directory(string $path): void {
    global $CFG;
    if (str_contains($path, "\x00")) {
        throw new \RuntimeException('Invalid output path.');
    }
    if (!is_dir($path) && !mkdir($path, 0700, true)) {
        throw new \RuntimeException('Cannot create output directory.');
    }
    $real = realpath($path);
    $web = realpath($CFG->dirroot);
    $data = realpath($CFG->dataroot);
    if (
        $real === false
        || $web && str_starts_with($real . '/', $web . '/')
        && !($data && str_starts_with($real . '/', $data . '/'))
    ) {
        throw new \RuntimeException('Use a private output directory outside served Moodle code.');
    }
    chmod($real, 0700);
}

/**
 * Build seeded counterbalanced paired blocks, preserving warmup labels.
 *
 * @param int $runs Number of measured paired blocks.
 * @param int $warmups Number of labelled warmup paired blocks.
 * @param int $seed Deterministic ordering or bootstrap seed.
 * @return array
 */
function schedule(int $runs, int $warmups, int $seed): array {
    mt_srand($seed, MT_RAND_MT19937);
    $out = [];
    foreach (['warmup' => $warmups, 'measured' => $runs] as $type => $n) {
        $first = [];
        for ($i = 0; $i < $n; $i++) {
            $first[] = $i % 2 === 0 ? 'native' : 'clonecategory';
        }
        for ($i = count($first) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$first[$i], $first[$j]] = [$first[$j], $first[$i]];
        }
        foreach ($first as $block => $a) {
            $order = [$a, $a === 'native' ? 'clonecategory' : 'native'];
            foreach ($order as $method) {
                $out[] = [
                    'token' => $type . '_' . $block . '_' . $method,
                    'block' => $block,
                    'warmup' => $type === 'warmup',
                    'method' => $method,
                    'order' => $order,
                ];
            }
        }
    }
    return $out;
}

/**
 * Interpolate a quantile of a numeric sample.
 *
 * @param array $a Numeric sample.
 * @param float $p Requested quantile between zero and one.
 * @return float|null
 */
function quantile(array $a, float $p): ?float {
    if (!$a) {
        return null;
    }
    sort($a, SORT_NUMERIC);
    $t = (count($a) - 1) * $p;
    $i = (int) floor($t);
    return $a[$i] + ($a[min($i + 1, count($a) - 1)] - $a[$i]) * ($t - $i);
}

/**
 * Describe the sample without inventing variance for a single observation.
 *
 * @param array $a Numeric sample.
 * @return array
 */
function describe(array $a): array {
    if (!$a) {
        return [
            'n' => 0,
            'mean' => null,
            'median' => null,
            'sd' => null,
            'min' => null,
            'max' => null,
            'q1' => null,
            'q3' => null,
        ];
    }
    $n = count($a);
    $mean = array_sum($a) / $n;
    $ss = 0;
    foreach ($a as $v) {
        $ss += ($v - $mean) ** 2;
    }
    return [
        'n' => $n,
        'mean' => $mean,
        'median' => quantile($a, 0.5),
        'sd' => $n > 1 ? sqrt($ss / ($n - 1)) : null,
        'min' => min($a),
        'max' => max($a),
        'q1' => quantile($a, 0.25),
        'q3' => quantile($a, 0.75),
    ];
}

/**
 * Compute paired ratios, differences and exploratory bootstrap intervals.
 *
 * @param array $native Native observations indexed by paired block.
 * @param array $clone Plugin observations indexed by paired block.
 * @param int $seed Deterministic ordering or bootstrap seed.
 * @return array
 */
function paired_ratio(array $native, array $clone, int $seed): array {
    $logs = [];
    $diffs = [];
    $nonpositive = 0;
    foreach ($native as $block => $v) {
        if (isset($clone[$block])) {
            $diffs[] = $v - $clone[$block];
            if ($v > 0 && $clone[$block] > 0) {
                $logs[] = log($v / $clone[$block]);
            } else {
                $nonpositive++;
            }
        }
    }
    $n = count($diffs);
    $ratio = $n && !$nonpositive ? exp(array_sum($logs) / $n) : null;
    $lo = null;
    $hi = null;
    $dlo = null;
    $dhi = null;
    if ($n >= 2) {
        mt_srand($seed, MT_RAND_MT19937);
        $boot = [];
        $db = [];
        for ($i = 0; $i < 10000; $i++) {
            $sum = 0;
            $ds = 0;
            for ($j = 0; $j < $n; $j++) {
                $idx = mt_rand(0, $n - 1);
                $ds += $diffs[$idx];
                if (!$nonpositive) {
                    $sum += $logs[$idx];
                }
            }
            $db[] = $ds / $n;
            if (!$nonpositive) {
                $boot[] = exp($sum / $n);
            }
        }
        $dlo = quantile($db, 0.025);
        $dhi = quantile($db, 0.975);
        if (!$nonpositive) {
            $lo = quantile($boot, 0.025);
            $hi = quantile($boot, 0.975);
        }
    }
    return [
        'paired_blocks' => $n,
        'pairs_with_nonpositive_values' => $nonpositive,
        'native_over_clone_geometric_ratio' => $ratio,
        'exploratory_ratio_95_low' => $lo,
        'exploratory_ratio_95_high' => $hi,
        'mean_paired_difference_native_minus_clone' => $n ? array_sum($diffs) / $n : null,
        'exploratory_difference_95_low' => $dlo,
        'exploratory_difference_95_high' => $dhi,
        'ratio_status' => $nonpositive ? 'undefined_for_zero_or_negative_values_no_pairs_silently_dropped' : ($n
            ? 'defined' : 'no_pairs'),
        'interval_status' => $n < 2 ? 'not_estimable_from_one_or_zero_pairs'
            : 'within_session_percentile_bootstrap_not_cross_site_inference',
    ];
}

/**
 * Validate the source and required production interfaces before mutation.
 *
 * @param int $source Source category identifier or captured source evidence.
 * @return void
 */
function preflight(int $source): void {
    global $DB, $CFG;
    if ($source < 1 || !$DB->record_exists('course_categories', ['id' => $source])) {
        throw new \RuntimeException('A valid --source-category=ID is required.');
    }
    if (!function_exists('proc_open')) {
        throw new \RuntimeException('proc_open is required for isolated measurements; no in-process fallback.');
    }
    if (
        !class_exists('\copy_helper') || !method_exists('\local_clonecategory\manager', 'create_job')
        || !method_exists('\local_clonecategory\manager', 'fingerprint')
    ) {
        throw new \RuntimeException('Required production copy / ownership APIs are unavailable.');
    }
    if (CLI_MAINTENANCE || moodle_needs_upgrading() || !get_config('core', 'cron_enabled')) {
        throw new \RuntimeException('Maintenance, upgrade or disabled Cron prevents an actual task benchmark.');
    }
    foreach (['\core\task\asynchronous_copy_task', '\local_clonecategory\task\clone_category_task'] as $class) {
        if ($DB->record_exists('task_adhoc', ['classname' => $class])) {
            throw new \RuntimeException('Copy tasks already pending/running; isolate the experiment first.');
        }
    }
    if (\local_clonecategory\manager::has_active_job()) {
        throw new \RuntimeException('A Clone Category job is active.');
    }
}

/**
 * Capture versions, engine hashes, host load and queue inventory.
 *
 * @param string $script Absolute benchmark entry-point path.
 * @return array
 */
function environment(string $script): array {
    global $CFG, $DB;
    $hashes = [];
    foreach (
        [
        'backup/util/helper/copy_helper.class.php',
        'lib/classes/task/asynchronous_copy_task.php',
        'local/clonecategory/classes/manager.php',
        'local/clonecategory/classes/task/clone_category_task.php',
        ] as $f
    ) {
        $hashes[$f] = hash_file('sha256', $CFG->dirroot . '/' . $f);
    }
    return [
        'utc' => gmdate('c'),
        'moodle_release' => $CFG->release,
        'moodle_version' => $CFG->version,
        'php' => PHP_VERSION,
        'php_ini' => [
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache.enable_cli' => ini_get('opcache.enable_cli'),
        ],
        'db_family' => $DB->get_dbfamily(),
        'db_server' => $DB->get_server_info(),
        'uname' => php_uname(),
        'load' => sys_getloadavg(),
        'plugin_installed_version' => get_config('local_clonecategory', 'version'),
        'engine_hashes' => $hashes,
        'benchmark_script_sha256' => hash_file('sha256', $script),
        'library_sha256' => hash_file('sha256', __FILE__),
        'categories' => $DB->count_records('course_categories'),
        'courses_including_frontpage' => $DB->count_records('course'),
        'queued_tasks' => array_values($DB->get_records(
            'task_adhoc',
            null,
            'id',
            'id,classname,nextruntime,faildelay,timestarted'
        )),
    ];
}

/**
 * Read the source category subtree in parent-first order.
 *
 * @param int $root Source category root identifier.
 * @return array
 */
function tree(int $root): array {
    global $DB;
    $all = $DB->get_records('course_categories', null, 'depth,id');
    if (!isset($all[$root])) {
        throw new \RuntimeException('Source category missing.');
    }
    $todo = [$root];
    $seen = [];
    $out = [];
    while ($todo) {
        $id = array_shift($todo);
        if (isset($seen[$id])) {
            throw new \RuntimeException('Category cycle.');
        }
        $seen[$id] = true;
        $out[$id] = (array) $all[$id];
        foreach ($all as $c) {
            if ((int) $c->parent === $id) {
                $todo[] = (int) $c->id;
            }
        }
    }
    return $out;
}

/**
 * Identify file areas covered by the design-content comparison.
 *
 * @param array $f Captured file metadata.
 * @return bool
 */
function design_file(array $f): bool {
    if (in_array($f['component'], ['backup', 'user'], true)) {
        return false;
    }
    if (
        str_starts_with($f['component'], 'assignsubmission_')
        || str_starts_with($f['component'], 'assignfeedback_')
    ) {
        return false;
    }
    if ($f['component'] === 'mod_forum' && in_array($f['filearea'], ['attachment', 'post'], true)) {
        return false;
    }
    return true;
}

/**
 * Read file metadata and streamed SHA256 for mapped contexts.
 *
 * @param int $context Moodle context identifier.
 * @param array $owners File-owner mapping for captured contexts.
 * @return array
 */
function file_rows(int $context, array $owners): array {
    global $DB;
    static $digests = [];
    $out = [];
    $fs = get_file_storage();
    $rows = $DB->get_records_select('files', 'contextid=:c AND filename<>:dot', ['c' => $context, 'dot' => '.'], 'id');
    foreach ($rows as $r) {
        $f = (array) $r;
        $hash = $f['contenthash'];
        if (!isset($digests[$hash])) {
            $sf = $fs->get_file_by_id($r->id);
            $stream = $sf->get_content_file_handle();
            if (!$stream) {
                throw new \RuntimeException('Cannot hash content ' . $r->id);
            }
            $h = hash_init('sha256');
            hash_update_stream($h, $stream);
            fclose($stream);
            $digests[$hash] = hash_final($h);
        }
        $f['sha256'] = $digests[$hash];
        $f['owner'] = $owners[$context];
        $f['primary_design_file'] = design_file($f);
        $out[] = $f;
    }
    return $out;
}

/**
 * Capture category, course, section, activity and file evidence.
 *
 * @param int $root Source category root identifier.
 * @return array
 */
function capture(int $root): array {
    global $DB;
    $cats = tree($root);
    $courses = [];
    $warn = [];
    $types = [];
    $files = [];
    $sectioncount = 0;
    $mods = 0;
    foreach ($cats as $id => &$c) {
        $ctx = \context_coursecat::instance($id);
        $c = [
            'record' => $c,
            'files' => file_rows($ctx->id, [$ctx->id => 'category']),
            'roles' => $DB->count_records('role_assignments', ['contextid' => $ctx->id]),
            'role_overrides' => $DB->count_records('role_capabilities', ['contextid' => $ctx->id]),
            'cohorts' => $DB->count_records('cohort', ['contextid' => $ctx->id]),
        ];
        if ($c['roles'] || $c['role_overrides'] || $c['cohorts']) {
            $warn[] = 'Category ' . $id . ' has administration data outside the display-tree oracle.';
        }
        foreach ($c['files'] as $f) {
            $files[] = $f;
        }
        foreach ($DB->get_records('course', ['category' => $id], 'id') as $course) {
            $cid = (int) $course->id;
            $ctx = \context_course::instance($cid);
            $cf = file_rows($ctx->id, [$ctx->id => 'course']);
            $sections = [];
            foreach ($DB->get_records('course_sections', ['course' => $cid], 'section') as $s) {
                $ml = [];
                $position = 0;
                $sectioncount++;
                foreach (array_filter(explode(',', $s->sequence)) as $cmid) {
                    $cm = $DB->get_record('course_modules', ['id' => (int) $cmid, 'course' => $cid], '*', MUST_EXIST);
                    $type = $DB->get_field('modules', 'name', ['id' => $cm->module], MUST_EXIST);
                    $inst = $DB->get_record($type, ['id' => $cm->instance], '*', MUST_EXIST);
                    $mc = \context_module::instance($cm->id);
                    $owner = 'section:' . $s->section . '/module:' . $position . '/' . $type;
                    $mf = file_rows($mc->id, [$mc->id => $owner]);
                    $cf = array_merge($cf, $mf);
                    $ml[] = [
                        'cm' => (array) $cm,
                        'type' => $type,
                        'instance' => (array) $inst,
                        'position' => $position++,
                    ];
                    $types[$type] = ($types[$type] ?? 0) + 1;
                    $mods++;
                    if (
                        !in_array($type, [
                        'resource',
                        'forum',
                        'page',
                        'folder',
                        'url',
                        'label',
                        ], true)
                    ) {
                        $warn[] = 'Activity type ' . $type . ' is outside the complete design oracle.';
                    }
                }
                $sections[] = ['record' => (array) $s, 'modules' => $ml];
            }
            $extra = [
                'grade_items' => $DB->count_records('grade_items', ['courseid' => $cid]),
                'grade_categories' => $DB->count_records('grade_categories', ['courseid' => $cid]),
                'blocks' => $DB->count_records('block_instances', ['parentcontextid' => $ctx->id]),
                'groups' => $DB->count_records('groups', ['courseid' => $cid]),
                'groupings' => $DB->count_records('groupings', ['courseid' => $cid]),
                'learner_enrolments' => (int) $DB->count_records_sql(
                    'SELECT COUNT(*) FROM {user_enrolments} u JOIN {enrol} e ON e.id=u.enrolid W'
                    . 'HERE e.courseid=:c',
                    ['c' => $cid]
                ),
                'learner_grade_rows' => (int) $DB->count_records_sql(
                    'SELECT COUNT(*) FROM {grade_grades} g JOIN {grade_items} i ON i.id=g.itemid'
                    . ' WHERE i.courseid=:c',
                    ['c' => $cid]
                ),
                'question_bank_entries' => (int) $DB->count_records_sql(
                    'SELECT COUNT(*) FROM {question_bank_entries} b JOIN {question_categories} q'
                    . ' ON q.id=b.questioncategoryid JOIN {context} x ON x.id=q.contextid WHERE x.'
                    . 'id=:c OR x.path LIKE :p',
                    ['c' => $ctx->id, 'p' => $ctx->path . '/%']
                ),
                'forum_posts' => (int) $DB->count_records_sql(
                    'SELECT COUNT(*) FROM {forum_posts} p JOIN {forum_discussions} d ON d.id=p.d'
                    . 'iscussion JOIN {forum} f ON f.id=d.forum WHERE f.course=:c',
                    ['c' => $cid]
                ),
            ];
            foreach (
                [
                'blocks',
                'groups',
                'groupings',
                'question_bank_entries',
                'forum_posts',
                ] as $k
            ) {
                if ($extra[$k]) {
                    $warn[] = 'Course ' . $cid . ' contains ' . $k . ' outside complete oracle coverage.';
                }
            }
            foreach ($cf as &$f) {
                if ($f['owner'] === 'course' && $f['component'] === 'course' && $f['filearea'] === 'section') {
                    foreach ($sections as $sec) {
                        if ((string) $sec['record']['id'] === (string) $f['itemid']) {
                            $f['owner'] = 'section-summary:' . $sec['record']['section'];
                        }
                    }
                }
            }
            unset($f);
            $courses[$cid] = [
                'record' => (array) $course,
                'sections' => $sections,
                'files' => $cf,
                'extra' => $extra,
            ];
            $files = array_merge($files, $cf);
        }
    }
    unset($c);
    $unique = [];
    $logical = 0;
    foreach ($files as $f) {
        $unique[$f['contenthash']] = (int) $f['filesize'];
        $logical += (int) $f['filesize'];
    }
    if (!$courses) {
        $warn[] = 'Source has zero courses; no course-copy advantage can be estimated.';
    }
    $depths = array_map(fn($x) => (int) $x['record']['depth'], $cats);
    return [
        'root' => $root,
        'captured_utc' => gmdate('c'),
        'categories' => $cats,
        'courses' => $courses,
        'scope_warnings' => array_values(array_unique($warn)),
        'totals' => [
            'categories' => count($cats),
            'courses' => count($courses),
            'sections_including_general' => $sectioncount,
            'modules' => $mods,
            'module_types' => $types,
            'file_references' => count($files),
            'logical_file_bytes' => $logical,
            'unique_contents' => count($unique),
            'unique_content_bytes' => array_sum($unique),
            'maximum_absolute_category_depth' => max($depths),
            'maximum_relative_category_depth' => max($depths) - (int) $cats[$root]['record']['depth'],
        ],
    ];
}

/**
 * Remove explicitly normalised system fields from a comparison record.
 *
 * @param array $r Captured record or resource-accounting values.
 * @param array $keys System fields to exclude explicitly.
 * @return array
 */
function omit(array $r, array $keys): array {
    foreach ($keys as $k) {
        unset($r[$k]);
    }
    return $r;
}

/**
 * Select file metadata fields covered by content verification.
 *
 * @param array $f Captured file metadata.
 * @return array
 */
function file_signature(array $f): array {
    return array_intersect_key($f, array_flip([
        'owner',
        'component',
        'filearea',
        'filepath',
        'filename',
        'filesize',
        'contenthash',
        'sha256',
        'author',
        'license',
        'mimetype',
    ]));
}

/**
 * Normalise expected identity changes without hiding design mutations.
 *
 * @param array $c Captured course evidence.
 * @return array
 */
function normalized_course(array $c): array {
    $sections = [];
    foreach ($c['sections'] as $s) {
        $ml = [];
        foreach ($s['modules'] as $m) {
            $ml[] = [
                'type' => $m['type'],
                'cm' => omit($m['cm'], [
                    'id',
                    'course',
                    'instance',
                    'section',
                    'added',
                ]),
                'instance' => omit($m['instance'], [
                    'id',
                    'course',
                    'timecreated',
                    'timemodified',
                    'revision',
                ]),
            ];
        }
        $sections[] = [
            'record' => omit($s['record'], [
                'id',
                'course',
                'sequence',
                'timemodified',
                'itemid',
            ]),
            'modules' => $ml,
        ];
    }
    $files = [];
    foreach ($c['files'] as $f) {
        if ($f['primary_design_file']) {
            $files[] = file_signature($f);
        }
    }
    usort($files, fn($a, $b) => strcmp(hash_data($a), hash_data($b)));
    return [
        'record' => omit($c['record'], [
            'id',
            'category',
            'fullname',
            'shortname',
            'idnumber',
            'sortorder',
            'timecreated',
            'timemodified',
            'cacherev',
            'originalcourseid',
            'visible',
            'visibleold',
        ]),
        'sections' => $sections,
        'files' => $files,
    ];
}

/**
 * Fingerprint stable source content across a trial.
 *
 * @param array $s Captured evidence or mutable owned run state.
 * @return string
 */
function source_signature(array $s): string {
    $cats = [];
    foreach ($s['categories'] as $id => $c) {
        $cats[$id] = [
            'record' => omit($c['record'], ['sortorder']),
            'files' => $c['files'],
            'roles' => $c['roles'],
            'role_overrides' => $c['role_overrides'],
            'cohorts' => $c['cohorts'],
        ];
    }
    $courses = [];
    foreach ($s['courses'] as $id => $c) {
        $c['record'] = omit($c['record'], ['sortorder', 'cacherev']);
        $courses[$id] = $c;
    }
    return hash_data(['categories' => $cats, 'courses' => $courses]);
}

/**
 * Freeze workload, endpoints, ordering, settings and evidence limitations.
 *
 * @param string $id Recorded object or experiment identifier.
 * @param array $o Parsed benchmark CLI options.
 * @param array $s Captured evidence or mutable owned run state.
 * @param string $script Absolute benchmark entry-point path.
 * @return array
 */
function protocol(string $id, array $o, array $s, string $script): array {
    return [
        'revision' => REVISION,
        'experiment' => $id,
        'created_utc' => gmdate('c'),
        'source_category' => $s['root'],
        'source_totals' => $s['totals'],
        'source_signature' => source_signature($s),
        'scope_warnings' => $s['scope_warnings'],
        'schedule' => schedule($o['runs'], $o['warmups'], $o['seed']),
        'options' => $o,
        'implementations' => [
            'native' => 'Core category APIs + copy_helper + actual asynchronous_copy_task',
            'clonecategory' => 'Installed manager::create_job(full) + actual clone_category_task',
        ],
        'matched_policy' => 'One isolated copy-worker PHP process/method/run; sequential exact owned tas'
            . 'ks, not parallel execution.',
        'primary_endpoint' => 'Preparation launch through own postprocessing exit; excludes verification, '
            . 'source hashing and cleanup.',
        'settings_policy' => 'Unmodified production defaults; users/keptroles disabled. Optional flags di'
            . 'ffer, so no engine-controlled or universally equivalent-settings claim.',
        'oracle' => 'Course/section/module design fields and design file SHA256; learner interac'
            . 'tions/grades excluded; grade structure generated by restore allowed. Scope '
            . 'warnings make performance-summary eligibility false.',
        'cleanup' => 'Common core API harness with final postprocessing-complete ownership guards'
            . ', not a plugin-native rollback comparison.',
        'statistics' => 'Paired counterbalanced blocks; warmups retained but excluded from summaries'
            . '; no outlier removal; 10000 paired bootstrap resamples; n<2 CI unavailable;'
            . ' shared-host within-session intervals only.',
        'unmeasured' => [
            'production lock wait/hold separately',
            'DB server CPU and physical I/O',
            'network bytes',
            'energy',
            'independent cross-host confidence intervals',
        ],
        'script_sha256' => hash_file('sha256', $script),
        'library_sha256' => hash_file('sha256', __FILE__),
    ];
}

/**
 * Read available process IO counters from Linux procfs.
 *
 * @return array
 */
function io_values(): array {
    if (!is_readable('/proc/self/io')) {
        return [];
    }
    $out = [];
    foreach (explode("\n", trim(file_get_contents('/proc/self/io'))) as $line) {
        [$k, $v] = explode(':', $line, 2);
        $out[$k] = (int) trim($v);
    }
    return $out;
}

/**
 * Convert one resource-accounting CPU counter to seconds.
 *
 * @param array $r Captured record or resource-accounting values.
 * @param string $key Required payload field or resource-counter name.
 * @return float|null
 */
function cpu_value(array $r, string $key): ?float {
    return isset($r[$key . '.tv_sec']) ? (float) $r[$key . '.tv_sec'] + ($r[$key . '.tv_usec'] ?? 0) / 1000000.0 : null;
}

/**
 * Read the process high-water RSS in KiB when available.
 *
 * @return int|null
 */
function hwm_kib(): ?int {
    if (
        is_readable('/proc/self/status')
        && preg_match('/VmHWM:\s+(\d+) kB/', file_get_contents('/proc/self/status'), $m)
    ) {
        return (int) $m[1];
    }
    $r = getrusage();
    return isset($r['ru_maxrss']) ? (int) $r['ru_maxrss'] : null;
}

/**
 * Validate a scheduled run identifier and private experiment directory.
 *
 * @param string $experiment Absolute private experiment directory.
 * @param string $run Scheduled run identifier.
 * @return string
 */
function run_directory(string $experiment, string $run): string {
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $run)) {
        throw new \RuntimeException('Invalid recorded run token.');
    }
    if (!is_file($experiment . '/protocol.json') || !is_file($experiment . '/baseline.json')) {
        throw new \RuntimeException('Missing experiment protocol/baseline.');
    }
    $dir = $experiment . '/runs/' . $run;
    if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
        throw new \RuntimeException('Cannot create run directory.');
    }
    return $dir;
}

/**
 * Locate the exact newly queued task by class and owned custom data.
 *
 * @param string $class Exact production task class name.
 * @param string $key Required payload field or resource-counter name.
 * @param mixed $value Expected owned payload value.
 * @return int
 */
function task_id(string $class, string $key, $value): int {
    global $DB;
    // Fetch the matching payload, not every growing pending queue into PHP.
    $where = 'classname=:class AND ' . $DB->sql_like('customdata', ':needle', true);
    $rows = $DB->get_records_select('task_adhoc', $where, ['class' => $class, 'needle' => '%' . $DB->sql_like_escape(
        (string) $value
    ) . '%'], 'id DESC', 'id,customdata', 0, 2);
    foreach ($rows as $r) {
        $d = json_decode($r->customdata, true);
        if ((string) ($d[$key] ?? '') === (string) $value) {
            return (int) $r->id;
        }
    }
    throw new \RuntimeException('Owned task disappeared before dispatch; possible concurrent Cron. Trial invalid.');
}

/**
 * Reject changed, running or retry-delayed task ownership records.
 *
 * @param array $state Recorded task and resource ownership state.
 * @param int $id Recorded object or experiment identifier.
 * @return \stdClass
 */
function owned_task(array $state, int $id): \stdClass {
    global $DB;
    if (!isset($state['tasks'][(string) $id])) {
        throw new \RuntimeException('Task ID is outside recorded run.');
    }
    $r = $DB->get_record('task_adhoc', ['id' => $id], '*', MUST_EXIST);
    $expected = $state['tasks'][(string) $id];
    if (
        $r->classname !== $expected['class']
        || hash_data(json_decode($r->customdata, true)) !== $expected['payload_hash']
    ) {
        throw new \RuntimeException('Task payload/owner changed.');
    }
    if (!empty($r->timestarted) || !empty($r->faildelay)) {
        throw new \RuntimeException('Task already running or delayed.');
    }
    return $r;
}

/**
 * Append a small ownership event without rewriting growing state per item.
 *
 * @param string $dir Absolute private run or output directory.
 * @param string $event Append-only ownership journal event name.
 * @param array $data Structured evidence values.
 * @return void
 */
function journal(string $dir, string $event, array $data): void {
    $line = json_encode(['utc' => gmdate('c'), 'event' => $event, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        . PHP_EOL;
    if (file_put_contents($dir . '/ownership.jsonl', $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
        throw new \RuntimeException('Ownership journal write failed.');
    }
}

/**
 * Record the exact queued task payload and observation time.
 *
 * @param array $s Captured evidence or mutable owned run state.
 * @param int $id Recorded object or experiment identifier.
 * @param string $dir Absolute private run or output directory.
 * @return void
 */
function remember_task(array &$s, int $id, string $dir): void {
    global $DB;
    $r = $DB->get_record('task_adhoc', ['id' => $id], '*', MUST_EXIST);
    $s['tasks'][$id] = [
        'class' => $r->classname,
        'payload_hash' => hash_data(json_decode($r->customdata, true)),
        'payload' => json_decode($r->customdata, true),
        'observed_queued_unix' => microtime(true),
        'nextruntime' => $r->nextruntime,
    ];
    journal($dir, 'task', ['id' => $id, 'record' => $s['tasks'][$id]]);
}

/**
 * Fingerprint category records, context data and files before cleanup.
 *
 * @param int $id Recorded object or experiment identifier.
 * @return string
 */
function category_guard(int $id): string {
    global $DB;
    $r = $DB->get_record('course_categories', ['id' => $id], '*', MUST_EXIST);
    unset($r->sortorder);
    $ctx = \context_coursecat::instance($id);
    $data = [$r];
    foreach (
        [
        'role_assignments',
        'role_capabilities',
        'cohort',
        'question_categories',
        'contentbank_content',
        'customfield_data',
        'files',
        ] as $t
    ) {
        $data[] = $DB->get_records($t, ['contextid' => $ctx->id], 'id');
    }
    return hash_data($data);
}

/**
 * Execute one isolated sourcecheck, prepare, copy, postprocess, verify or cleanup phase.
 *
 * @param array $o Parsed benchmark CLI options.
 * @param int $processstart Monotonic PHP entry timestamp in nanoseconds.
 * @param array $cpustart CPU resource counters on PHP entry.
 * @return void
 */
function worker(array $o, int $processstart, array $cpustart): void {
    global $DB, $CFG, $USER;
    if (
        !in_array($o['phase'], [
        'sourcecheck',
        'prepare',
        'copy',
        'postprocess',
        'verify',
        'cleanup',
        ], true)
    ) {
        throw new \RuntimeException('Unknown internal phase.');
    }
    $experiment = realpath($o['experiment-dir']);
    if ($experiment === false) {
        throw new \RuntimeException('Experiment directory missing.');
    }
    $dir = run_directory($experiment, $o['run']);
    $phase = $o['phase'];
    $p = read_json($experiment . '/protocol.json');
    $base = read_json($experiment . '/baseline.json');
    $runlock = fopen($dir . '/run.lock', 'c');
    if (!$runlock || !flock($runlock, LOCK_EX | LOCK_NB)) {
        throw new \RuntimeException('Another worker/cleanup owns this run.');
    }
    $item = null;
    foreach ($p['schedule'] as $i) {
        if ($p['experiment'] . '_' . $i['token'] === $o['run']) {
            $item = $i;
            break;
        }
    }
    if ($item === null) {
        throw new \RuntimeException('Run is not in frozen schedule.');
    }
    $reserve = str_repeat('x', 262144);
    $status = 'not_completed';
    $started = hrtime(true);
    $q0 = $DB->perf_get_queries();
    $r0 = $DB->perf_get_reads();
    $w0 = $DB->perf_get_writes();
    $io0 = io_values();
    register_shutdown_function(function () use (
        $dir,
        $phase,
        $processstart,
        $cpustart,
        $started,
        $q0,
        $r0,
        $w0,
        $io0,
        &$reserve,
        &$status
) {
        global $DB;
        $reserve = null;
        $end = getrusage();
        $io = io_values();
        $delta = [];
        foreach ($io as $k => $v) {
            $delta[$k] = $v - ($io0[$k] ?? 0);
        }
        write_json($dir . '/' . $phase . '.metrics.json', [
            'status' => $status,
            'utc' => gmdate('c'),
            'operation_seconds_after_bootstrap' => (hrtime(true) - $started) / 1000000000.0,
            'php_entry_to_shutdown_seconds' => (hrtime(true) - $processstart) / 1000000000.0,
            'cpu_user_entry_to_shutdown_seconds' => cpu_value($end, 'ru_utime') - cpu_value($cpustart, 'ru_utime'),
            'cpu_system_entry_to_shutdown_seconds' => cpu_value($end, 'ru_stime') - cpu_value($cpustart, 'ru_stime'),
            'os_hwm_rss_kib' => hwm_kib(),
            'php_peak_allocated_bytes' => memory_get_peak_usage(true),
            'db_queries_after_bootstrap' => $DB->perf_get_queries() - $q0,
            'db_reads_after_bootstrap' => $DB->perf_get_reads() - $r0,
            'db_writes_after_bootstrap' => $DB->perf_get_writes() - $w0,
            'io_after_bootstrap_delta' => $delta,
            'last_error' => error_get_last(),
        ]);
    });
    $statefile = $dir . '/state.json';
    $state = is_file($statefile) ? read_json($statefile) : null;
    if ($phase === 'sourcecheck') {
        if ($state !== null) {
            throw new \RuntimeException('Duplicate run submission rejected.');
        }
        $current = capture($base['root']);
        $same = source_signature($base) === source_signature($current);
        write_json($dir . '/sourcecheck.json', ['utc' => gmdate('c'), 'pass' => $same, 'source_signature'
            => source_signature($current)]);
        if (!$same) {
            throw new \RuntimeException('Source changed since frozen baseline.');
        }
        $status = 'ok';
        return;
    }
    if ($phase === 'prepare') {
        if ($state !== null) {
            throw new \RuntimeException('Run already exists; no duplicate queue.');
        }
        if (empty(read_json($dir . '/sourcecheck.json')['pass'])) {
            throw new \RuntimeException('Sourcecheck required.');
        }
        preflight($base['root']);
        $state = [
            'run' => $o['run'],
            'method' => $item['method'],
            'source' => $base['root'],
            'phase' => 'preparing',
            'categories' => [],
            'courses' => [],
            'tasks' => [],
            'created_utc' => gmdate('c'),
        ];
        write_json($statefile, $state);
        $container = \core_course_category::create([
            'name' => 'Benchmark ' . $o['run'],
            'parent' => 0,
            'visible' => 0,
            'idnumber' => 'LCB-' . $o['run'],
        ]);
        $state['container'] = (int) $container->id;
        write_json($statefile, $state);
        if ($item['method'] === 'clonecategory') {
            $state['jobid'] = \local_clonecategory\manager::create_job($base['root'], $state['container'], '', ' ['
                . $o['run'] . ']', (int) $USER->id, 'full');
            write_json($statefile, $state);
            remember_task($state, task_id('\local_clonecategory\task\clone_category_task', 'jobid', $state['jobid']), $dir);
        } else {
            foreach ($base['categories'] as $sid => $cat) {
                $r = $cat['record'];
                $d = \core_course_category::create([
                    'name' => $r['name'],
                    'description' => $r['description'],
                    'descriptionformat' => $r['descriptionformat'],
                    'parent' => (int) $sid === $base['root'] ? $state['container'] : $state['categories'][(string) $r['parent']],
                    'visible' => 0,
                    'idnumber' => 'LCB-' . $o['run'] . '-K' . $sid,
                ]);
                $state['categories'][$sid] = (int) $d->id;
                journal($dir, 'category', ['source' => $sid, 'destination' => (int) $d->id]);
                $fs = get_file_storage();
                $srcctx = \context_coursecat::instance((int) $sid);
                $dstctx = \context_coursecat::instance($d->id);
                foreach ($fs->get_area_files($srcctx->id, 'coursecat', 'description', false, 'id', false) as $f) {
                    $fs->create_file_from_storedfile(['contextid' => $dstctx->id], $f);
                }
            }
            write_json($statefile, $state);
            foreach ($base['courses'] as $sid => $course) {
                $r = $course['record'];
                $state['submitting_course'] = (int) $sid;
                journal($dir, 'submitting_course', ['source' => $sid]);
                $before = microtime(true);
                $data = (object) [
                    'courseid' => (int) $sid,
                    'fullname' => \core_text::substr($r['fullname'] . ' [' . $o['run'] . ']', 0, 254),
                    'shortname' => 'LCB-' . $o['run'] . '-C' . $sid,
                    'category' => $state['categories'][(string) $r['category']],
                    'visible' => 0,
                    'startdate' => $r['startdate'],
                    'enddate' => $r['enddate'],
                    'idnumber' => 'LCB-' . $o['run'] . '-C' . $sid,
                    'userdata' => 0,
                ];
                $ids = \copy_helper::create_copy(\copy_helper::process_formdata($data));
                $state['controllers'][$sid] = $ids;
                journal($dir, 'controllers', ['source' => $sid, 'ids' => $ids]);
                $rc = $DB->get_record('backup_controllers', ['backupid' => $ids['restoreid']], '*', MUST_EXIST);
                $state['courses'][$sid] = (int) $rc->itemid;
                $id = task_id('\core\task\asynchronous_copy_task', 'restoreid', $ids['restoreid']);
                remember_task($state, $id, $dir);
                $state['tasks'][$id]['enqueue_bound_start_unix'] = $before;
                $state['tasks'][$id]['enqueue_bound_end_unix'] = microtime(true);
                journal($dir, 'queued_course', [
                    'source' => $sid,
                    'destination' => $state['courses'][$sid],
                    'task' => $id,
                    'bounds' => $state['tasks'][$id],
                ]);
            }
        }
        unset($state['submitting_course']);
        $state['phase'] = 'queued';
        write_json($statefile, $state);
        echo 'QUEUED ' . count($state['tasks']) . ' actual production tasks' . PHP_EOL;
        $status = 'ok';
        return;
    }
    if ($state === null) {
        throw new \RuntimeException('Recorded run missing.');
    }
    if ($phase === 'copy' || $phase === 'postprocess') {
        if (CLI_MAINTENANCE || moodle_needs_upgrading() || !get_config('core', 'cron_enabled')) {
            throw new \RuntimeException('Core task preflight failed.');
        }
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);
        \core\cron::setup_user();
        \core\local\cli\shutdown::script_supports_graceful_exit();
        if ($phase === 'copy') {
            if ($state['phase'] !== 'queued') {
                throw new \RuntimeException('Copy phase already used.');
            }
            foreach (array_keys($state['tasks']) as $id) {
                owned_task($state, (int) $id);
                \core\cron::run_adhoc_task((int) $id);
                if ($DB->record_exists('task_adhoc', ['id' => $id])) {
                    throw new \RuntimeException('Copy task pending or retrying after execution.');
                }
            }
            if ($state['method'] === 'clonecategory') {
                $job = $DB->get_record('local_clonecategory_jobs', ['id' => $state['jobid']], '*', MUST_EXIST);
                $state['plugin_job'] = (array) $job;
                if ($job->status !== 'completed') {
                    throw new \RuntimeException('Production plugin job did not complete.');
                }
                foreach ($DB->get_records('local_clonecategory_items', ['jobid' => $state['jobid']], 'id') as $it) {
                    if ($it->status !== 'completed') {
                        throw new \RuntimeException('Plugin item not complete.');
                    }
                    $key = $it->itemtype === 'course' ? 'courses' : 'categories';
                    $state[$key][(string) $it->sourceid] = (int) $it->itemid;
                }
            }
            if (
                count($state['courses']) !== count($base['courses'])
                || count($state['categories']) !== count($base['categories'])
            ) {
                throw new \RuntimeException('Not all source objects were mapped.');
            }
            $state['phase'] = 'copied';
            write_json($statefile, $state);
            $status = 'ok';
            return;
        }
        if ($state['phase'] !== 'copied') {
            throw new \RuntimeException('Postprocessing requires completed copies.');
        }
        $ids = array_values($state['courses']);
        $allowed = ['\core_course\task\regrade_final_grades', '\core\task\refresh_mod_calendar_events_task'];
        $selected = [];
        foreach ($DB->get_records('task_adhoc', null, 'id') as $t) {
            $d = json_decode($t->customdata, true);
            if (in_array($t->classname, $allowed, true) && in_array((int) ($d['courseid'] ?? 0), $ids, true)) {
                $selected[] = $t;
            }
        }
        write_json($dir . '/postprocessing_tasks.json', array_values($selected));
        foreach ($selected as $t) {
            if (!empty($t->timestarted) || !empty($t->faildelay)) {
                throw new \RuntimeException('Owned postprocessing already active/delayed.');
            }
            \core\cron::run_adhoc_task((int) $t->id);
            if ($DB->record_exists('task_adhoc', ['id' => $t->id])) {
                throw new \RuntimeException('Postprocessing incomplete.');
            }
        }
        $state['postprocessing_tasks'] = count($selected);
        $state['phase'] = 'ready';
        write_json($statefile, $state);
        $status = 'ok';
        return;
    }
    if ($phase === 'verify') {
        if ($state['phase'] !== 'ready') {
            throw new \RuntimeException('Verification requires own postprocessing completion.');
        }
        verify($experiment, $dir, $base, $state);
        $status = 'ok';
        return;
    }
    if ($phase === 'cleanup') {
        cleanup($dir, $base, $state);
        $status = 'ok';
        return;
    }
}

/**
 * Compare mapped design content and capture final deletion guards.
 *
 * @param string $experiment Absolute private experiment directory.
 * @param string $dir Absolute private run or output directory.
 * @param array $base Frozen source baseline.
 * @param array $s Captured evidence or mutable owned run state.
 * @return void
 */
function verify(string $experiment, string $dir, array $base, array &$s): void {
    global $DB;
    $source = capture($base['root']);
    $dest = capture($s['categories'][(string) $base['root']]);
    write_json($dir . '/destination.json', $dest);
    write_json($dir . '/source_after.json', $source);
    $checks = [];
    $ok = source_signature($source) === source_signature($base);
    $srcok = $ok;
    $dstcourses = array_values($s['courses']);
    $dstcats = array_values($s['categories']);
    $ok = $ok && count(array_unique($dstcourses)) === count($base['courses'])
        && count(array_unique($dstcats)) === count($base['categories'])
        && count($dest['courses']) === count($base['courses'])
        && count($dest['categories']) === count($base['categories']);
    foreach ($base['categories'] as $sid => $cat) {
        $did = $s['categories'][$sid];
        $d = $dest['categories'][$did];
        $r = $cat['record'];
        $dr = $d['record'];
        $parent = (int) $sid === $base['root'] ? $s['container'] : $s['categories'][(string) $r['parent']];
        $cf = array_map(fn($f) => file_signature($f), $cat['files']);
        $df = array_map(fn($f) => file_signature($f), $d['files']);
        usort($cf, fn($a, $b) => strcmp(hash_data($a), hash_data($b)));
        usort($df, fn($a, $b) => strcmp(hash_data($a), hash_data($b)));
        $pass = $r['name'] === $dr['name'] && $r['description'] === $dr['description']
            && (int) $r['descriptionformat'] === (int) $dr['descriptionformat']
            && (int) $dr['parent'] === (int) $parent
            && hash_data($cf) === hash_data($df);
        $checks[] = [
            'entity' => 'category',
            'source' => (int) $sid,
            'destination' => $did,
            'pass' => $pass,
        ];
        $ok = $ok && $pass;
    }
    foreach ($base['courses'] as $sid => $course) {
        $did = $s['courses'][$sid];
        $d = $dest['courses'][$did];
        $a = normalized_course($course);
        $b = normalized_course($d);
        $content = hash_data($a) === hash_data($b);
        $expectedname = \core_text::substr($course['record']['fullname'] . ' [' . $s['run'] . ']', 0, 254);
        $nameok = $d['record']['fullname'] === $expectedname;
        // Core calculate_course_names adds a localized copy suffix for repeated full names.
        // Normalize ONLY its documented exact full/short-name pair, never arbitrary renaming.
        if (!$nameok && $s['method'] === 'native') {
            $prefix = 'LCB-' . $s['run'] . '-C' . $sid;
            if (preg_match('/^' . preg_quote($prefix, '/') . '_(\d+)$/D', $d['record']['shortname'], $m)) {
                foreach (array_keys(get_string_manager()->get_list_of_translations()) as $lang) {
                    $suffix = ' ' . get_string_manager()->get_string('copyasnoun', 'moodle', null, $lang) . ' ' . $m[1];
                    if (
                        $d['record']['fullname'] === \core_text::substr(
                            $expectedname,
                            0,
                            \core_course\constants::FULLNAME_MAXIMUM_LENGTH - strlen($suffix)
                        )
                        . $suffix
                    ) {
                        $nameok = true;
                        break;
                    }
                }
            }
        }
        $pass = $content && $nameok
            && (int) $d['record']['category'] === $s['categories'][(string) $course['record']['category']]
            && (int) $d['record']['visible'] === 0
            && $d['extra']['learner_enrolments'] === 0
            && $d['extra']['learner_grade_rows'] === 0;
        $checks[] = [
            'entity' => 'course',
            'source' => (int) $sid,
            'destination' => $did,
            'content_match' => $content,
            'pass' => $pass,
        ];
        $ok = $ok && $pass;
        if (!$content) {
            write_json($dir . '/difference_course_' . $sid . '.json', ['source' => $a, 'destination' => $b]);
        }
    }
    $s['verification'] = [
        'pass' => $ok,
        'source_unchanged' => $srcok,
        'checks' => $checks,
        'utc' => gmdate('c'),
        'scope_warnings' => $base['scope_warnings'],
    ];
    write_json($dir . '/verification.json', $s['verification']);
    if (!$ok) {
        $s['phase'] = 'verification_failed';
        write_json($dir . '/state.json', $s);
        throw new \RuntimeException('Content/source/cardinality verification failed; no winner claimed.');
    }
    $s['final_course_guards'] = [];
    foreach ($dstcourses as $id) {
        $s['final_course_guards'][$id] = \local_clonecategory\manager::fingerprint('course', (int) $id);
    }
    $s['final_category_guards'] = [];
    foreach (array_merge([$s['container']], $dstcats) as $id) {
        $s['final_category_guards'][$id] = category_guard((int) $id);
    }
    $s['phase'] = 'verified';
    write_json($dir . '/state.json', $s);
    export_entities($experiment, $dir, $base, $dest, $s);
    echo 'VERIFIED: ' . count($dstcourses) . ' courses; ' . count($dstcats)
        . ' categories; source unchanged'
        . PHP_EOL;
}

/**
 * Delete only verified owned copies after final membership and change checks.
 *
 * @param string $dir Absolute private run or output directory.
 * @param array $base Frozen source baseline.
 * @param array $s Captured evidence or mutable owned run state.
 * @return void
 */
function cleanup(string $dir, array $base, array &$s): void {
    global $DB;
    if ($s['phase'] !== 'verified' || empty($s['verification']['pass'])) {
        throw new \RuntimeException('Cleanup requires verified final guards.');
    }
    $container = $DB->get_record('course_categories', ['id' => $s['container']], '*', MUST_EXIST);
    if ($container->idnumber !== 'LCB-' . $s['run']) {
        throw new \RuntimeException('Container ownership changed.');
    }
    $ids = array_values($s['courses']);
    $cats = array_merge([$s['container']], array_values($s['categories']));
    foreach ($s['final_course_guards'] as $id => $hash) {
        if (!hash_equals($hash, \local_clonecategory\manager::fingerprint('course', (int) $id))) {
            throw new \RuntimeException('Copied course changed; no cleanup.');
        }
        $c = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
        if (!in_array((int) $c->category, $cats, true)) {
            throw new \RuntimeException('Copied course moved.');
        }
    }
    foreach ($s['final_category_guards'] as $id => $hash) {
        if (!hash_equals($hash, category_guard((int) $id))) {
            throw new \RuntimeException('Category changed; no cleanup.');
        }
        foreach ($DB->get_records('course', ['category' => $id]) as $r) {
            if (!in_array((int) $r->id, $ids, true)) {
                throw new \RuntimeException('Untracked course; no recursive deletion.');
            }
        }
        foreach ($DB->get_records('course_categories', ['parent' => $id]) as $r) {
            if (!in_array((int) $r->id, $cats, true)) {
                throw new \RuntimeException('Untracked category; no recursive deletion.');
            }
        }
    }
    foreach ($DB->get_records('task_adhoc') as $t) {
        $d = json_decode($t->customdata, true);
        if (isset($d['courseid']) && in_array((int) $d['courseid'], $ids, true)) {
            throw new \RuntimeException('Destination task still pending.');
        }
    }
    $s['phase'] = 'cleanup_started';
    write_json($dir . '/state.json', $s);
    foreach ($ids as $id) {
        if (!hash_equals($s['final_course_guards'][$id], \local_clonecategory\manager::fingerprint('course', (int) $id))) {
            throw new \RuntimeException('Course changed during cleanup.');
        }
        \local_clonecategory\manager::delete_owned_course((int) $id);
    }
    usort($cats, fn($a, $b) => (int) $DB->get_field('course_categories', 'depth', ['id' => $b])
        <=> (int) $DB->get_field('course_categories', 'depth', ['id' => $a]));
    foreach ($cats as $id) {
        if (
            $DB->record_exists('course', ['category' => $id])
            || $DB->record_exists('course_categories', ['parent' => $id])
        ) {
            throw new \RuntimeException('Category not empty; stopped.');
        }
        \core_course_category::get($id)->delete_full(false);
        if ($DB->record_exists('course_categories', ['id' => $id])) {
            throw new \RuntimeException('Category remained.');
        }
    }
    foreach ($ids as $id) {
        if ($DB->record_exists('course', ['id' => $id])) {
            throw new \RuntimeException('Course remained after cleanup.');
        }
    }
    if (source_signature(capture($base['root'])) !== source_signature($base)) {
        throw new \RuntimeException('Source changed during cleanup.');
    }
    $s['phase'] = 'cleaned';
    $s['cleanup_utc'] = gmdate('c');
    write_json($dir . '/state.json', $s);
    echo 'OWNED CORE CLEANUP PASS (not plugin rollback)' . PHP_EOL;
}

/**
 * Measure one fresh worker process and preserve samples, logs and exit status.
 *
 * @param string $script Absolute benchmark entry-point path.
 * @param string $experiment Absolute private experiment directory.
 * @param string $run Scheduled run identifier.
 * @param string $phase Worker phase name.
 * @param int $timeout Per-phase timeout in seconds.
 * @return array
 */
function run_child(string $script, string $experiment, string $run, string $phase, int $timeout): array {
    $dir = run_directory($experiment, $run);
    $args = [
        PHP_BINARY,
        $script,
        '--phase=' . $phase,
        '--experiment-dir=' . $experiment,
        '--run=' . $run,
    ];
    if (is_file($dir . '/' . $phase . '.process.json') || is_file($dir . '/' . $phase . '.log')) {
        throw new \RuntimeException('Existing phase evidence will not be overwritten: ' . $phase);
    }
    $before = getrusage(1);
    $start = hrtime(true);
    $utc = microtime(true);
    $log = $dir . '/' . $phase . '.log';
    $proc = proc_open($args, [
        0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', $log, 'wb'],
        2 => ['redirect', 1],
    ], $pipes);
    if (!is_resource($proc)) {
        throw new \RuntimeException('Child process could not start.');
    }
    $lastsample = -1;
    $timedout = false;
    $state = proc_get_status($proc);
    $pid = $state['pid'];
    do {
        $elapsed = (hrtime(true) - $start) / 1000000000.0;
        if ($elapsed - $lastsample >= 0.5) {
            $sample = [
                'run' => $run,
                'phase' => $phase,
                'pid' => $pid,
                'utc_unix' => microtime(true),
                'elapsed_seconds' => $elapsed,
                'rss_kib' => null,
                'hwm_kib' => null,
            ];
            if (is_readable('/proc/' . $pid . '/status')) {
                $txt = file_get_contents('/proc/' . $pid . '/status');
                foreach (['VmRSS' => 'rss_kib', 'VmHWM' => 'hwm_kib'] as $label => $key) {
                    if (preg_match('/' . $label . ':\s+(\d+) kB/', $txt, $m)) {
                        $sample[$key] = (int) $m[1];
                    }
                }
            }
            append_csv($experiment . '/process_samples.csv', $sample);
            $lastsample = $elapsed;
        }
        if ($elapsed > $timeout) {
            $timedout = true;
            proc_terminate($proc);
            usleep(200000);
            $test = proc_get_status($proc);
            if ($test['running']) {
                proc_terminate($proc, 9);
            }
        }
        if (!$state['running']) {
            break;
        }
        usleep(10000);
        $state = proc_get_status($proc);
    } while (true);
    $exit = $state['exitcode'];
    $close = proc_close($proc);
    if ($exit < 0) {
        $exit = $close;
    }
    $after = getrusage(1);
    $user = (cpu_value($after, 'ru_utime') ?? 0) - (cpu_value($before, 'ru_utime') ?? 0);
    $system = (cpu_value($after, 'ru_stime') ?? 0) - (cpu_value($before, 'ru_stime') ?? 0);
    $r = [
        'run' => $run,
        'phase' => $phase,
        'command' => $args,
        'pid' => $pid,
        'start_unix' => $utc,
        'end_unix' => microtime(true),
        'wall_seconds' => (hrtime(true) - $start) / 1000000000.0,
        'exit_status' => $exit,
        'timed_out' => $timedout,
        'cpu_user_children_delta_seconds' => $user,
        'cpu_system_children_delta_seconds' => $system,
        'cpu_scope' => 'parent getrusage(RUSAGE_CHILDREN) sequential delta',
    ];
    write_json($dir . '/' . $phase . '.process.json', $r);
    $metricfile = $dir . '/' . $phase . '.metrics.json';
    $metric = is_file($metricfile) ? read_json($metricfile) : [];
    append_csv($experiment . '/phases.csv', [
        'run' => $run,
        'phase' => $phase,
        'command_json' => $args,
        'pid' => $pid,
        'start_unix' => $utc,
        'end_unix' => $r['end_unix'],
        'wall_seconds' => $r['wall_seconds'],
        'exit_status' => $exit,
        'timed_out' => $timedout,
        'cpu_user_seconds' => $user,
        'cpu_system_seconds' => $system,
        'os_hwm_rss_kib' => $metric['os_hwm_rss_kib'] ?? null,
        'php_peak_allocated_bytes' => $metric['php_peak_allocated_bytes'] ?? null,
        'db_queries_after_bootstrap' => $metric['db_queries_after_bootstrap'] ?? null,
        'db_reads_after_bootstrap' => $metric['db_reads_after_bootstrap'] ?? null,
        'db_writes_after_bootstrap' => $metric['db_writes_after_bootstrap'] ?? null,
        'io_after_bootstrap_json' => $metric['io_after_bootstrap_delta'] ?? null,
        'last_error_json' => $metric['last_error'] ?? null,
    ]);
    if ($exit !== 0 || $timedout || ($metric['status'] ?? 'missing') !== 'ok') {
        throw new \RuntimeException($phase . ' failed: exit=' . $exit . '; see ' . $log);
    }
    return $r;
}

/**
 * Count logical file references and deduplicated content bytes.
 *
 * @param array $files Captured file metadata records.
 * @return array
 */
function volume(array $files): array {
    $unique = [];
    $logical = 0;
    $design = 0;
    foreach ($files as $f) {
        $logical += (int) $f['filesize'];
        $unique[$f['contenthash']] = (int) $f['filesize'];
        if ($f['primary_design_file']) {
            $design += (int) $f['filesize'];
        }
    }
    return [
        'file_references' => count($files),
        'logical_bytes' => $logical,
        'unique_contents' => count($unique),
        'unique_bytes' => array_sum($unique),
        'design_logical_bytes' => $design,
    ];
}

/**
 * Prefix exported volume column names.
 *
 * @param string $prefix CSV volume-column prefix.
 * @param array $data Structured evidence values.
 * @return array
 */
function prefixed(string $prefix, array $data): array {
    $out = [];
    foreach ($data as $key => $value) {
        $out[$prefix . $key] = $value;
    }
    return $out;
}

/**
 * Export mapped entities and validation checks as analysis-ready CSV.
 *
 * @param string $experiment Absolute private experiment directory.
 * @param string $dir Absolute private run or output directory.
 * @param array $source Source category identifier or captured source evidence.
 * @param array $dest Captured destination evidence.
 * @param array $s Captured evidence or mutable owned run state.
 * @return void
 */
function export_entities(string $experiment, string $dir, array $source, array $dest, array $s): void {
    $common = ['run' => $s['run'], 'method' => $s['method']];
    $sd = (int) $source['categories'][$source['root']]['record']['depth'];
    $dd = (int) $dest['categories'][$s['categories'][$source['root']]]['record']['depth'];
    foreach ($source['categories'] as $id => $c) {
        $d = $dest['categories'][$s['categories'][$id]];
        $path = $c['record']['path'];
        $courseids = [];
        $files = $c['files'];
        foreach ($source['courses'] as $cid => $course) {
            $cat = $source['categories'][(string) $course['record']['category']]['record'];
            if ($cat['path'] === $path || str_starts_with($cat['path'], $path . '/')) {
                $courseids[] = $cid;
                $files = array_merge($files, $course['files']);
            }
        }
        $direct = 0;
        $descendants = 0;
        foreach ($source['courses'] as $cc) {
            if ((int) $cc['record']['category'] === (int) $id) {
                $direct++;
            }
        }
        foreach ($source['categories'] as $cc) {
            if (str_starts_with($cc['record']['path'], $path . '/')) {
                $descendants++;
            }
        }
        append_csv($experiment . '/categories.csv', $common + [
            'source_id' => (int) $id,
            'destination_id' => (int) $d['record']['id'],
            'source_parent' => (int) $c['record']['parent'],
            'destination_parent' => (int) $d['record']['parent'],
            'name' => $c['record']['name'],
            'source_absolute_depth' => (int) $c['record']['depth'],
            'destination_absolute_depth' => (int) $d['record']['depth'],
            'source_relative_depth' => (int) $c['record']['depth'] - $sd,
            'destination_relative_depth' => (int) $d['record']['depth'] - $dd,
            'direct_courses' => $direct,
            'descendant_categories' => $descendants,
            'subtree_courses' => count($courseids),
        ] + prefixed('subtree_', volume($files)) + ['source_record_json' => $c, 'destination_record_json' => $d]);
    }
    foreach ($source['courses'] as $id => $c) {
        $d = $dest['courses'][$s['courses'][$id]];
        append_csv($experiment . '/courses.csv', $common + [
            'source_id' => (int) $id,
            'destination_id' => (int) $d['record']['id'],
            'source_category' => (int) $c['record']['category'],
            'destination_category' => (int) $d['record']['category'],
            'source_name' => $c['record']['fullname'],
            'destination_name' => $d['record']['fullname'],
            'source_sections' => count($c['sections']),
            'destination_sections' => count($d['sections']),
        ] + prefixed('source_', volume($c['files'])) + prefixed('destination_', volume($d['files'])) + [
            'source_extra_json' => $c['extra'],
            'destination_extra_json' => $d['extra'],
            'source_record_json' => $c['record'],
            'destination_record_json' => $d['record'],
        ]);
        foreach ($c['sections'] as $i => $section) {
            $ds = $d['sections'][$i];
            $ctx = $common + [
                'source_course' => (int) $id,
                'destination_course' => (int) $d['record']['id'],
                'section_number' => (int) $section['record']['section'],
            ];
            append_csv($experiment . '/sections.csv', $ctx + [
                'source_name' => $section['record']['name'],
                'destination_name' => $ds['record']['name'],
                'source_record_json' => $section['record'],
                'destination_record_json' => $ds['record'],
            ]);
            foreach ($section['modules'] as $j => $m) {
                $dm = $ds['modules'][$j];
                append_csv($experiment . '/activities.csv', $ctx + [
                    'position' => $j,
                    'type' => $m['type'],
                    'source_module_id' => (int) $m['cm']['id'],
                    'destination_module_id' => (int) $dm['cm']['id'],
                    'source_name' => $m['instance']['name'] ?? null,
                    'destination_name' => $dm['instance']['name'] ?? null,
                    'source_record_json' => $m,
                    'destination_record_json' => $dm,
                ]);
            }
        }
        $dstfiles = [];
        foreach ($d['files'] as $f) {
            $k = hash_data(omit(file_signature($f), [
                'contenthash',
                'sha256',
                'filesize',
                'author',
                'license',
                'mimetype',
            ]));
            $dstfiles[$k][] = $f;
        }
        foreach ($c['files'] as $f) {
            $k = hash_data(omit(file_signature($f), [
                'contenthash',
                'sha256',
                'filesize',
                'author',
                'license',
                'mimetype',
            ]));
            $df = !empty($dstfiles[$k]) ? array_shift($dstfiles[$k]) : null;
            append_csv($experiment . '/files.csv', $common + [
                'source_course' => (int) $id,
                'destination_course' => (int) $d['record']['id'],
                'owner' => $f['owner'],
                'component' => $f['component'],
                'area' => $f['filearea'],
                'filepath' => $f['filepath'],
                'filename' => $f['filename'],
                'source_bytes' => (int) $f['filesize'],
                'destination_bytes' => $df === null ? null : (int) $df['filesize'],
                'source_contenthash' => $f['contenthash'],
                'destination_contenthash' => $df['contenthash'] ?? null,
                'source_sha256' => $f['sha256'],
                'destination_sha256' => $df['sha256'] ?? null,
                'primary_design_file' => $f['primary_design_file'],
                'payload_matches' => $df !== null && $f['sha256'] === $df['sha256'],
                'source_record_json' => $f,
                'destination_record_json' => $df,
            ]);
        }
    }
    foreach ($s['verification']['checks'] as $check) {
        append_csv($experiment . '/checks.csv', $common + [
            'entity' => $check['entity'],
            'source_id' => $check['source'],
            'destination_id' => $check['destination'],
            'pass' => $check['pass'],
            'content_match' => $check['content_match'] ?? null,
            'source_unchanged' => $s['verification']['source_unchanged'],
        ]);
    }
    foreach (['copy', 'postprocess'] as $phase) {
        $text = file_get_contents($dir . '/' . $phase . '.log');
        $blocks = explode('Execute adhoc task:', $text);
        array_shift($blocks);
        foreach ($blocks as $block) {
            $grab = function ($p) use ($block) {
                return preg_match($p, $block, $m) ? $m[1] : null;
            };
            $data = $grab('/Adhoc task custom data: (.+)/');
            $custom = $data ? json_decode($data, true) : [];
            $sid = $grab('/Processing asynchronous course copy for course id: (\d+)/');
            $did = $custom['courseid'] ?? null;
            if (!$sid && $did) {
                $reverse = array_flip($s['courses']);
                $sid = $reverse[$did] ?? null;
            }
            if (!$did && $sid) {
                $did = $s['courses'][$sid] ?? null;
            }
            append_csv($experiment . '/tasks.csv', $common + [
                'phase' => $phase,
                'task_id' => $grab('/Adhoc task id: (\d+)/'),
                'task_class' => trim(strtok($block, "\n")),
                'source_course_id' => $sid,
                'destination_course_id' => $did,
                'measurement_grain' => $sid ? 'course_task' : 'whole_job',
                'task_elapsed_seconds' => $grab('/\.\.\. used ([0-9.]+) seconds/'),
                'task_db_queries' => $grab('/\.\.\. used (\d+) dbqueries/'),
                'reported_peak_memory_text' => $grab('/\.\.\. used ([^\n]+) peak memory/'),
                'complete_marker' => str_contains($block, 'Adhoc task complete:'),
                'custom_data_json' => $custom,
            ]);
        }
    }
}

/**
 * Aggregate measured phases without adding verification or cleanup costs.
 *
 * @param string $experiment Absolute private experiment directory.
 * @param string $run Scheduled run identifier.
 * @param array $base Frozen source baseline.
 * @return array
 */
function trial_metrics(string $experiment, string $run, array $base): array {
    $dir = $experiment . '/runs/' . $run;
    $cpuu = 0;
    $cpus = 0;
    $peak = 0;
    $heap = 0;
    $queries = 0;
    $reads = 0;
    $writes = 0;
    $io = [];
    $stages = [];
    foreach (['prepare', 'copy', 'postprocess'] as $phase) {
        $p = read_json($dir . '/' . $phase . '.process.json');
        $m = read_json($dir . '/' . $phase . '.metrics.json');
        $cpuu += $p['cpu_user_children_delta_seconds'];
        $cpus += $p['cpu_system_children_delta_seconds'];
        $peak = max($peak, $m['os_hwm_rss_kib'] ?? 0);
        $heap = max($heap, $m['php_peak_allocated_bytes']);
        $queries += $m['db_queries_after_bootstrap'];
        $reads += $m['db_reads_after_bootstrap'];
        $writes += $m['db_writes_after_bootstrap'];
        foreach ($m['io_after_bootstrap_delta'] as $k => $v) {
            $io[$k] = ($io[$k] ?? 0) + $v;
        }
        $stages[$phase . '_wall_seconds'] = $p['wall_seconds'];
    }
    $state = read_json($dir . '/state.json');
    $verify = read_json($dir . '/verify.process.json');
    return $stages + [
        'cpu_user_seconds' => $cpuu,
        'cpu_system_seconds' => $cpus,
        'os_peak_rss_kib' => $peak ?: null,
        'php_peak_allocated_bytes' => $heap,
        'db_queries_after_bootstrap' => $queries,
        'db_reads_after_bootstrap' => $reads,
        'db_writes_after_bootstrap' => $writes,
        'io_read_bytes_after_bootstrap' => $io['read_bytes'] ?? null,
        'io_write_bytes_after_bootstrap' => $io['write_bytes'] ?? null,
        'io_logical_rchar_after_bootstrap' => $io['rchar'] ?? null,
        'io_logical_wchar_after_bootstrap' => $io['wchar'] ?? null,
        'verification_wall_seconds' => $verify['wall_seconds'],
        'source_categories' => $base['totals']['categories'],
        'source_courses' => $base['totals']['courses'],
        'source_sections' => $base['totals']['sections_including_general'],
        'source_modules' => $base['totals']['modules'],
        'source_file_references' => $base['totals']['file_references'],
        'source_logical_file_bytes' => $base['totals']['logical_file_bytes'],
        'source_unique_file_bytes' => $base['totals']['unique_content_bytes'],
        'source_maximum_relative_depth' => $base['totals']['maximum_relative_category_depth'],
        'copy_tasks' => count($state['tasks']),
        'postprocessing_tasks' => $state['postprocessing_tasks'],
        'source_unchanged' => $state['verification']['source_unchanged'],
    ];
}

/**
 * Persist all trial rows, including failures and warmups.
 *
 * @param string $directory Private experiment directory.
 * @param array $rows All trial records, including warmups and failures.
 * @return void
 */
function write_trials(string $directory, array $rows): void {
    write_json($directory . '/runs.json', $rows);
    $headers = [
        'experiment',
        'run',
        'block',
        'warmup',
        'method',
        'order',
        'source_category_id',
        'status',
        'eligible_for_performance_summary',
        'verification_pass',
        'source_unchanged',
        'scope_warnings_count',
        'destination_root_category_id',
        'container_category_id',
        'copy_workflow_seconds',
        'readiness_workflow_seconds',
        'prepare_wall_seconds',
        'copy_wall_seconds',
        'postprocess_wall_seconds',
        'verification_wall_seconds',
        'cleanup_wall_seconds',
        'cpu_user_seconds',
        'cpu_system_seconds',
        'os_peak_rss_kib',
        'php_peak_allocated_bytes',
        'db_queries_after_bootstrap',
        'db_reads_after_bootstrap',
        'db_writes_after_bootstrap',
        'io_read_bytes_after_bootstrap',
        'io_write_bytes_after_bootstrap',
        'io_logical_rchar_after_bootstrap',
        'io_logical_wchar_after_bootstrap',
        'source_categories',
        'source_courses',
        'source_sections',
        'source_modules',
        'source_file_references',
        'source_logical_file_bytes',
        'source_unique_file_bytes',
        'source_maximum_relative_depth',
        'copy_tasks',
        'postprocessing_tasks',
        'cleanup_status',
        'failure_stage',
        'failure_reason',
    ];
    $tmp = $directory . '/runs.csv.tmp';
    if (is_file($tmp)) {
        unlink($tmp);
    }
    foreach ($rows as $row) {
        append_csv($tmp, $row, $headers);
    }
    if (!rename($tmp, $directory . '/runs.csv')) {
        throw new \RuntimeException('Run table replacement failed.');
    }
}

/**
 * Summarise eligible paired observations without pooling revisions.
 *
 * @param string $dir Absolute private run or output directory.
 * @param array $rows All trial records, including warmups and failures.
 * @param int $seed Deterministic ordering or bootstrap seed.
 * @return void
 */
function summarize(string $dir, array $rows, int $seed): void {
    $metrics = [
        'readiness_workflow_seconds',
        'copy_workflow_seconds',
        'cpu_user_seconds',
        'cpu_system_seconds',
        'os_peak_rss_kib',
        'php_peak_allocated_bytes',
        'db_queries_after_bootstrap',
        'db_reads_after_bootstrap',
        'db_writes_after_bootstrap',
        'io_read_bytes_after_bootstrap',
        'io_write_bytes_after_bootstrap',
    ];
    $summary = [];
    $included = [];
    $excluded = [];
    foreach ($rows as $r) {
        if (
            !$r['warmup'] && !empty($r['eligible_for_performance_summary'])
            && !empty($r['verification_pass'])
            && ($r['status'] !== 'failed' || ($r['failure_stage'] ?? '') === 'cleanup')
        ) {
            $included[] = $r;
        } else {
            $excluded[] = [
                'run' => $r['run'],
                'warmup' => $r['warmup'],
                'status' => $r['status'],
                'reason' => $r['failure_reason'] ?? (!empty($r['scope_warnings_count']) ? 'scope warnings'
                    : 'warmup or ineligible'),
            ];
        }
    }
    foreach (METHODS as $method) {
        foreach ($metrics as $metric) {
            $values = [];
            foreach ($included as $r) {
                if ($r['method'] === $method && isset($r[$metric])) {
                    $values[] = $r[$metric];
                }
            }
            $x = ['method' => $method, 'metric' => $metric] + describe($values);
            $summary[] = $x;
            append_csv($dir . '/summary.csv', $x);
        }
    }
    $comparisons = [];
    foreach ($metrics as $i => $metric) {
        $a = [];
        $b = [];
        foreach ($included as $r) {
            if (isset($r[$metric])) {
                if ($r['method'] === 'native') {
                    $a[$r['block']] = $r[$metric];
                } else {
                    $b[$r['block']] = $r[$metric];
                }
            }
        }
        $pair = ['metric' => $metric] + paired_ratio($a, $b, $seed + 1 + $i);
        $comparisons[] = $pair;
        append_csv($dir . '/paired_comparisons.csv', $pair);
    }
    write_json($dir . '/summary.json', [
        'revision' => REVISION,
        'included_runs' => count($included),
        'excluded_runs' => $excluded,
        'operational_failed_runs' => count(array_filter($rows, fn($r) => $r['status'] === 'failed')),
        'descriptive' => $summary,
        'paired_comparisons' => $comparisons,
        'interpretation' => 'Ratios >1 mean native metric is larger; lower resource/time is not a univer'
            . 'sal product winner. n<2 intervals are unavailable. All intervals are explor'
            . 'atory within-session, not cross-site or publisher certification.',
        'scope' => 'Only verified source-stable supported-design trials are included. An AFTER-'
            . 'endpoint cleanup failure is reported separately and does not erase an alrea'
            . 'dy verified readiness measurement. All failures and warmups remain in raw C'
            . 'SV/JSON.',
    ]);
}

/**
 * Hash experiment evidence files for subsequent independent verification.
 *
 * @param string $directory Private experiment directory.
 * @return void
 */
function manifest(string $directory): void {
    $out = [];
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $f) {
        if (
            $f->isFile() && $f->getFilename() !== 'manifest.sha256.json'
            && !str_ends_with($f->getFilename(), '.tmp')
        ) {
            $out[substr($f->getPathname(), strlen($directory) + 1)] = ['sha256' => hash_file(
                'sha256',
                $f->getPathname()
            ), 'bytes' => $f->getSize()];
        }
    }
    ksort($out);
    write_json($directory . '/manifest.sha256.json', $out);
}
