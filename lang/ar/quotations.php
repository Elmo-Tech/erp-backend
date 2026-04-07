<?php

return [
    'retrieved' => 'تم استرجاع عروض الأسعار بنجاح',
    'created' => 'تم إنشاء عرض السعر بنجاح',
    'updated' => 'تم تحديث عرض السعر بنجاح',
    'deleted' => 'تم حذف عرض السعر بنجاح',
    'sent' => 'تم إرسال عرض السعر بنجاح',
    'status_updated' => 'تم تحديث حالة عرض السعر بنجاح',
    'expiring_retrieved' => 'تم استرجاع العروض المنتهية قريباً بنجاح',
    'overdue_retrieved' => 'تم استرجاع العروض المتأخرة بنجاح',
    'not_found' => 'عرض السعر غير موجود',
    'already_sent' => 'تم إرسال عرض السعر بالفعل',
    'cannot_send' => 'لا يمكن إرسال عرض السعر في الحالة الحالية',

    // Validation
    'validation_failed' => 'فشل التحقق من عرض السعر',
    'milestones_required' => 'يجب توفير مرحلة دفع واحدة على الأقل',
    'at_least_one_milestone' => 'يجب توفير مرحلة دفع واحدة على الأقل',
    'milestones_must_equal_100' => 'يجب أن يساوي مجموع نسب المراحل 100%. المجموع الحالي: :total%',

    // Payment Plan
    'payment_percentage_invalid' => 'يجب أن تكون نسبة الدفع بين 0.01 و 100',
    'payment_plan_validated' => 'تم التحقق من خطة الدفع بنجاح',
    'payment_plan_invalid' => 'فشل في التحقق من خطة الدفع',
    'items_or_estimations_required' => 'يجب توفير العناصر أو التقديرات',
    'override_reason_required' => 'سبب التجاوز مطلوب عند تجاوز السعر',
    'expiry_must_be_future' => 'يجب أن يكون تاريخ الانتهاء في المستقبل',
];
