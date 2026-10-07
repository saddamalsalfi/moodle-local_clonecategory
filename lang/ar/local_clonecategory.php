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
$string['error_lock_failed'] = 'العملية ما زالت مشغولة. انتظر توقف الإجراء الجاري ثم حاول مجدداً.';
$string['error_rollback_not_latest'] = 'عذراً، لا يمكن التراجع إلا عن آخر عملية استنساخ تم إجراؤها فقط.';
$string['error_rollback_expired'] = 'عذراً، انقضت مهلة الـ 24 ساعة المتاحة للتراجع عن هذه العملية.';

// Clone scope and responsive interface.
$string['clonemode'] = 'نطاق الاستنساخ';
$string['mode_categories'] = 'استنساخ التصنيفات فقط';
$string['mode_settings'] = 'استنساخ التصنيفات وأسماء المقررات وإعداداتها فقط';
$string['mode_full'] = 'استنساخ التصنيفات والمقررات والمحتويات (كامل)';
$string['clonemode_help'] = 'التصنيفات فقط: نسخ شجرة التصنيفات دون مقررات. الإعدادات فقط: إنشاء مقررات فارغة بأسمائها وإعداداتها العامة وخيارات تنسيقها، دون أنشطة أو ملفات أو ملخصات أو بيانات طلاب. الكامل: نسخ محتوى المقررات عبر النسخ والاستعادة في Moodle دون بيانات الطلاب. قد تحتاج إعدادات تنسيقات المقررات الخارجية إلى تحقق مستقل.';
$string['modenote'] = 'جميع الأوضاع تستبعد تسجيلات الطلاب ودرجاتهم وتسليماتهم.';
$string['scopeheading'] = '١. اختر ما تريد استنساخه';
$string['locationheading'] = '٢. حدد المصدر والوجهة';
$string['namingheading'] = '٣. حدد أسماء النسخ الجديدة';
$string['searchcategories'] = 'ابحث باسم التصنيف…';
$string['choosecategory'] = 'اختر تصنيفاً';
$string['invaliddestination'] = 'اختر وجهة صالحة خارج التصنيف المصدر وتصنيفاته الفرعية.';
$string['invalidclonemode'] = 'اختر نطاق استنساخ صالحاً.';
$string['suffixlength'] = 'يجب ألا تتجاوز اللاحقة ٢٥٥ حرفاً.';
$string['pageintro'] = 'جهّز هيكلاً أكاديمياً جديداً. اختر نطاق الاستنساخ وحدد التصنيفات ثم ابدأ العملية.';
$string['expandall'] = 'توسيع الكل';
$string['collapseall'] = 'طي الكل';
$string['nosearchresults'] = 'لا توجد تصنيفات مطابقة';
$string['singlesourcehint'] = 'اختر مصدراً واحداً فقط. اختيار مصدر آخر يلغي الاختيار السابق.';
$string['selectedcategory'] = 'التصنيف المحدد';

$string['clonecategory:clone'] = 'استنساخ هياكل التصنيفات';
$string['clonecategory:managejobs'] = 'إدارة جميع عمليات استنساخ التصنيفات';
$string['invalidjobstate'] = 'هذا الإجراء غير متاح في حالة العملية الحالية.';
$string['rollbackunavailable'] = 'التراجع متاح لآخر عملية متوقفة خلال ٢٤ ساعة. أوقف العامل مؤقتاً وانتظر انتهاء الخطوة الحالية أولاً.';
$string['rollbackmodified'] = 'تم منع التراجع: تغير أحد العناصر المنسوخة أو يحتوي على محتوى إضافي أو لا توجد له بصمة أصلية. لن يُحذف المحتوى غير المسجل.';
$string['rollbackfailed'] = 'تعذر إكمال التراجع. تم الاحتفاظ بالتتبع للمراجعة وإعادة المحاولة.';
$string['cannotdeleteactive'] = 'أوقف العملية وانتظر توقف العامل قبل حذف سجلها.';
$string['incompletemodified'] = 'تغير مقرر غير مكتمل بعد فشل الاستعادة. راجعه قبل إعادة المحاولة.';
$string['restoreprecheckfailed'] = 'أبلغ فحص الاستعادة في Moodle عن أخطاء. راجع سجل المهمة المحمي للتفاصيل.';
$string['job_failed_safe'] = 'فشلت العملية. راجع سجل مهام Moodle ثم أعد المحاولة أو تراجع عنها.';
$string['job_cancelled'] = 'تم طلب الإلغاء. قد تكتمل الخطوة الحالية؛ تبقى العناصر المنشأة حتى التراجع.';
$string['status_cancelled'] = 'ملغاة';
$string['status_rolling_back'] = 'جارٍ التراجع';
$string['btn_cancel'] = 'إلغاء العملية';
$string['confirm_cancel'] = 'هل تريد إلغاء العملية؟ قد يكتمل المقرر الجاري. ستبقى العناصر المنشأة.';
$string['confirm_delete'] = 'هل تريد حذف سجل العملية؟ ستبقى الموارد المنسوخة وستفقد بيانات التراجع.';
$string['confirm_rollback'] = 'هل تريد حذف العناصر التي أنشأتها هذه العملية ولم تتغير؟ لا يمكن التراجع عن هذا الحذف.';
$string['actioncomplete'] = 'تم تنفيذ الإجراء بنجاح.';
$string['liveupdatesfailed'] = 'التحديث المباشر غير متاح مؤقتاً. حدّث الصفحة أو انتظر عودة الاتصال.';
$string['cronrequired'] = 'يعمل الاستنساخ عبر cron في Moodle. يسري الإيقاف والإلغاء بين خطوات العمل الآمنة. إعادة المحاولة تجدول العملية الفاشلة ولا تشغّل استعادة طويلة داخل المتصفح.';
$string['trackeditemmissing'] = 'أحد الموارد المنسوخة المسجلة مفقود. راجع العملية قبل إعادة المحاولة.';
$string['privacy:metadata:items'] = 'يتتبع الموارد المنشأة ضمن عملية استنساخ طلبها المستخدم.';
$string['privacy:metadata:jobs:userid'] = 'حقل سجل الاستنساخ: userid.';
$string['privacy:metadata:jobs:sourcecategoryid'] = 'حقل سجل الاستنساخ: sourcecategoryid.';
$string['privacy:metadata:jobs:targetparentid'] = 'حقل سجل الاستنساخ: targetparentid.';
$string['privacy:metadata:jobs:clonemode'] = 'حقل سجل الاستنساخ: clonemode.';
$string['privacy:metadata:jobs:categorysuffix'] = 'حقل سجل الاستنساخ: categorysuffix.';
$string['privacy:metadata:jobs:coursesuffix'] = 'حقل سجل الاستنساخ: coursesuffix.';
$string['privacy:metadata:jobs:status'] = 'حقل سجل الاستنساخ: status.';
$string['privacy:metadata:jobs:categoriescount'] = 'حقل سجل الاستنساخ: categoriescount.';
$string['privacy:metadata:jobs:coursescount'] = 'حقل سجل الاستنساخ: coursescount.';
$string['privacy:metadata:jobs:totalcategories'] = 'حقل سجل الاستنساخ: totalcategories.';
$string['privacy:metadata:jobs:totalcourses'] = 'حقل سجل الاستنساخ: totalcourses.';
$string['privacy:metadata:jobs:progress'] = 'حقل سجل الاستنساخ: progress.';
$string['privacy:metadata:jobs:currentstep'] = 'حقل سجل الاستنساخ: currentstep.';
$string['privacy:metadata:jobs:timecreated'] = 'حقل سجل الاستنساخ: timecreated.';
$string['privacy:metadata:jobs:timemodified'] = 'حقل سجل الاستنساخ: timemodified.';
$string['privacy:metadata:jobs:timefinished'] = 'حقل سجل الاستنساخ: timefinished.';
$string['privacy:metadata:items:jobid'] = 'حقل تتبع المورد المنشأ: jobid.';
$string['privacy:metadata:items:itemtype'] = 'حقل تتبع المورد المنشأ: itemtype.';
$string['privacy:metadata:items:itemid'] = 'حقل تتبع المورد المنشأ: itemid.';
$string['privacy:metadata:items:sourceid'] = 'حقل تتبع المورد المنشأ: sourceid.';
$string['privacy:metadata:items:status'] = 'حقل تتبع المورد المنشأ: status.';
$string['privacy:metadata:items:timecreated'] = 'حقل تتبع المورد المنشأ: timecreated.';
$string['privacy:metadata:items:fingerprint'] = 'حقل تتبع المورد المنشأ: fingerprint.';

$string['status_rollback_failed'] = 'التراجع غير مكتمل';
