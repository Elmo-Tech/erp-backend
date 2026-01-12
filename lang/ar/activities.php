<?php

return [
    // CRUD Messages
    'retrieved' => 'تم استرجاع الأنشطة بنجاح',
    'created' => 'تم إنشاء النشاط بنجاح',
    'updated' => 'تم تحديث النشاط بنجاح',
    'deleted' => 'تم حذف النشاط بنجاح',
    'not_found' => 'النشاط غير موجود',
    'validation_failed' => 'فشل التحقق من صحة النشاط',

    // Status Messages
    'completed' => 'تم إكمال النشاط بنجاح',
    'cancelled' => 'تم إلغاء النشاط بنجاح',
    'schedule_retrieved' => 'تم استرجاع الجدول بنجاح',
    'upcoming_retrieved' => 'تم استرجاع الأحداث القادمة بنجاح',

    // Validation Messages
    'outcome_required' => 'النتيجة مطلوبة عند إكمال النشاط',
    'outcome_max' => 'لا يمكن أن تتجاوز النتيجة 5000 حرف',
    'cancellation_reason_required' => 'سبب الإلغاء مطلوب',

    // Reminder Messages
    'reminder_title' => 'تذكير بالنشاط',
    'reminder_message' => 'لديك نشاط قادم: :title',
    'reminder_15_min' => 'نشاطك ":title" يبدأ خلال 15 دقيقة',
    'outcome_reminder_title' => 'سجل نتيجة النشاط',
    'outcome_reminder_message' => 'انتهى النشاط ":title". يرجى تسجيل النتيجة الآن.',

    // Notification Messages
    'notification_scheduled' => 'تم جدولة نشاط جديد: :title',
    'notification_completed' => 'تم إكمال النشاط: :title',
    'notification_cancelled' => 'تم إلغاء النشاط: :title',
];
