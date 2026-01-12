<?php

return [
    // CRUD Messages
    'retrieved' => 'تم استرجاع الاجتماعات بنجاح',
    'created' => 'تم إنشاء الاجتماع بنجاح',
    'updated' => 'تم تحديث الاجتماع بنجاح',
    'deleted' => 'تم حذف الاجتماع بنجاح',
    'not_found' => 'الاجتماع غير موجود',
    'validation_failed' => 'فشل التحقق من صحة الاجتماع',

    // Status Messages
    'completed' => 'تم إكمال الاجتماع بنجاح',
    'cancelled' => 'تم إلغاء الاجتماع بنجاح',

    // Validation Messages
    'minutes_required' => 'محضر الاجتماع مطلوب عند إكمال الاجتماع',
    'minutes_max' => 'لا يمكن أن يتجاوز محضر الاجتماع 10000 حرف',
    'cancellation_reason_required' => 'سبب الإلغاء مطلوب',
    'must_be_future' => 'يجب جدولة الاجتماع في المستقبل',

    // Reminder Messages
    'reminder_title' => 'تذكير بالاجتماع',
    'reminder_message' => 'لديك اجتماع قادم: :title',
    'reminder_15_min' => 'اجتماعك ":title" يبدأ خلال 15 دقيقة',
    'outcome_reminder_title' => 'سجل محضر الاجتماع',
    'outcome_reminder_message' => 'انتهى الاجتماع ":title". يرجى تسجيل المحضر الآن.',

    // Notification Messages
    'notification_scheduled' => 'تم جدولة اجتماع جديد: :title',
    'notification_completed' => 'تم إكمال الاجتماع: :title',
    'notification_cancelled' => 'تم إلغاء الاجتماع: :title',
    'notification_invitation' => 'تمت دعوتك لاجتماع: :title',
    'notification_cancellation' => 'تم إلغاء الاجتماع ":title". السبب: :reason',
];
