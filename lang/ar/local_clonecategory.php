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
 * Arabic language strings for local_clonecategory.
 *
 * @package    local_clonecategory
 * @copyright  2026 Saddam Al-Salfi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'استنساخ الفئة';
$string['clonecategory'] = 'استنساخ الفئة';
$string['sourcecategory'] = 'الفئة المصدر';
$string['targetcategory'] = 'الفئة الهدف (الأب)';
$string['targetcategory_help'] = 'اختر الفئة التي سيتم وضع الفئة المستنسخة بداخلها. اختر "أعلى" لوضعها في المستوى الجذري.';
$string['clone_button'] = 'بدء الاستنساخ';
$string['cloningsuccess'] = 'تمت جدولة عملية استنساخ الفئة في الخلفية. ستكتمل العملية قريباً.';
$string['privacy:metadata'] = 'إضافة استنساخ الفئات لا تقوم بتخزين أي بيانات شخصية.';
$string['task:clone_category_task'] = 'مهمة استنساخ الفئة';
$string['clone_page_title'] = 'استنساخ التصنيفات(المساقات) والمقررات';
$string['categorysuffix'] = 'لاحقة اسم الفئة';
$string['categorysuffix_help'] = 'النص الذي سيتم إضافته إلى اسم الفئة المستنسخة. اتركه فارغاً للنسخ بنفس الاسم.';
$string['coursesuffix'] = 'لاحقة اسم المقرر';
$string['coursesuffix_help'] = 'النص الذي سيتم إضافته إلى اسم المقرر المستنسخ. اتركه فارغاً للنسخ بنفس الاسم.';
$string['tasks_started'] = 'تم إطلاق أمر تشغيل المهام في الخلفية بنجاح.';
$string['scheduled_tasks'] = 'المهام المجدولة';
$string['force_run_tasks'] = 'فرض التنفيذ الفوري للمهام';
$string['queued_tasks'] = 'مهام الاستنساخ قيد الانتظار';
$string['no_queued_tasks'] = 'لا توجد مهام استنساخ قيد الانتظار حالياً.';
$string['completed_tasks'] = 'مهام الاستنساخ السابقة';
$string['no_completed_tasks'] = 'لا توجد مهام استنساخ سابقة.';
$string['status'] = 'الحالة';
$string['time_started'] = 'وقت البدء';
$string['time_completed'] = 'وقت الانتهاء';
$string['id'] = 'المعرف';
$string['default_suffix'] = ' - نسخة';
$string['task_starting'] = "بدء تنفيذ المهمة في الخلفية...\n";
$string['task_executing'] = "جاري تنفيذ المهمة: {\$a->class} (المعرف: {\$a->id})...\n";
$string['task_completed_success'] = "-> اكتملت المهمة بنجاح.\n\n";
$string['task_failed'] = "-> فشلت المهمة: {\$a}\n";
$string['task_other_plugin'] = "تم العثور على مهمة تابعة لإضافة أخرى ({\$a}). تم إيقاف التنفيذ لتجنب التداخل.\n";
$string['no_pending_tasks'] = "لم يتم العثور على مهام استنساخ قيد الانتظار.\n";
$string['no_output_returned'] = 'لم يتم إرجاع أي مخرجات.';
$string['terminal_output'] = 'مخرجات التنفيذ المباشر';
$string['back_to_tasks'] = 'العودة إلى المهام المجدولة';
