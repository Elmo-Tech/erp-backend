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
    'employee_created' => 'تم إنشاء الموظف بنجاح',
    'employee_updated' => 'تم تحديث الموظف بنجاح',
    'employee_deleted' => 'تم حذف الموظف بنجاح',
    'employee_not_found' => 'الموظف غير موجود',
    'employee_activated' => 'تم تفعيل الموظف بنجاح',
    'employee_deactivated' => 'تم إلغاء تفعيل الموظف بنجاح',
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
    'code_review_rejected' => 'تم رفض مراجعة الكود بنجاح',
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

    // Invoices
    'invoices_retrieved' => 'تم جلب الفواتير بنجاح',
    'invoice_retrieved' => 'تم جلب الفاتورة بنجاح',
    'invoice_created' => 'تم إنشاء الفاتورة بنجاح',
    'invoice_updated' => 'تم تحديث الفاتورة بنجاح',
    'invoice_deleted' => 'تم حذف الفاتورة بنجاح',
    'invoice_sent' => 'تم إرسال الفاتورة بنجاح',
    'invoice_marked_paid' => 'تم تحديد الفاتورة كمدفوعة بنجاح',
    'milestone_invoice_generated' => 'تم إنشاء فاتورة المرحلة بنجاح',
    'pending_invoices_generated' => 'تم إنشاء الفواتير المعلقة بنجاح',
    'cannot_delete_non_draft_invoice' => 'يمكن حذف المسودات فقط',

    // Audit Logs
    'audit_logs_retrieved' => 'تم جلب سجلات المراجعة بنجاح',
    'audit_log_retrieved' => 'تم جلب سجل المراجعة بنجاح',
    'audit_stats_retrieved' => 'تم جلب إحصائيات المراجعة بنجاح',

    // Tasks
    'task_status_updated' => 'تم تحديث حالة المهمة بنجاح',
    'task_marked_complete' => 'تم تحديد المهمة كمكتملة بنجاح',
    'no_permission_manage_team' => 'ليس لديك صلاحية لإدارة الفريق في هذا النوع من المهام',
    'no_permission_manage_test_scenarios' => 'ليس لديك صلاحية لإدارة سيناريوهات الاختبار في هذا النوع من المهام',
    'no_permission_verify_test_scenarios' => 'فقط المدير ومراجعي المهام يمكنهم التحقق من سيناريوهات الاختبار',
    'cannot_create_invoice_for_paid_milestone' => 'لا يمكن إنشاء فاتورة لمرحلة مدفوعة',

    // Status Updates
    'task_status_updated' => 'تم تحديث حالة المهمة بنجاح',
    'project_status_updated' => 'تم تحديث حالة المشروع بنجاح',
    'status_already_set' => 'الحالة مضبوطة بالفعل على :status',
    'no_permission_manage_team' => 'ليس لديك صلاحية لإدارة الفريق في هذا النوع من المهام',
    'no_permission_manage_test_scenarios' => 'ليس لديك صلاحية لإدارة سيناريوهات الاختبار في هذا النوع من المهام',
    'contract_must_have_project' => 'يجب أن يكون العقد مرتبطاً بمشروع لإنشاء فاتورة',
    'invoice_sent' => 'تم إرسال الفاتورة بنجاح',
    'invoice_cancelled' => 'تم إلغاء الفاتورة بنجاح',
    'cannot_cancel_paid_invoice' => 'لا يمكن إلغاء فاتورة مدفوعة',

    // Time Entries
    'time_entry_not_found' => 'سجل الوقت غير موجود',
    'time_entry_updated' => 'تم تحديث سجل الوقت بنجاح',

    // Attendance
    'no_attendance_record' => 'لا يوجد سجل حضور لليوم',
    'clocked_out' => 'تم تسجيل الخروج بنجاح',
    'attendance_not_found' => 'سجل الحضور غير موجود',
    'attendance_updated' => 'تم تحديث سجل الحضور بنجاح',
    'attendance_created' => 'تم إنشاء سجل الحضور بنجاح',
    'attendance_already_exists' => 'يوجد سجل حضور لهذا الموظف في هذا التاريخ',

    // أجهزة التحكم في الدخول
    'devices_retrieved' => 'تم جلب أجهزة التحكم في الدخول بنجاح',
    'device_retrieved' => 'تم جلب جهاز التحكم في الدخول بنجاح',
    'device_created' => 'تم إنشاء جهاز التحكم في الدخول بنجاح',
    'device_updated' => 'تم تحديث جهاز التحكم في الدخول بنجاح',
    'device_deleted' => 'تم حذف جهاز التحكم في الدخول بنجاح',
    'device_not_found' => 'جهاز التحكم في الدخول غير موجود',
    'device_connection_ok' => 'الجهاز يعمل والبيانات المدخلة صحيحة',
    'device_unreachable' => 'تعذر الوصول إلى الجهاز. تحقق من عنوان الـ IP والمنفذ ومسار الشبكة.',
    'device_auth_failed' => 'رفض الجهاز بيانات الدخول. تحقق من اسم المستخدم وكلمة المرور.',
    'device_endpoint_unsupported' => 'الجهاز لا يدعم هذه الواجهة (ISAPI).',
    'device_request_failed' => 'رفض الجهاز الطلب (الحالة :status).',
    'device_invalid_payload' => 'أرسل الجهاز بيانات غير قابلة للقراءة.',
    'device_event_accepted' => 'تم استلام حدث الجهاز',
    'device_users_pushed' => 'تم إرسال الموظفين إلى الجهاز',
    'device_users_pulled' => 'تم جلب المستخدمين الموجودين على الجهاز',
    'device_user_linked' => 'تم ربط الموظف بالجهاز',
    'device_user_unlinked' => 'تم فك ربط الموظف من الجهاز',
    'device_polled' => 'تم قراءة الأحداث الجديدة من الجهاز',
    'device_events_retrieved' => 'تم جلب أحداث الجهاز',
    'device_webhook_enabled' => 'الجهاز الآن يرسل أحداثه إلى النظام',
    'device_webhook_disabled' => 'الجهاز لم يعد يرسل أحداثه إلى النظام',

    // Task Permissions
    'no_permission_manage_team' => 'ليس لديك صلاحية لإدارة أعضاء الفريق في هذا النوع من المهام.',
    'no_permission_manage_test_scenarios' => 'ليس لديك صلاحية لإدارة سيناريوهات الاختبار في هذا النوع من المهام.',
    'no_permission_verify_test_scenarios' => 'ليس لديك صلاحية للتحقق من سيناريوهات الاختبار. فقط الأدمن والأشخاص المعينين كمراجعين في فريق المهمة يمكنهم التحقق.',

    // Project Permissions
    'no_permission_view_budget' => 'ليس لديك صلاحية لعرض معلومات ميزانية المشروع.',
    'no_permission_view_history' => 'ليس لديك صلاحية لعرض سجل تغييرات المشروع.',

    // Task Deliverables
    'deliverables_retrieved' => 'تم جلب مخرجات المهمة بنجاح',
    'deliverable_retrieved' => 'تم جلب المخرج بنجاح',
    'deliverable_created' => 'تم إنشاء المخرج بنجاح',
    'deliverable_updated' => 'تم تحديث المخرج بنجاح',
    'deliverable_deleted' => 'تم حذف المخرج بنجاح',
    'deliverable_submitted' => 'تم تسليم المخرج للمراجعة بنجاح',
    'deliverable_reviewed' => 'تم مراجعة المخرج بنجاح',
    'deliverable_cannot_update' => 'لا يمكن تحديث المخرج في الحالة الحالية',
    'deliverable_cannot_delete' => 'لا يمكن حذف المخرج في الحالة الحالية',
    'deliverable_cannot_submit' => 'لا يمكن تسليم المخرج في الحالة الحالية',
    'deliverable_cannot_review' => 'لا يمكن مراجعة المخرج في الحالة الحالية',

    // Admin Deliverable Management
    'admin_deliverable_updated' => 'تم تحديث المخرج بنجاح من قبل المسؤول',
    'admin_deliverable_deleted' => 'تم حذف المخرج بنجاح من قبل المسؤول',
    'admin_status_changed' => 'تم تغيير حالة المخرج بنجاح',
    'admin_deliverable_reset' => 'تم إعادة تعيين المخرج إلى حالة الانتظار بنجاح',
    'admin_deliverable_approved' => 'تم الموافقة على المخرج بشكل مباشر بنجاح',
    'admin_deliverable_rejected' => 'تم رفض المخرج بشكل مباشر بنجاح',

    // Activities
    'activities_retrieved' => 'تم استرداد الأنشطة بنجاح',
    'activity_created' => 'تم إنشاء النشاط بنجاح',
    'activity_retrieved' => 'تم استرداد النشاط بنجاح',
    'activity_updated' => 'تم تحديث النشاط بنجاح',
    'activity_completed' => 'تم وضع علامة على النشاط كمكتمل بنجاح',
    'activity_deleted' => 'تم حذف النشاط بنجاح',

    // Contracts
    'contracts' => [
        'retrieved' => 'تم استرداد العقود بنجاح',
        'generated' => 'تم إنشاء العقد بنجاح',
        'signed' => 'تم توقيع العقد بنجاح',
        'status_updated' => 'تم تحديث حالة العقد بنجاح',
        'already_exists' => 'يوجد عقد بالفعل لهذا العرض',
        'deleted' => 'تم حذف عقد العمل بنجاح',
    ],

    // Analytics
    'sales_performance_retrieved' => 'تم استرداد مقاييس الأداء التجاري بنجاح',
    'rejection_analysis_retrieved' => 'تم استرداد تحليل الرفض بنجاح',
    'financial_forecast_retrieved' => 'تم استرداد التوقعات المالية بنجاح',
    'follow_up_radar_retrieved' => 'تم استرداد رادار المتابعة بنجاح',
    'dashboard_summary_retrieved' => 'تم استرداد ملخص لوحة التحكم بنجاح',
    'analytics_cache_cleared' => 'تم مسح ذاكرة التخزين المؤقت للتحليلات بنجاح',
];
