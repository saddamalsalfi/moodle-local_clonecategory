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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Pure helper tests for the production-path benchmark protocol.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_clonecategory;

use local_clonecategory\benchmark_v2 as benchmark;

#[\PHPUnit\Framework\Attributes\CoversNothing]
/**
 * No course, task or database mutation is required by these helper tests.
 *
 * @coversNothing
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scientific_benchmark_test extends \basic_testcase {
    /**
     * Set up the optional PHP 8.1+ CLI helper library.
     */
    protected function setUp(): void {
        parent::setUp();
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Optional benchmark requires PHP 8.1 or later.');
        }
        require_once(__DIR__ . '/../cli/scientific_benchmark_v2/library.php');
    }

    /**
     * A fixed seed preserves paired membership and counterbalances first method.
     */
    public function test_schedule_is_deterministic_and_balanced(): void {
        $schedule = benchmark\schedule(6, 1, 20261028);
        $this->assertSame($schedule, benchmark\schedule(6, 1, 20261028));
        $this->assertCount(14, $schedule);
        $measured = array_values(array_filter($schedule, fn($row) => !$row['warmup']));
        $this->assertCount(12, $measured);
        $first = array_filter($measured, fn($row) => $row['method'] === $row['order'][0]);
        $this->assertCount(3, array_filter($first, fn($row) => $row['method'] === 'native'));
        $this->assertCount(3, array_filter($first, fn($row) => $row['method'] === 'clonecategory'));
    }

    /**
     * A single paired block does not manufacture a confidence interval.
     */
    public function test_single_pair_and_zero_values_are_explicit(): void {
        $single = benchmark\paired_ratio([2.0], [1.0], 1);
        $this->assertNull($single['exploratory_ratio_95_low']);
        $this->assertSame(1, $single['paired_blocks']);
        $zeros = benchmark\paired_ratio([0.0, 2.0], [1.0, 1.0], 1);
        $this->assertSame(2, $zeros['paired_blocks']);
        $this->assertNull($zeros['native_over_clone_geometric_ratio']);
        $this->assertEquals(0, $zeros['mean_paired_difference_native_minus_clone']);
    }

    /**
     * Missing measurements remain missing rather than becoming observed zeros.
     */
    public function test_descriptive_statistics_handle_small_samples(): void {
        $this->assertNull(benchmark\describe([])['mean']);
        $this->assertNull(benchmark\describe([1])['sd']);
        $this->assertEquals(2.5, benchmark\quantile([1, 2, 3, 4], 0.5));
    }

    /**
     * The default command cannot silently become an execution request.
     */
    public function test_default_is_plan_and_removed_options_are_rejected(): void {
        $this->assertFalse(benchmark\options(['runner'])['execute']);
        $this->expectException(\InvalidArgumentException::class);
        benchmark\options(['runner', '--pipeline=optimized']);
    }

    /**
     * Canonical hashing preserves list order and detects actual field changes.
     */
    public function test_canonical_hashing_detects_changes(): void {
        $this->assertSame(benchmark\hash_data(['a' => 1, 'b' => 2]), benchmark\hash_data(['b' => 2, 'a' => 1]));
        $this->assertNotSame(benchmark\hash_data([1, 2]), benchmark\hash_data([2, 1]));
        $this->assertNotSame(benchmark\hash_data(['sha256' => 'original']), benchmark\hash_data(['sha256' => 'changed']));
    }
}
