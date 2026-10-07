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
 * Authorised read-only polling endpoint for clone progress.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidrequest');
}
require_sesskey();
$jobs = \local_clonecategory\manager::get_visible_jobs();
\core\session\manager::write_close();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$result = [];
foreach ($jobs as $job) {
    $result[] = [
        'id' => (int)$job->id, 'status' => $job->status,
        'statuslabel' => get_string('status_' . $job->status, 'local_clonecategory'),
        'progress' => (int)$job->progress, 'currentstep' => $job->currentstep ?? '',
        'categoriescount' => (int)$job->categoriescount, 'totalcategories' => (int)$job->totalcategories,
        'coursescount' => (int)$job->coursescount, 'totalcourses' => (int)$job->totalcourses,
    ];
}
echo json_encode(['jobs' => $result]);
