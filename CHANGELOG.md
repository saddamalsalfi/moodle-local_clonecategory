# Changelog

## 1.3.1 — 2026-10-07

Stable release; Moodle plugin build `2026100705`.

### Added

- Three copy scopes: categories only; categories and empty courses/settings; full course content without learner data.
- Collapsible, searchable category selectors with one source checkbox selection and one destination radio selection.
- Responsive/RTL interface, translated English/Arabic guidance and live job progress.
- Scoped capabilities, background ad hoc tasks, safe pause/cancel/retry, shared locks and tracked partial restores.
- Complete audit-data Privacy API handlers, integration tests and CI/release automation.

### Fixed

- False rollback rejection caused by deferred course-section and enrol-instance creation logs appearing after the original fingerprint was recorded.
- Safe verification of affected settings-only preview jobs against their original fingerprints without accepting modified resources.
- Category labels repeating all ancestors and overflowing small screens.
- Incomplete rollback accidentally becoming eligible to resume cloning.
- Queue/task idempotence, raw error disclosure and unsafe cleanup of changed partial copies.

### Changed

- Rollback preflights owned resources and applies latest-job/24-hour limits, capability checks and change detection.
- Stable metadata advances the preview build `2026100704` without additional schema changes.
- Missing historical fingerprints remain unavailable; audit deletion preserves the copied resources.

See [release notes](RELEASE_NOTES.md) for installation, evidence, development details and compatibility limits.

## 1.3.1-beta — 2026-10-07

Preview build `2026100704`: corrected buffered-log fingerprints and added strict verification for affected settings-only jobs. Passed 27 tests/90 assertions per executed Moodle environment.

## 1.3.0-beta — 2026-10-07

Preview build `2026100703`: introduced copy scopes, responsive tree selectors, background-job safeguards and Privacy API improvements.

## 1.1.2 — earlier release

Baseline source tag used for this development: `810ed1b9cd26a4bb83ab9afc2c1e624c83546089`. Includes the XMLDB status-index correction from review issue #7.
