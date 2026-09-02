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
$string['privacy:metadata'] = 'إضافة استنساخ الفئات تقوم بتخزين سجلات عمليات الاستنساخ بما فيها معرف المستخدم لغايات التدقيق.';
$string['privacy:metadata:local_clonecategory_jobs'] = 'تخزن تفاصيل وحالة عمليات استنساخ الفئات والمقررات.';
$string['privacy:metadata:local_clonecategory_jobs:userid'] = 'معرف المستخدم الذي قام بعملية الاستنساخ.';
$string['privacy:metadata:local_clonecategory_jobs:sourcecategoryid'] = 'معرف الفئة المصدر المستنسخة.';
$string['privacy:metadata:local_clonecategory_jobs:targetparentid'] = 'معرف الفئة الهدف.';
$string['privacy:metadata:local_clonecategory_jobs:timecreated'] = 'الطابع الزمني لوقت إنشاء العملية.';

$string['task:clone_category_task'] = 'مهمة استنساخ الفئة';
$string['clone_page_title'] = 'استنساخ التصنيفات(المساقات) والمقررات';
$string['categorysuffix'] = 'لاحقة اسم الفئة';
$string['categorysuffix_help'] = 'النص الذي سيتم إضافته إلى اسم الفئة المستنسخة. اتركه فارغاً للنسخ بنفس الاسم.';
$string['coursesuffix'] = 'لاحقة اسم المقرر';
$string['coursesuffix_help'] = 'النص الذي سيتم إضافته إلى اسم المقرر المستنسخ. اتركه فارغاً للنسخ بنفس الاسم.';
$string['tasks_started'] = 'تم إطلاق أمر تشغيل المهام في الخلفية بنجاح.';
$string['scheduled_tasks'] = 'المهام والعمليات المجدولة';
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

$string['active_job_exists'] = 'هناك عملية استنساخ نشطة حالياً. يرجى الانتظار أو إيقافها/التراجع عنها قبل بدء عملية جديدة.';
$string['active_job_warning'] = 'تنبيه: يوجد عملية استنساخ نشطة أو متوقفة حالياً. تم تعطيل بدء استنساخ جديد حتى تكتمل العملية أو يتم إلغاؤها.';
$string['active_job_title'] = 'عملية الاستنساخ النشطة رقم {$a}';
$string['progress'] = 'نسبة الإنجاز';
$string['categories_copied'] = 'الفئات المستنسخة';
$string['courses_copied'] = 'المقررات المستنسخة';
$string['current_step'] = 'الخطوة الحالية';
$string['btn_pause'] = 'إيقاف مؤقت';
$string['btn_resume'] = 'استئناف';
$string['btn_rollback'] = 'إلغاء والتراجع الكلي';
$string['btn_delete'] = 'حذف السجل';
$string['rollback_confirm'] = 'هل أنت تأكد من أنك تريد إلغاء عملية الاستنساخ والتراجع عنها؟ سيتم حذف جميع الفئات والمقررات التي تم إنشاؤها في هذه العملية بشكل دائم.';
$string['job_queued'] = 'تمت جدولة العملية في الخلفية';
$string['job_running'] = 'جاري استنساخ الفئات والمقررات...';
$string['job_paused_by_user'] = 'تم الإيقاف المؤقت بواسطة المستخدم';
$string['job_resumed'] = 'جاري استئناف عملية الاستنساخ...';
$string['job_rolling_back'] = 'جاري التراجع وحذف الفئات والمقررات المستنسخة...';
$string['job_rolled_back_success'] = 'تم التراجع عن عملية الاستنساخ وتصفيتها بالكامل.';
$string['job_completed_success'] = 'اكتملت عملية الاستنساخ بنجاح.';
$string['job_failed_error'] = 'فشلت عملية الاستنساخ: {$a}';
$string['job_cloned_item'] = 'تم استنساخ {$a->type}: {$a->name}';
$string['status_running'] = 'قيد التنفيذ';
$string['status_paused'] = 'متوقفة مؤقتاً';
$string['status_pending'] = 'قيد الانتظار';
$string['status_completed'] = 'مكتملة';
$string['status_failed'] = 'فشلت';
$string['status_rolled_back'] = 'تم التراجع';
$string['all_jobs_history'] = 'سجل وإحصائيات عمليات الاستنساخ';
$string['no_jobs_found'] = 'لا توجد عمليات استنساخ مسجلة بعد.';
$string['user'] = 'المستخدم';
$string['stats_categories'] = 'التصنيفات';
$string['stats_courses'] = 'المقررات';
$string['actions'] = 'الإجراءات';
$string['job_paused_success'] = 'تم إيقاف عملية الاستنساخ مؤقتاً بنجاح.';
$string['job_resumed_success'] = 'تم استئناف عملية الاستنساخ.';
$string['job_deleted_success'] = 'تم حذف سجل العملية.';
$string['error_lock_failed'] = 'تعذر الحصول على قفل القفل للعملية. يرجى المحاولة مرة أخرى.';
$string['error_rollback_not_latest'] = 'عذراً، لا يمكن التراجع إلا عن آخر عملية استنساخ تم إجراؤها فقط.';
$string['error_rollback_expired'] = 'عذراً، انقضت مهلة الـ 24 ساعة المتاحة للتراجع عن هذه العملية.';
