# Sales & Contracting Module - Database Schema

## 1. Leads Enhancement

### leads (تحديث)

```sql
-- الحقول الجديدة
reference_type ENUM('sales_rep', 'client_referral', 'employee') NULL,
reference_id BIGINT UNSIGNED NULL, -- Polymorphic to users/clients
company VARCHAR(255) NULL,
country VARCHAR(100) NULL,
priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
expected_budget DECIMAL(15,2) NULL,
expected_close_date DATE NULL,
last_contact_at TIMESTAMP NULL,
next_follow_up_at TIMESTAMP NULL
```

### lead_activities

```sql
id BIGINT PRIMARY KEY,
lead_id BIGINT UNSIGNED,
user_id BIGINT UNSIGNED, -- من قام بالنشاط
type ENUM('call', 'email', 'meeting', 'note', 'status_change'),
title VARCHAR(255),
description TEXT NULL,
outcome VARCHAR(255) NULL, -- نتيجة المكالمة/الاجتماع
scheduled_at TIMESTAMP NULL,
completed_at TIMESTAMP NULL,
created_at, updated_at
```

### lead_meetings

```sql
id BIGINT PRIMARY KEY,
lead_id BIGINT UNSIGNED,
title VARCHAR(255),
location ENUM('online', 'office', 'client_site'),
meeting_link VARCHAR(500) NULL, -- Zoom/Google Meet
scheduled_at TIMESTAMP,
duration_minutes INT DEFAULT 60,
attendees JSON, -- [{user_id, name, role}]
minutes TEXT NULL, -- Meeting notes
outcome TEXT NULL,
google_calendar_event_id VARCHAR(255) NULL,
created_by, updated_by, created_at, updated_at
```

## 2. Quotations Enhancement

### quotations (تحديث)

```sql
-- الحقول الجديدة
template_id BIGINT UNSIGNED NULL,
expiry_date DATE NULL,
viewed_at TIMESTAMP NULL,
sent_at TIMESTAMP NULL,
rejection_reason ENUM('price', 'timeline', 'competitor', 'budget', 'scope', 'other') NULL,
rejection_notes TEXT NULL,

-- Commission fields
commission_type ENUM('markup', 'margin') NULL,
commission_percentage DECIMAL(5,2) DEFAULT 0,
commission_amount DECIMAL(15,2) DEFAULT 0,
commission_user_id BIGINT UNSIGNED NULL, -- Sales Rep

-- Cost breakdown
internal_cost DECIMAL(15,2) DEFAULT 0, -- Sum of hours * internal rates
profit_margin DECIMAL(15,2) DEFAULT 0,
override_price DECIMAL(15,2) NULL, -- Manual override
override_reason TEXT NULL
```

### quotation_items

```sql
id BIGINT PRIMARY KEY,
quotation_id BIGINT UNSIGNED,
resource_type ENUM('backend', 'frontend', 'ui_ux', 'mobile', 'devops', 'qa', 'pm'),
description VARCHAR(255),
estimated_hours DECIMAL(8,2),
hourly_rate DECIMAL(10,2),
internal_hourly_cost DECIMAL(10,2), -- Hidden from client
subtotal DECIMAL(15,2),
sort_order INT DEFAULT 0,
created_at, updated_at
```

## 3. Templates System

### proposal_templates

```sql
id BIGINT PRIMARY KEY,
name VARCHAR(255),
type ENUM('saas', 'custom', 'mobile', 'maintenance', 'consultation'),
content LONGTEXT, -- Rich HTML/Markdown with placeholders
placeholders JSON, -- [{key: 'client_name', label: 'اسم العميل'}]
is_active BOOLEAN DEFAULT true,
created_by, updated_by, created_at, updated_at
```

### contract_templates

```sql
id BIGINT PRIMARY KEY,
name VARCHAR(255),
type ENUM('development', 'maintenance', 'sla', 'nda', 'consultation'),
content LONGTEXT, -- Legal text with placeholders
placeholders JSON,
is_active BOOLEAN DEFAULT true,
created_by, updated_by, created_at, updated_at
```

## 4. Contracts Management

### contracts

```sql
id BIGINT PRIMARY KEY,
quotation_id BIGINT UNSIGNED NULL,
project_id BIGINT UNSIGNED NULL,
template_id BIGINT UNSIGNED NULL,
contract_number VARCHAR(50) UNIQUE,
title VARCHAR(255),
content LONGTEXT, -- Final contract text

-- Parties
party_one JSON, -- {company, representative, title, id_number, address}
party_two JSON, -- Client details

-- Financial
total_amount DECIMAL(15,2),
currency VARCHAR(3) DEFAULT 'SAR',
payment_terms TEXT NULL,

-- Dates
start_date DATE,
end_date DATE NULL,
signed_at TIMESTAMP NULL,
signed_by_client VARCHAR(255) NULL,

status ENUM('draft', 'pending_signature', 'signed', 'active', 'completed', 'terminated') DEFAULT 'draft',
created_by, updated_by, created_at, updated_at
```

### contract_milestones (Payment Milestones)

```sql
id BIGINT PRIMARY KEY,
contract_id BIGINT UNSIGNED,
title VARCHAR(255),
description TEXT NULL,
amount DECIMAL(15,2),
percentage DECIMAL(5,2), -- % of total
due_date DATE NULL,

-- Event-based trigger
trigger_type ENUM('immediate', 'project_status', 'deliverable', 'date') DEFAULT 'date',
trigger_value VARCHAR(100) NULL, -- e.g., 'design_approved', 'beta_delivered'

status ENUM('pending', 'due', 'invoiced', 'paid', 'overdue') DEFAULT 'pending',
invoice_id BIGINT UNSIGNED NULL,
paid_at TIMESTAMP NULL,
sort_order INT DEFAULT 0,
created_at, updated_at
```

## 5. Change Requests

### change_requests

```sql
id BIGINT PRIMARY KEY,
project_id BIGINT UNSIGNED,
requested_by BIGINT UNSIGNED, -- User or Client
cr_number VARCHAR(50) UNIQUE,
title VARCHAR(255),
description TEXT,
impact_analysis TEXT NULL,

-- Cost
is_billable BOOLEAN DEFAULT true,
estimated_hours DECIMAL(8,2) DEFAULT 0,
estimated_cost DECIMAL(15,2) DEFAULT 0,
approved_cost DECIMAL(15,2) NULL,

-- Timeline impact
additional_days INT DEFAULT 0,

status ENUM('pending', 'under_review', 'approved', 'rejected', 'implemented') DEFAULT 'pending',
approved_by BIGINT UNSIGNED NULL,
approved_at TIMESTAMP NULL,
rejection_reason TEXT NULL,

created_by, updated_by, created_at, updated_at
```

## 6. Project Handover

### project_handovers

```sql
id BIGINT PRIMARY KEY,
project_id BIGINT UNSIGNED,
contract_id BIGINT UNSIGNED NULL,
quotation_id BIGINT UNSIGNED NULL,

-- Handover details
sales_rep_id BIGINT UNSIGNED,
project_manager_id BIGINT UNSIGNED,
handover_date TIMESTAMP,

-- Checklist
scope_confirmed BOOLEAN DEFAULT false,
requirements_clear BOOLEAN DEFAULT false,
timeline_agreed BOOLEAN DEFAULT false,
budget_confirmed BOOLEAN DEFAULT false,
risks_identified BOOLEAN DEFAULT false,

notes TEXT NULL,
attachments JSON NULL, -- [{name, path}]

status ENUM('pending', 'completed', 'disputed') DEFAULT 'pending',
completed_at TIMESTAMP NULL,
created_at, updated_at
```

## 7. Analytics Support Tables

### lead_source_stats (Materialized/Cached)

```sql
source VARCHAR(100),
period DATE, -- Monthly
leads_count INT,
converted_count INT,
total_value DECIMAL(15,2),
conversion_rate DECIMAL(5,2),
avg_deal_size DECIMAL(15,2)
```
