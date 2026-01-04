# Sales Module - API Endpoints

## 1. Leads API

### GET /api/v1/leads

```json
// Query params
{
    "filter[status]": "new|contacted|qualified|proposal|negotiation|won|lost",
    "filter[source]": "facebook|google|linkedin|referral|direct",
    "filter[priority]": "low|medium|high|urgent",
    "filter[assignedTo]": "userId",
    "filter[search]": "company name or contact",
    "sort": "-created_at|priority|expected_close_date",
    "include": "activities,meetings,quotations"
}
```

### POST /api/v1/leads

```json
{
    "name": "أحمد محمد",
    "email": "ahmed@company.com",
    "phone": "+966501234567",
    "company": "شركة التقنية",
    "country": "SA",
    "source": "facebook",
    "referenceType": "sales_rep", // or client_referral, employee
    "referenceId": 5,
    "priority": "high",
    "expectedBudget": 50000,
    "expectedCloseDate": "2026-02-15",
    "notes": "مهتم بنظام ERP"
}
```

### POST /api/v1/leads/{id}/activities

```json
{
    "type": "call",
    "title": "مكالمة متابعة",
    "description": "تم مناقشة المتطلبات",
    "outcome": "مهتم، يحتاج عرض سعر",
    "scheduledAt": null,
    "completedAt": "2026-01-04T10:30:00Z"
}
```

### POST /api/v1/leads/{id}/meetings

```json
{
    "title": "اجتماع تحديد المتطلبات",
    "location": "online",
    "meetingLink": "https://meet.google.com/xxx",
    "scheduledAt": "2026-01-10T14:00:00Z",
    "durationMinutes": 60,
    "attendees": [
        { "userId": 1, "role": "Sales Manager" },
        { "userId": 3, "role": "Technical Lead" }
    ]
}
```

## 2. Quotations API

### GET /api/v1/quotations

```json
{
    "filter[status]": "draft|sent|viewed|expired|negotiation|approved|rejected",
    "filter[expiringIn]": "3", // days
    "filter[leadId]": "123",
    "include": "lead,items,template"
}
```

### POST /api/v1/quotations

```json
{
    "leadId": 123,
    "templateId": 1, // optional
    "items": [
        {
            "resourceType": "backend",
            "description": "تطوير API",
            "estimatedHours": 120,
            "hourlyRate": 150
        },
        {
            "resourceType": "frontend",
            "description": "واجهة المستخدم",
            "estimatedHours": 80,
            "hourlyRate": 120
        }
    ],
    "currency": "SAR",
    "discountPercentage": 10,
    "commissionType": "markup",
    "commissionPercentage": 5,
    "commissionUserId": 5,
    "expiryDate": "2026-01-20",
    "notes": "عرض خاص للعميل"
}
```

### Response (Calculated)

```json
{
  "quotationId": 456,
  "quotationNumber": "QT-2026-0001",
  "items": [...],
  "subtotal": 26400,
  "discount": 2640,
  "commission": 1188, // 5% markup
  "total": 24948,
  "internalCost": 15000, // Hidden from client
  "profitMargin": 9948, // Hidden from client
  "currency": "SAR",
  "status": "draft",
  "expiryDate": "2026-01-20"
}
```

### POST /api/v1/quotations/{id}/send

```json
{
    "recipientEmail": "client@company.com",
    "message": "مرفق عرض السعر الخاص بكم"
}
```

### PATCH /api/v1/quotations/{id}/status

```json
{
    "status": "rejected",
    "rejectionReason": "price",
    "rejectionNotes": "السعر أعلى من المنافسين"
}
```

## 3. Templates API

### GET /api/v1/templates/proposals

### GET /api/v1/templates/contracts

### POST /api/v1/templates/proposals

```json
{
    "name": "قالب SaaS القياسي",
    "type": "saas",
    "content": "<h1>عرض سعر - {{clientName}}</h1>...",
    "placeholders": [
        { "key": "clientName", "label": "اسم العميل" },
        { "key": "projectName", "label": "اسم المشروع" },
        { "key": "totalPrice", "label": "السعر الإجمالي" }
    ]
}
```

### POST /api/v1/templates/proposals/{id}/generate

```json
{
    "quotationId": 456,
    "customValues": {
        "additionalNotes": "ملاحظات إضافية"
    }
}
```

## 4. Contracts API

### POST /api/v1/contracts

```json
{
    "quotationId": 456,
    "templateId": 2,
    "title": "عقد تطوير نظام ERP",
    "partyOne": {
        "company": "شركتنا للبرمجيات",
        "representative": "محمد أحمد",
        "title": "المدير التنفيذي",
        "address": "الرياض، السعودية"
    },
    "partyTwo": {
        "company": "شركة العميل",
        "representative": "خالد سعيد",
        "title": "مدير المشاريع",
        "address": "جدة، السعودية"
    },
    "startDate": "2026-02-01",
    "endDate": "2026-08-01",
    "milestones": [
        {
            "title": "دفعة التعاقد",
            "percentage": 30,
            "triggerType": "immediate"
        },
        {
            "title": "دفعة التصميم",
            "percentage": 20,
            "triggerType": "project_status",
            "triggerValue": "design_approved"
        },
        {
            "title": "دفعة البيتا",
            "percentage": 30,
            "triggerType": "deliverable",
            "triggerValue": "beta_delivered"
        },
        {
            "title": "دفعة التسليم النهائي",
            "percentage": 20,
            "triggerType": "project_status",
            "triggerValue": "completed"
        }
    ]
}
```

### POST /api/v1/contracts/{id}/sign

```json
{
    "signedByClient": "خالد سعيد",
    "signatureDate": "2026-01-15"
}
```

### POST /api/v1/contracts/{id}/convert-to-project

```json
{
    "projectManagerId": 3,
    "teamMembers": [5, 7, 9]
}
```

## 5. Change Requests API

### GET /api/v1/projects/{projectId}/change-requests

### POST /api/v1/projects/{projectId}/change-requests

```json
{
    "title": "إضافة تقرير جديد",
    "description": "العميل يطلب إضافة تقرير مبيعات شهري",
    "impactAnalysis": "يحتاج 16 ساعة عمل إضافية",
    "isBillable": true,
    "estimatedHours": 16,
    "estimatedCost": 2400,
    "additionalDays": 3
}
```

### PATCH /api/v1/change-requests/{id}/approve

```json
{
    "approvedCost": 2000, // قد يختلف عن المقدر
    "notes": "تمت الموافقة مع تخفيض"
}
```

## 6. Handover API

### POST /api/v1/projects/{projectId}/handover

```json
{
    "projectManagerId": 3,
    "scopeConfirmed": true,
    "requirementsClear": true,
    "timelineAgreed": true,
    "budgetConfirmed": true,
    "risksIdentified": true,
    "notes": "تم استلام كافة المتطلبات",
    "attachments": [{ "name": "requirements.pdf", "path": "/uploads/..." }]
}
```

## 7. Analytics API

### GET /api/v1/analytics/sales/performance

```json
// Response
{
    "period": "2026-01",
    "salesReps": [
        {
            "userId": 5,
            "name": "أحمد",
            "leadsCount": 25,
            "convertedCount": 8,
            "conversionRate": 32,
            "totalValue": 150000,
            "commission": 7500
        }
    ]
}
```

### GET /api/v1/analytics/sales/sources

```json
{
    "period": "2026-01",
    "sources": [
        {
            "source": "facebook",
            "leadsCount": 45,
            "convertedCount": 12,
            "conversionRate": 26.7,
            "totalValue": 280000,
            "avgDealSize": 23333
        }
    ]
}
```

### GET /api/v1/analytics/sales/pipeline

```json
{
    "stages": [
        { "status": "new", "count": 15, "value": 450000 },
        { "status": "qualified", "count": 8, "value": 320000 },
        { "status": "proposal", "count": 5, "value": 200000 },
        { "status": "negotiation", "count": 3, "value": 150000 }
    ],
    "forecast": {
        "nextMonth": 180000,
        "nextQuarter": 520000
    }
}
```

### GET /api/v1/analytics/projects/types

```json
{
    "types": [
        { "type": "saas", "count": 12, "percentage": 40, "avgValue": 45000 },
        { "type": "custom", "count": 10, "percentage": 33, "avgValue": 65000 },
        { "type": "mobile", "count": 8, "percentage": 27, "avgValue": 35000 }
    ],
    "rejectionByType": [
        { "type": "mobile", "rejectionRate": 45, "topReason": "price" }
    ]
}
```

### GET /api/v1/analytics/revenue/forecast

```json
{
    "milestonesDue": [
        {
            "projectId": 1,
            "projectName": "نظام ERP",
            "milestoneTitle": "دفعة البيتا",
            "amount": 30000,
            "dueDate": "2026-01-20",
            "probability": 90
        }
    ],
    "totalForecast": {
        "thisMonth": 85000,
        "nextMonth": 120000,
        "thisQuarter": 350000
    }
}
```
