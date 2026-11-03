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
 * Standalone regression tests for the benchmark without Moodle bootstrap.
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
    fwrite(STDERR, 'Benchmark tests require PHP 8.1+' . PHP_EOL);
    exit(1);
}
require(__DIR__ . '/../cli/scientific_benchmark_v2/library.php');
use local_clonecategory\benchmark_v2 as b;
$tests = 0;
/**
 * Assert one standalone regression condition and increment its count.
 *
 * @param mixed $v Regression condition result.
 * @param string $name Regression condition label.
 * @return void
 */
function check($v, string $name): void {
    global $tests;
    if (!$v) {
        throw new RuntimeException('FAILED: ' . $name);
    }
    $tests++;
}
for ($n = 1; $n <= 9; $n++) {
    $s = b\schedule($n, 1, 123);
    check($s === b\schedule($n, 1, 123), 'deterministic schedule');
    $measured = array_values(array_filter($s, fn($r) => !$r['warmup']));
    check(count($measured) === 2 * $n, 'both methods per block');
    $first = [];
    foreach ($measured as $r) {
        if ($r['method'] === $r['order'][0]) {
            $first[] = $r['method'];
        }
    }
    check(abs(count(array_filter($first, fn($m) => $m === 'native')) - count(array_filter($first, fn($m) => $m
        === 'clonecategory'))) <= 1, 'balanced order');
}
check(b\describe([])['mean'] === null, 'missing values not zero');
check(b\describe([2])['sd'] === null, 'one observation has no sample SD');
check(b\quantile([
    1,
    2,
    3,
    4,
], 0.5) === 2.5, 'median');
check(abs(b\describe([
    1,
    2,
    3,
    4,
])['sd'] - sqrt(5 / 3)) < 1.0E-12, 'sample SD');
$r = b\paired_ratio([0 => 2.0], [0 => 1.0], 1);
check($r['paired_blocks'] === 1 && $r['exploratory_ratio_95_low'] === null, 'no invented CI for n1');
$r = b\paired_ratio([0 => 2.0, 1 => 4.0], [0 => 1.0, 1 => 2.0], 1);
check(abs($r['native_over_clone_geometric_ratio'] - 2) < 1.0E-12
    && abs($r['exploratory_ratio_95_low'] - 2) < 1.0E-12, 'paired bootstrap direction');
$z = b\paired_ratio([0 => 0.0, 1 => 2.0], [0 => 1.0, 1 => 1.0], 1);
check($z['paired_blocks'] === 2 && $z['native_over_clone_geometric_ratio'] === null, 'zero pairs are not silently dropped');
check(abs($z['mean_paired_difference_native_minus_clone']) < 1.0E-12, 'zero-valued measurements retain paired difference');
check(b\hash_data(['b' => 2, 'a' => 1]) === b\hash_data(['a' => 1, 'b' => 2]), 'canonical map order');
check(b\hash_data([1, 2]) !== b\hash_data([2, 1]), 'list order matters');
foreach (
    [
    ['--mode=baseline'],
    ['--pipeline=optimized'],
    ['--runs=0'],
    ['--seed=99999999999999'],
    ['--execute=true'],
    ] as $bad
) {
    $caught = false;
    try {
        b\options(array_merge(['x.php'], $bad));
    } catch (InvalidArgumentException $e) {
        $caught = true;
    }
    check($caught, 'deprecated/invalid option rejected');
}
check(b\options(['x.php'])['execute'] === false, 'default is plan');
check(b\options(['x.php', '--execute', '--retain=0'])['execute'] === true, 'explicit execute');
$file = [
    'owner' => 'section:0/module:0/resource',
    'component' => 'mod_resource',
    'filearea' => 'content',
    'filepath' => '/',
    'filename' => 'sample.pdf',
    'filesize' => '3',
    'contenthash' => sha1('PDF'),
    'sha256' => hash('sha256', 'PDF'),
    'author' => 'Fixture',
    'license' => 'cc',
    'mimetype' => 'application/pdf',
    'primary_design_file' => true,
];
$source = [
    'record' => [
        'id' => '1',
        'category' => '2',
        'fullname' => 'Example',
        'shortname' => 'EX',
        'summary' => 'Design',
        'summaryformat' => '1',
        'format' => 'topics',
        'visible' => '1',
        'timecreated' => '100',
        'cacherev' => '123',
    ],
    'sections' => [
        [
            'record' => [
                'id' => '11',
                'course' => '1',
                'section' => '0',
                'name' => null,
                'summary' => '',
                'summaryformat' => '1',
                'sequence' => '21',
                'visible' => '1',
                'timemodified' => '100',
            ],
            'modules' => [
                [
                    'type' => 'resource',
                    'cm' => [
                        'id' => '21',
                        'course' => '1',
                        'instance' => '31',
                        'section' => '11',
                        'visible' => '1',
                        'added' => '100',
                    ],
                    'instance' => [
                        'id' => '31',
                        'course' => '1',
                        'name' => 'PDF',
                        'intro' => '',
                        'introformat' => '1',
                        'revision' => '1',
                        'timemodified' => '100',
                    ],
                    'position' => 0,
                ],
            ],
        ],
    ],
    'files' => [$file],
];
$dest = $source;
$dest['record']['id'] = '101';
$dest['record']['category'] = '102';
$dest['record']['fullname'] = 'Example [copy]';
$dest['record']['shortname'] = 'COPY';
$dest['record']['visible'] = '0';
$dest['record']['timecreated'] = '200';
$dest['record']['cacherev'] = '456';
$dest['sections'][0]['record']['id'] = '111';
$dest['sections'][0]['record']['course'] = '101';
$dest['sections'][0]['record']['sequence'] = '121';
$dest['sections'][0]['record']['timemodified'] = '200';
$dest['sections'][0]['modules'][0]['cm']['id'] = '121';
$dest['sections'][0]['modules'][0]['cm']['course'] = '101';
$dest['sections'][0]['modules'][0]['cm']['instance'] = '131';
$dest['sections'][0]['modules'][0]['cm']['section'] = '111';
$dest['sections'][0]['modules'][0]['cm']['added'] = '200';
$dest['sections'][0]['modules'][0]['instance']['id'] = '131';
$dest['sections'][0]['modules'][0]['instance']['course'] = '101';
$dest['sections'][0]['modules'][0]['instance']['timemodified'] = '200';
check(b\hash_data(b\normalized_course($source)) === b\hash_data(b\normalized_course($dest)), 'expected identity changes normalize');
$dir = sys_get_temp_dir() . '/clonecategory_benchmark_tests_' . bin2hex(random_bytes(6));
mkdir($dir, 0700, true);
b\write_json($dir . '/BASELINE.json', $dest);
$dest['files'][0]['sha256'] = str_repeat('0', 64);
b\write_json($dir . '/MODIFIED.json', $dest);
check(b\hash_data(b\normalized_course($source)) !== b\hash_data(b\normalized_course($dest)), 'modified file digest detected');
b\write_json($dir . '/ROLLBACK_COPY.json', $dest);
copy($dir . '/BASELINE.json', $dir . '/ROLLBACK_COPY.json');
$restored = b\read_json($dir . '/ROLLBACK_COPY.json');
check(
    b\hash_data(b\normalized_course($source)) === b\hash_data(b\normalized_course($restored)),
    'rollback on separate copy restores behavior'
);
check(b\read_json($dir . '/MODIFIED.json')['files'][0]['sha256'] === str_repeat('0', 64), 'modified fixture retained');
$dest = $restored;
$dest['sections'][0]['modules'][0]['instance']['name'] = 'Changed';
check(b\hash_data(b\normalized_course($source)) !== b\hash_data(b\normalized_course($dest)), 'activity design change detected');
check(
    !b\design_file(['component' => 'mod_forum', 'filearea' => 'attachment']),
    'learner attachments explicitly outside design scope'
);
check(b\design_file($file), 'resource files included');
b\append_csv($dir . '/quoted.csv', ['x' => "comma,quote\"\nsecond line", 'n' => null]);
$f = fopen($dir . '/quoted.csv', 'rb');
fgetcsv($f, null, ',', '"', '');
$row = fgetcsv($f, null, ',', '"', '');
fclose($f);
check($row[0] === "comma,quote\"\nsecond line" && $row[1] === '', 'CSV roundtrip multiline and missing');
echo 'FIXTURES: ' . $dir . PHP_EOL;
echo 'UNIT PASS: ' . $tests . " assertions; baseline/modified/rollback copy verified\n";
