# Clone Category (local_clonecategory)

A powerful, native Moodle plugin that allows administrators and managers to seamlessly clone entire course categories, including their subcategories, courses, and course contents.

## Features
- **Deep Cloning**: Duplicates categories, subcategories, and courses sequentially.
- **Content Preservation**: Copies course contents (modules, sections) without including enrolled users or user events.
- **Custom Suffixes**: Allows you to dynamically specify custom text to append to the end of cloned categories and cloned courses (e.g., "- Copy", "- Fall 2026").
- **Background Processing**: Heavy cloning operations are queued as ad-hoc tasks, preventing browser timeouts and server overloads.
- **Task Management**: Built-in tabbed interface to monitor queued and completed cloning tasks.
- **Force Run Engine**: Instantly execute pending tasks synchronously from the UI using native Moodle APIs without needing to wait for system cron.
- **Cross-Platform**: Fully compatible with Linux, Windows, cPanel, and Docker environments. No `exec()` shell access required.

## Requirements
- Moodle 5.2.1 or later (Compatible with 4.0 up to 5.2.x).

## Installation
1. Extract the downloaded zip file.
2. Rename the extracted folder to `clonecategory` if it isn't already.
3. Place the folder into your Moodle's `local/` directory (`moodle_root/local/clonecategory`).
4. Log into Moodle as an administrator.
5. Go to **Site administration > Notifications** to trigger the plugin installation process.

## Usage
1. Navigate to **Site administration > Courses > Clone Category**.
2. Alternatively, access it via the settings gear icon in any existing Course Category.
3. Select your **Source Category** and your **Target Category (Parent)**.
4. (Optional) Provide custom suffixes for the cloned entities.
5. Click **Clone Category**.
6. Switch to the **Scheduled Tasks** tab to monitor the progress or instantly execute it.

## Compatibility
Developed according to strict Moodle Marketplace guidelines. Uses `\core\task\manager` for task handling, ensuring full compatibility with shared hosting and restricted PHP environments.

## License
Licensed under the GNU GPL v3 or later.
