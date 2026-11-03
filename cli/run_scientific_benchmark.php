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
 * Production-path process-isolated benchmark entry point.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.MoodleInternal -- CLI-only guard precedes dynamic bootstrap or pure tests.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}
if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "This benchmark requires PHP 8.1+; no unmeasured compatibility fallback is used.\n");
    exit(1);
}
$processstart = hrtime(true);
$cpustart = getrusage();
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/scientific_benchmark_v2/library.php');
use local_clonecategory\benchmark_v2 as bench;
try {
    $options = bench\options($argv);
    if ($options['help']) {
        echo bench\help_text(basename(__FILE__));
        exit(0);
    }
    $config = null;
    foreach ([__DIR__ . '/../../../../config.php', __DIR__ . '/../../../config.php'] as $candidate) {
        if (is_file($candidate)) {
            $config = realpath($candidate);
            break;
        }
    }
    if ($config === null) {
        throw new RuntimeException('Moodle config.php was not found relative to this plugin CLI.');
    }
    require($config);
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
    require_once($CFG->dirroot . '/backup/util/helper/copy_helper.class.php');
    \core\session\manager::set_user(get_admin());
    if ($options['phase'] !== '') {
        bench\worker($options, $processstart, $cpustart);
        exit(0);
    }
    if ($options['cleanup-run'] !== '') {
        if (!$options['execute']) {
            throw new RuntimeException('--cleanup-run requires --execute; no object was deleted.');
        }
        $runpath = realpath($options['cleanup-run']);
        if ($runpath === false) {
            throw new RuntimeException('Recorded run directory does not exist.');
        }
        $experimentpath = dirname(dirname($runpath));
        $runname = basename($runpath);
        bench\run_child(__FILE__, $experimentpath, $runname, 'cleanup', $options['timeout']);
        $rows = bench\read_json($experimentpath . '/runs.json');
        foreach ($rows as &$row) {
            if ($row['run'] === $runname) {
                $row['cleanup_status'] = 'manual_owned_core_cleanup_passed';
                $row['status'] = 'verified_cleaned';
            }
        }
        unset($row);
        bench\write_trials($experimentpath, $rows);
        bench\append_csv($experimentpath . '/cleanup_actions.csv', [
            'run' => $runname,
            'action' => 'manual_owned_core_cleanup',
            'utc' => gmdate('c'),
            'exit_status' => 0,
        ]);
        bench\manifest($experimentpath);
        echo 'RECORDED OWNED CLEANUP COMPLETE: ' . $runname . PHP_EOL;
        exit(0);
    }
    bench\preflight((int) $options['source-category']);
    $parent = $CFG->dataroot . '/local_clonecategory/scientific_benchmark_v2';
    if (!is_dir($parent) && !mkdir($parent, 0700, true)) {
        throw new RuntimeException('Private benchmark directory creation failed.');
    }
    $lock = fopen($parent . '/suite.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another benchmark controller is active.');
    }
    $experiment = gmdate('Ymd_His') . '_' . bin2hex(random_bytes(5));
    $directory = $options['output-dir'] !== '' ? $options['output-dir'] : $parent . '/' . $experiment;
    if (is_dir($directory) && count(scandir($directory)) > 2) {
        throw new RuntimeException('Output directory is not empty; existing evidence will not be overwritten.');
    }
    bench\private_directory($directory);
    if (is_file($directory . '/protocol.json')) {
        throw new RuntimeException('Output directory already contains an experiment; no append or duplicate submission.');
    }
    $directory = realpath($directory);
    $baseline = bench\capture((int) $options['source-category']);
    bench\write_json($directory . '/baseline.json', $baseline);
    $protocol = bench\protocol($experiment, $options, $baseline, __FILE__);
    bench\write_json($directory . '/protocol.json', $protocol);
    bench\write_json($directory . '/environment_before.json', bench\environment(__FILE__));
    echo 'OUTPUT: ' . $directory . PHP_EOL;
    echo 'SOURCE: ' . json_encode($baseline['totals'], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    echo 'PROTOCOL SHA256: ' . hash_file('sha256', $directory . '/protocol.json') . PHP_EOL;
    echo 'PLANNED: ' . count($protocol['schedule']) . ' trials; '
        . count($protocol['schedule']) * $baseline['totals']['courses']
        . ' course copies, including warm-ups.'
        . PHP_EOL;
    if (!$options['execute']) {
        echo 'PLAN ONLY: no category, course or task was created. Use --execute with a NE'
            . 'W output directory.'
            . PHP_EOL;
        exit(0);
    }
    $trialrows = [];
    $failed = false;
    foreach ($protocol['schedule'] as $item) {
        $run = $experiment . '_' . $item['token'];
        $row = [
            'experiment' => $experiment,
            'run' => $run,
            'block' => $item['block'],
            'warmup' => $item['warmup'],
            'method' => $item['method'],
            'order' => implode(',', $item['order']),
            'source_category_id' => (int) $options['source-category'],
            'status' => 'started',
            'eligible_for_performance_summary' => false,
        ];
        $stage = 'prepare';
        try {
            // Inventory/hashing is outside the copy/readiness endpoint for BOTH methods.
            bench\run_child(__FILE__, $directory, $run, 'sourcecheck', $options['timeout']);
            $start = hrtime(true);
            bench\run_child(__FILE__, $directory, $run, 'prepare', $options['timeout']);
            $stage = 'copy';
            bench\run_child(__FILE__, $directory, $run, 'copy', $options['timeout']);
            $row['copy_workflow_seconds'] = (hrtime(true) - $start) / 1000000000.0;
            $stage = 'postprocess';
            bench\run_child(__FILE__, $directory, $run, 'postprocess', $options['timeout']);
            $row['readiness_workflow_seconds'] = (hrtime(true) - $start) / 1000000000.0;
            $stage = 'verify';
            bench\run_child(__FILE__, $directory, $run, 'verify', $options['timeout']);
            $state = bench\read_json($directory . '/runs/' . $run . '/state.json');
            $row = array_merge($row, bench\trial_metrics($directory, $run, $baseline));
            $row['destination_root_category_id'] = $state['categories'][(string) $options['source-category']];
            $row['container_category_id'] = $state['container'];
            $row['verification_pass'] = $state['verification']['pass'];
            $row['scope_warnings_count'] = count($baseline['scope_warnings']);
            $row['eligible_for_performance_summary'] = empty($baseline['scope_warnings']);
            $row['status'] = 'verified';
            if (!$options['retain']) {
                $stage = 'cleanup';
                bench\run_child(__FILE__, $directory, $run, 'cleanup', $options['timeout']);
                $row['cleanup_status'] = 'owned_core_cleanup_passed';
                $row['cleanup_wall_seconds'] = bench\read_json($directory . '/runs/' . $run
                    . '/cleanup.process.json')['wall_seconds'];
            } else {
                $row['cleanup_status'] = 'retained_by_request';
            }
            $row['status'] = $options['retain'] ? 'verified_retained' : 'verified_cleaned';
        } catch (Throwable $e) {
            $row['status'] = 'failed';
            $row['failure_stage'] = $stage;
            $row['failure_reason'] = $e->getMessage();
            $failed = true;
            bench\append_csv($directory . '/failures.csv', [
                'run' => $run,
                'method' => $item['method'],
                'stage' => $stage,
                'reason' => $e->getMessage(),
                'utc' => gmdate('c'),
            ]);
        }
        $trialrows[] = $row;
        bench\write_trials($directory, $trialrows);
        echo json_encode($row, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if ($failed) {
            echo 'STOPPED: failed run retained; no automatic retry or error-row suppression.' . PHP_EOL;
            break;
        }
    }
    bench\summarize($directory, $trialrows, (int) $options['seed']);
    // The controller connection may exceed the host's idle timeout while children work.
    // Reopen only outside every measured phase; never retry a copy operation.
    $DB->dispose();
    unset($DB);
    setup_DB();
    bench\write_json($directory . '/environment_after.json', bench\environment(__FILE__));
    bench\write_json($directory . '/completion.json', [
        'utc' => gmdate('c'),
        'failed' => $failed,
        'trials_recorded' => count($trialrows),
        'script_sha256' => hash_file('sha256', __FILE__),
        'protocol_sha256' => hash_file('sha256', $directory . '/protocol.json'),
    ]);
    bench\manifest($directory);
    echo 'RESULTS: ' . $directory . '/runs.csv' . PHP_EOL;
    echo 'COMPARISON: ' . $directory . '/paired_comparisons.csv' . PHP_EOL;
    exit($failed ? 2 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'BENCHMARK ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
