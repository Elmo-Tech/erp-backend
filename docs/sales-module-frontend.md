# Sales Module - Frontend Components

## Directory Structure

```
src/features/sales/
├── api/
│   ├── leads.ts
│   ├── quotations.ts
│   ├── contracts.ts
│   ├── templates.ts
│   └── analytics.ts
├── components/
│   ├── leads/
│   │   ├── LeadKanban.tsx
│   │   ├── LeadCard.tsx
│   │   ├── LeadForm.tsx
│   │   ├── LeadTimeline.tsx
│   │   ├── LeadMeetingDialog.tsx
│   │   └── LeadSourceBadge.tsx
│   ├── quotations/
│   │   ├── QuotationBuilder.tsx
│   │   ├── QuotationItemsTable.tsx
│   │   ├── PricingCalculator.tsx
│   │   ├── CommissionConfig.tsx
│   │   ├── QuotationPreview.tsx
│   │   ├── QuotationStatusBadge.tsx
│   │   └── ExpiringQuotationsWidget.tsx
│   ├── contracts/
│   │   ├── ContractBuilder.tsx
│   │   ├── ContractEditor.tsx
│   │   ├── MilestonesConfig.tsx
│   │   ├── PartiesForm.tsx
│   │   └── ContractPreview.tsx
│   ├── templates/
│   │   ├── TemplateSelector.tsx
│   │   ├── TemplateEditor.tsx
│   │   └── PlaceholderManager.tsx
│   └── analytics/
│       ├── SalesPerformanceChart.tsx
│       ├── SourcesBreakdown.tsx
│       ├── PipelineFunnel.tsx
│       ├── RevenueForecast.tsx
│       └── ProjectTypesChart.tsx
├── hooks/
│   ├── useLeads.ts
│   ├── useQuotations.ts
│   ├── useContracts.ts
│   └── useSalesAnalytics.ts
├── types/
│   ├── lead.types.ts
│   ├── quotation.types.ts
│   ├── contract.types.ts
│   └── analytics.types.ts
└── schemas/
    ├── lead.schema.ts
    ├── quotation.schema.ts
    └── contract.schema.ts
```

## Key Components Specs

### 1. LeadKanban.tsx

```typescript
// Drag & Drop Kanban Board
// Columns: New → Contacted → Qualified → Proposal → Negotiation → Won/Lost
// Features:
// - Drag cards between columns (updates status via API)
// - Quick actions: Call, Email, Schedule Meeting
// - Color coding by priority
// - Filter by source, assigned user
// - Search by company/contact name
```

### 2. LeadTimeline.tsx

```typescript
// Activity feed for single lead
// Shows: Calls, Emails, Meetings, Notes, Status Changes
// Features:
// - Add new activity inline
// - Meeting details expandable
// - Attachments preview
// - Filter by activity type
```

### 3. QuotationBuilder.tsx

```typescript
// Multi-step form:
// Step 1: Select Lead / Template
// Step 2: Add Line Items (resource type, hours, rate)
// Step 3: Configure Pricing (discount, commission)
// Step 4: Preview & Send

// Real-time calculations:
// - Subtotal updates as items added
// - Discount applied
// - Commission calculated (markup vs margin)
// - Profit margin shown (admin only)
```

### 4. PricingCalculator.tsx

```typescript
interface PricingCalculatorProps {
    items: QuotationItem[];
    discount: number;
    commission: CommissionConfig;
    onCalculate: (result: PricingResult) => void;
}

// Calls API for server-side calculation
// Displays breakdown:
// - Technical Cost (internal, hidden)
// - Subtotal
// - Discount
// - Commission (if markup)
// - Final Price
// - Profit Margin (admin only)
```

### 5. ContractEditor.tsx

```typescript
// Rich text editor (TipTap or similar)
// Features:
// - Load template with placeholders
// - Auto-fill from quotation data
// - Manual editing of any clause
// - Placeholder highlighting
// - Version history
// - PDF export
```

### 6. MilestonesConfig.tsx

```typescript
// Payment milestones configuration
// Features:
// - Add/remove milestones
// - Set percentage or fixed amount
// - Configure trigger type:
//   - Immediate (on signing)
//   - Project Status (dropdown of statuses)
//   - Deliverable (custom text)
//   - Date (date picker)
// - Visual timeline preview
// - Total must equal 100%
```

### 7. ExpiringQuotationsWidget.tsx

```typescript
// Dashboard widget
// Shows quotations expiring in next 3 days
// Color coding:
// - Yellow: 2-3 days
// - Orange: 1 day
// - Red: Today/Overdue
// Quick actions: Extend, Send Reminder, Mark as Lost
```

### 8. SalesPerformanceChart.tsx

```typescript
// Bar chart comparing sales reps
// Metrics:
// - Leads count
// - Conversion rate
// - Total value
// - Commission earned
// Period selector: This month, Quarter, Year
```

### 9. PipelineFunnel.tsx

```typescript
// Funnel visualization
// Shows conversion at each stage
// Hover for details:
// - Count
// - Total value
// - Avg time in stage
// Click to filter leads list
```

## Pages Structure

```
src/app/(dashboard)/sales/
├── leads/
│   ├── page.tsx          # Kanban view
│   └── [id]/
│       └── page.tsx      # Lead details + timeline
├── quotations/
│   ├── page.tsx          # List view
│   ├── new/
│   │   └── page.tsx      # Quotation builder
│   └── [id]/
│       ├── page.tsx      # View/Edit quotation
│       └── preview/
│           └── page.tsx  # Client-facing preview
├── contracts/
│   ├── page.tsx          # List view
│   ├── new/
│   │   └── page.tsx      # Contract builder
│   └── [id]/
│       └── page.tsx      # View/Edit contract
├── templates/
│   └── page.tsx          # Manage templates
└── analytics/
    └── page.tsx          # Sales dashboard
```

## State Management

### Zustand Store: useSalesStore

```typescript
interface SalesStore {
    // Kanban state
    kanbanFilters: KanbanFilters;
    setKanbanFilters: (filters: KanbanFilters) => void;

    // Quotation builder state
    draftQuotation: Partial<Quotation> | null;
    setDraftQuotation: (draft: Partial<Quotation>) => void;
    clearDraft: () => void;

    // Selected items for bulk actions
    selectedLeads: number[];
    toggleLeadSelection: (id: number) => void;
    clearSelection: () => void;
}
```

### TanStack Query Keys

```typescript
export const salesKeys = {
    leads: ["leads"] as const,
    lead: (id: number) => ["leads", id] as const,
    leadActivities: (id: number) => ["leads", id, "activities"] as const,

    quotations: ["quotations"] as const,
    quotation: (id: number) => ["quotations", id] as const,
    expiringQuotations: ["quotations", "expiring"] as const,

    contracts: ["contracts"] as const,
    contract: (id: number) => ["contracts", id] as const,

    templates: (type: string) => ["templates", type] as const,

    analytics: {
        performance: ["analytics", "sales", "performance"] as const,
        sources: ["analytics", "sales", "sources"] as const,
        pipeline: ["analytics", "sales", "pipeline"] as const,
        forecast: ["analytics", "revenue", "forecast"] as const,
    },
};
```
