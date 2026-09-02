# Clone Category (local_clonecategory)

[![Moodle Plugin CI](https://github.com/saddamalsalfi/moodle-local_clonecategory/actions/workflows/ci.yml/badge.svg)](https://github.com/saddamalsalfi/moodle-local_clonecategory/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Moodle Version](https://img.shields.io/badge/Moodle-4.0%20--%205.2%2B-orange.svg)](https://moodle.org)

A powerful, native Moodle local plugin that enables site administrators and course managers to seamlessly clone entire course categories—including all subcategories, courses, and course activities—with advanced progress tracking, pause/resume capability, total rollback support, and strict concurrency controls.

---

## 🌟 Key Features

* **📁 Deep Recursive Cloning**: Duplicates entire category hierarchies, subcategories, and courses while automatically preserving layout and resetting user data/enrollments.
* **🛡️ Concurrency Control & Active Job Locking**: Prevents overlapping or simultaneous cloning tasks to eliminate database conflicts and server resource exhaustion.
* **⏸️ ▶️ Pause & Resume Operations**: Pause an active or queued cloning job at any time and resume it from the exact item where it stopped without duplicating already created content.
* **🔄 🗑️ Complete Rollback & Undo System**: Total one-click undo mechanism that cleanly removes all created categories, subcategories, and courses from Moodle if a job needs to be cancelled.
* **📊 Real-time Progress & Statistics**: Dynamic progress bar (0–100%), real-time item counter (categories and courses copied), and live step description.
* **⚡ Background Processing**: Leverages Moodle’s native ad-hoc task queue (`\core\task\manager`) to prevent PHP HTTP timeouts on large categories.
* **🚀 Native Force Run Engine**: Execute scheduled ad-hoc tasks directly from the web interface safely—no CLI `exec()` or server shell access required.
* **🔒 Privacy API & Compliance**: Fully compliant with Moodle Privacy API (`core_privacy`), declaring metadata for auditing records.
* **🌐 Multilingual (i18n)**: Out-of-the-box support for **English** and **Arabic** with 100% `get_string()` coverage.

---

## 📋 Requirements

* **Moodle**: 4.0 or later (Tested up to Moodle 5.2+).
* **PHP**: 8.1, 8.2, or 8.3.

---

## 🛠️ Installation

1. Download or clone the repository:
   ```bash
   git clone https://github.com/saddamalsalfi/moodle-local_clonecategory.git clonecategory
   ```
2. Place the `clonecategory` directory into your Moodle's `local/` folder:
   ```text
   moodle_root/local/clonecategory
   ```
3. Log into your Moodle site as an Administrator.
4. Go to **Site administration > Notifications** to trigger the plugin database installation/upgrade.

---

## 📖 How to Use

1. Navigate to **Site administration > Courses > Clone Category** (or click **Clone Category** from any course category actions menu).
2. Select the **Source Category** you wish to copy.
3. Select the **Target Parent Category** (choose *Top* for root level).
4. *(Optional)* Enter custom suffixes for cloned category and course names (e.g., ` - Copy` or ` - Fall 2026`).
5. Click **Start Cloning**.
6. Switch to the **Scheduled Tasks & Operations** tab to view real-time progress, pause/resume execution, view detailed history, or perform a total rollback.

---

## 🛡️ Privacy & Security

* **Privacy API**: Implements `\core_privacy\local\metadata\provider` to document audit metadata stored in `local_clonecategory_jobs`.
* **Capability Controls**: Access is strictly limited to users with `moodle/category:manage` capability.
* **CSRF Protection**: All action triggers are protected via Moodle `sesskey` validation.

---

## 🧪 Automated Testing (CI)

This repository includes continuous integration workflows using `moodle-plugin-ci` via GitHub Actions to validate code quality, Moodle PHPDoc standards, PHP syntax linting, and compatibility across supported PHP and Moodle versions.

---

## 📄 License

This plugin is free software: licensed under the terms of the [GNU General Public License v3](LICENSE) as published by the Free Software Foundation.

**Author**: Saddam Al-Salfi
