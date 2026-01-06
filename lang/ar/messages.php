<?php

return [
    // General
    'validation_failed' => 'فشل التحقق من البيانات',
    'validation_error' => 'خطأ في التحقق من البيانات',
    'no_permission' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء',
    'unauthorized' => 'غير مصرح بالوصول',
    'unauthorized_send_invoice' => 'ليس لديك صلاحية لإرسال الفواتير',
    
    // Users
    'users_retrieved' => 'تم جلب المستخدمين بنجاح',
    'user_retrieved' => 'تم جلب المستخدم بنجاح',
    'user_created' => 'تم إنشاء المستخدم بنجاح',
    'user_updated' => 'تم تحديث المستخدم بنجاح',
    'user_deleted' => 'تم حذف المستخدم بنجاح',
    'user_not_found' => 'المستخدم غير موجود',
    'user_activated' => 'تم تفعيل المستخدم بنجاح',
    'user_deactivated' => 'تم إلغاء تفعيل المستخدم بنجاح',
    'cannot_delete_self' => 'لا يمكنك حذف حسابك الخاص',
    'roles_assigned' => 'تم تعيين الأدوار بنجاح',

    // Performance Evaluation
    'evaluation_period_created' => 'تم إنشاء فترة التقييم بنجاح',
    'period_activated' => 'تم تفعيل فترة التقييم وإنشاء تقييمات لجميع الموظفين',
    'period_already_activated' => 'تم تفعيل هذه الفترة مسبقاً',
    'evaluation_calculated' => 'تم حساب درجات التقييم بنجاح',
    'evaluation_updated' => 'تم تحديث التقييم بنجاح',
    'evaluation_approved' => 'تم اعتماد التقييم بنجاح',

    // Code Reviews
    'code_review_created' => 'تم إنشاء مراجعة الكود بنجاح',
    'code_review_updated' => 'تم تحديث مراجعة الكود بنجاح',
    'code_review_approved' => 'تم الموافقة على مراجعة الكود بنجاح',
    'revision_requested' => 'تم طلب التعديل بنجاح',

    // Innovation Logs
    'innovation_logged' => 'تم تسجيل الابتكار بنجاح',
    'innovation_verified' => 'تم التحقق من الابتكار بنجاح',
    'innovation_rejected' => 'تم رفض الابتكار بنجاح',
    'innovation_deleted' => 'تم حذف سجل الابتكار بنجاح',
    'cannot_delete_verified' => 'لا يمكن حذف سجل ابتكار تم التحقق منه',

    // Milestones & Invoices
    'milestone_retrieved' => 'تم جلب المرحلة بنجاح',
    'milestone_marked_paid' => 'تم تحديد المرحلة كمدفوعة بنجاح',
    'invoice_created_from_milestone' => 'تم إنشاء الفاتورة من المرحلة بنجاح',
    'invoice_linked_to_milestone' => 'تم ربط الفاتورة بالمرحلة بنجاح',
    'milestone_already_has_invoice' => 'المرحلة مرتبطة بفاتورة بالفعل',
    'cannot_create_invoice_for_paid_milestone' => 'لا يمكن إنشاء فاتورة لمرحلة مدفوعة',
    'contract_must_have_project' => 'يجب أن يكون العقد مرتبطاً بمشروع لإنشاء فاتورة',
    'invoice_sent' => 'تم إرسال الفاتورة بنجاح',
    'invoice_cancelled' => 'تم إلغاء الفاتورة بنجاح',
    'cannot_cancel_paid_invoice' => 'لا يمكن إلغاء فاتورة مدفوعة',
];
