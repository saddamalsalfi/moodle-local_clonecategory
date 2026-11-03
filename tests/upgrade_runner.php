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
 * Standalone metadata-only upgrade probe with no Moodle database bootstrap.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// phpcs:disable moodle.Files.MoodleInternal -- Isolated CLI source probe intentionally uses no Moodle bootstrap.
if (PHP_SAPI !== 'cli' || defined('MOODLE_INTERNAL')) {
    exit(1);
}
define('MOODLE_INTERNAL', true);
define('MATURITY_STABLE', 200);
$root = $argv[1] ?? dirname(__DIR__);
$expectedrelease = $argv[2] ?? '1.4.2';
$expectedversion = (int)($argv[3] ?? 2026102900);
$plugin = new stdClass();
require($root . '/version.php');
if ($plugin->release !== $expectedrelease || $plugin->version !== $expectedversion) {
    throw new RuntimeException('Unexpected source metadata.');
}
$savepoints = [];
/**
 * Capture a metadata savepoint without loading Moodle or a database.
 *
 * @param bool $result Savepoint result.
 * @param int $version Expected upgrade version.
 * @param string $type Plugin type.
 * @param string $component Plugin component.
 * @return void
 */
function upgrade_plugin_savepoint($result, $version, $type, $component) {
    global $savepoints;
    if (!$result || $type !== 'local' || $component !== 'clonecategory') {
        throw new RuntimeException('Invalid savepoint.');
    }
    $savepoints[] = $version;
}
/**
 * Isolated database sentinel with no data-writing or schema-changing methods.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Slfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_clonecategory_upgrade_probe_database {
    /**
     * Return a manager with no DDL methods so any schema mutation fails.
     *
     * @return \stdClass
     */
    public function get_manager() {
        // Any schema call on this object fails, exposing accidental DDL.
        return new stdClass();
    }
}
$DB = new local_clonecategory_upgrade_probe_database();
require($root . '/db/upgrade.php');
if (xmldb_local_clonecategory_upgrade(2026102600) !== true) {
    throw new RuntimeException('Upgrade did not return true.');
}
$expected = $expectedversion > 2026102600 ? [$expectedversion] : [];
if ($savepoints !== $expected) {
    throw new RuntimeException('Unexpected savepoint sequence or data path.');
}
$savepoints = [];
xmldb_local_clonecategory_upgrade($expectedversion);
if ($savepoints) {
    throw new RuntimeException('Current-version upgrade is not a no-op.');
}
echo 'UPGRADE PASS: ' . $expectedrelease . ' / ' . $expectedversion
    . '; no schema/content writes; current version is no-op' . PHP_EOL;
