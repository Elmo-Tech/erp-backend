<?php

return [
    // CRUD Messages
    'history_retrieved' => 'تم استرجاع السجل بنجاح',
    'full_history_retrieved' => 'تم استرجاع السجل الكامل بنجاح',
    'stats_retrieved' => 'تم استرجاع الإحصائيات بنجاح',
    'recent_activity_retrieved' => 'تم استرجاع النشاط الأخير بنجاح',
    'user_activity_retrieved' => 'تم استرجاع نشاط المستخدم بنجاح',

    // Audit Descriptions
    'record_created' => 'تم إنشاء سجل :model',
    'record_deleted' => 'تم حذف سجل :model',
    'status_changed' => 'تم تغيير الحالة من :from إلى :to',
    'field_changed' => 'تم تغيير :field من :from إلى :to',

    // Action Types
    'action_types' => [
        'status_change' => 'تغيير الحالة',
        'data_edit' => 'تعديل البيانات',
        'upload' => 'رفع ملف',
        'activity_log' => 'سجل النشاط',
        'created' => 'إنشاء',
        'deleted' => 'حذف',
        'converted' => 'تحويل',
        'sent' => 'إرسال',
        'approved' => 'موافقة',
        'rejected' => 'رفض',
    ],
];
