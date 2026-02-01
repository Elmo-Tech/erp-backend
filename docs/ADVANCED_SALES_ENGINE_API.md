# Advanced Sales Engine API Documentation

## Overview

The Advanced Sales Engine extends the quotation system with payment plans, expiry management, and automated notifications. This document covers the enhanced API endpoints and features introduced in Expansion Pack 2.

## Key Features

- **Payment Plans**: Structured payment milestones with percentage-based amounts
- **Expiry Management**: Automatic tracking and notifications for expiring quotations
- **Rejection Handling**: Structured rejection reasons and notes
- **Real-time Validation**: Payment plan validation with detailed feedback
- **Automated Notifications**: 3-day warning system for expiring quotations

## Authentication

All endpoints require authentication using Laravel Sanctum bearer tokens:

```
Authorization: Bearer {your-token}
Accept-Language: en|ar
```

## Enhanced Quotation Endpoints

### 1. Create Quotation with Payment Plan

**POST** `/api/v1/quotations`

Creates a new quotation with optional payment plan and expiry date.

#### Request Body

```json
{
    "leadId": 1,
    "estimations": {
        "frontendHours": 40,
        "backendHours": 60,
        "uiUxHours": 20,
        "testingHours": 15,
        "pmHours": 10
    },
    "hourlyRate": 50.0,
    "discountPercentage": 10.0,
    "currency": "USD",
    "expiryDate": "2026-03-01",
    "paymentPlan": [
        {
            "milestone": "Sign Contract",
            "percentage": 30
        },
        {
            "milestone": "UI/UX Approval",
            "percentage": 40
        },
        {
            "milestone": "Project Delivery",
            "percentage": 30
        }
    ],
    "status": "draft"
}
```

#### Response

```json
{
    "success": true,
    "message": "Quotation created successfully",
    "data": {
        "quotationId": 1,
        "leadId": 1,
        "estimations": {
            "frontendHours": 40,
            "backendHours": 60,
            "uiUxHours": 20,
            "testingHours": 15,
            "pmHours": 10
        },
        "hourlyRate": 50.0,
        "discountPercentage": 10.0,
        "finalPrice": 6525.0,
        "currency": "USD",
        "status": "draft",
        "expiresAt": "2026-03-01T00:00:00.000000Z",
        "paymentPlan": [
            {
                "milestone": "Sign Contract",
                "percentage": 30
            },
            {
                "milestone": "UI/UX Approval",
                "percentage": 40
            },
            {
                "milestone": "Project Delivery",
                "percentage": 30
            }
        ],
        "rejectionReason": null,
        "pricingBreakdown": {
            "totalHours": 145,
            "grossPrice": 7250.0,
            "discountAmount": 725.0,
            "finalPrice": 6525.0
        },
        "paymentBreakdown": {
            "payments": [
                {
                    "milestone": "Sign Contract",
                    "percentage": 30,
                    "amount": 1957.5,
                    "currency": "USD"
                },
                {
                    "milestone": "UI/UX Approval",
                    "percentage": 40,
                    "amount": 2610.0,
                    "currency": "USD"
                },
                {
                    "milestone": "Project Delivery",
                    "percentage": 30,
                    "amount": 1957.5,
                    "currency": "USD"
                }
            ],
            "totalAmount": 6525.0,
            "totalPercentage": 100
        },
        "lead": {
            "leadId": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "company": "Acme Corp"
        },
        "createdAt": "2026-02-01T10:00:00.000000Z",
        "updatedAt": "2026-02-01T10:00:00.000000Z",
        "createdBy": 1,
        "updatedBy": 1
    }
}
```

### 2. Validate Payment Plan

**POST** `/api/v1/quotations/validate-payment-plan`

Validates payment plan structure and calculates payment amounts without saving.

#### Request Body

```json
{
    "paymentPlan": [
        {
            "milestone": "Sign Contract",
            "percentage": 50
        },
        {
            "milestone": "Project Delivery",
            "percentage": 50
        }
    ],
    "finalPrice": 10000.0
}
```

#### Success Response

```json
{
    "success": true,
    "message": "Payment plan validated successfully",
    "data": {
        "isValid": true,
        "paymentBreakdown": {
            "payments": [
                {
                    "milestone": "Sign Contract",
                    "percentage": 50,
                    "amount": 5000.0,
                    "currency": "USD"
                },
                {
                    "milestone": "Project Delivery",
                    "percentage": 50,
                    "amount": 5000.0,
                    "currency": "USD"
                }
            ],
            "totalAmount": 10000.0,
            "totalPercentage": 100
        }
    }
}
```

#### Validation Error Response

```json
{
    "success": false,
    "message": "Payment plan validation failed",
    "errors": {
        "paymentPlan": [
            "Total payment plan percentage must equal 100%. Current total: 90%"
        ]
    }
}
```

### 3. Update Quotation Status

**PATCH** `/api/v1/quotations/{id}/status`

Updates quotation status with optional rejection handling.

#### Request Body (Approval)

```json
{
    "status": "approved"
}
```

#### Request Body (Rejection)

```json
{
    "status": "rejected",
    "rejectionReason": "Budget constraints",
    "rejectionNotes": "Client requested 30% reduction in scope"
}
```

#### Response

```json
{
    "success": true,
    "message": "Quotation status updated successfully",
    "data": {
        "quotationId": 1,
        "status": "rejected",
        "rejectionReason": "Budget constraints"
        // ... other quotation fields
    }
}
```

### 4. Get Expiring Quotations

**GET** `/api/v1/quotations-expiring?days=3`

Retrieves quotations expiring within the specified number of days.

#### Query Parameters

- `days` (optional): Number of days to look ahead (default: 3, max: 30)

#### Response

```json
{
    "success": true,
    "message": "Expiring quotations retrieved successfully",
    "data": {
        "quotations": [
            {
                "quotationId": 1,
                "leadId": 1,
                "finalPrice": 6525.0,
                "currency": "USD",
                "status": "sent",
                "expiresAt": "2026-02-04T00:00:00.000000Z",
                "lead": {
                    "leadId": 1,
                    "name": "John Doe",
                    "company": "Acme Corp"
                }
                // ... other fields
            }
        ],
        "pagination": {
            "total": 5,
            "count": 5,
            "perPage": 10,
            "currentPage": 1,
            "totalPages": 1
        }
    }
}
```

## Payment Plan Structure

### Validation Rules

1. **Required Fields**: Each milestone must have `milestone` and `percentage`
2. **Percentage Range**: Between 0.01 and 100
3. **Total Percentage**: Must sum to exactly 100%
4. **Unique Milestones**: No duplicate milestone names
5. **Non-empty Milestones**: Milestone names cannot be empty

### Common Payment Plan Examples

#### Two-Phase Payment

```json
[
    {
        "milestone": "Sign Contract",
        "percentage": 50
    },
    {
        "milestone": "Project Delivery",
        "percentage": 50
    }
]
```

#### Three-Phase Payment

```json
[
    {
        "milestone": "Sign Contract",
        "percentage": 30
    },
    {
        "milestone": "UI/UX Approval",
        "percentage": 40
    },
    {
        "milestone": "Final Delivery",
        "percentage": 30
    }
]
```

#### Milestone-Based Payment

```json
[
    {
        "milestone": "Project Start",
        "percentage": 25
    },
    {
        "milestone": "Development Complete",
        "percentage": 35
    },
    {
        "milestone": "Testing Complete",
        "percentage": 25
    },
    {
        "milestone": "Go Live",
        "percentage": 15
    }
]
```

## Expiry Management

### Automatic Expiry Handling

The system automatically:

1. **Daily Check**: Runs at 8:00 AM daily via `CheckQuotationExpiryJob`
2. **3-Day Warning**: Sends notifications to sales reps 3 days before expiry
3. **Auto-Expire**: Marks quotations as 'expired' when expiry date passes
4. **Status Filter**: Only processes quotations with status: draft, sent, under_review

### Notification Recipients

1. **Primary**: Lead's assigned sales representative
2. **Fallback**: All users with 'manage_quotations' permission

### Expiry Notification Content

- Quotation number and lead information
- Days until expiry
- Total value and currency
- Direct link to quotation
- Call-to-action for follow-up

## Error Handling

### Common Validation Errors

#### Payment Plan Errors

```json
{
    "success": false,
    "message": "Validation failed for quotation creation",
    "errors": {
        "paymentPlan": [
            "Total payment plan percentage must equal 100%. Current total: 90%"
        ]
    }
}
```

#### Duplicate Milestone Error

```json
{
    "errors": {
        "paymentPlan": [
            "Duplicate milestone 'Sign Contract' found in payment plan."
        ]
    }
}
```

#### Invalid Percentage Error

```json
{
    "errors": {
        "paymentPlan.0.percentage": [
            "Payment percentage must be between 0.01 and 100"
        ]
    }
}
```

### HTTP Status Codes

- `200`: Success
- `201`: Created successfully
- `422`: Validation error
- `401`: Unauthorized
- `404`: Resource not found
- `500`: Server error

## Integration Examples

### Frontend Integration

```javascript
// Validate payment plan before submission
const validatePaymentPlan = async (paymentPlan, finalPrice) => {
    try {
        const response = await api.post("/quotations/validate-payment-plan", {
            paymentPlan,
            finalPrice,
        });

        if (response.data.success) {
            return response.data.data.paymentBreakdown;
        }
    } catch (error) {
        if (error.response?.status === 422) {
            throw new Error(error.response.data.errors.paymentPlan[0]);
        }
        throw error;
    }
};

// Create quotation with payment plan
const createQuotation = async (quotationData) => {
    try {
        const response = await api.post("/quotations", quotationData);
        return response.data.data;
    } catch (error) {
        if (error.response?.status === 422) {
            // Handle validation errors
            const errors = error.response.data.errors;
            console.error("Validation errors:", errors);
        }
        throw error;
    }
};
```

### Testing Examples

```bash
# Test payment plan validation
curl -X POST http://localhost:8000/api/v1/quotations/validate-payment-plan \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -H "Accept-Language: en" \
  -d '{
    "paymentPlan": [
      {"milestone": "Sign Contract", "percentage": 50},
      {"milestone": "Delivery", "percentage": 50}
    ],
    "finalPrice": 10000.00
  }'

# Get expiring quotations
curl -X GET "http://localhost:8000/api/v1/quotations-expiring?days=7" \
  -H "Authorization: Bearer your-token" \
  -H "Accept-Language: en"
```

## Changelog

### Version 2.0.0 (Expansion Pack 2)

#### Added

- Payment plan support with milestone-based payments
- Expiry date tracking and automated notifications
- Rejection reason handling
- Real-time payment plan validation endpoint
- Enhanced quotation resource with payment breakdown
- Automated expiry job with 3-day warning system

#### Enhanced

- QuotationController with payment plan validation
- QuotationResource with new fields
- StoreQuotationRequest with payment plan rules
- Notification system for expiring quotations

#### Database Changes

- Added `expires_at` column to quotations table
- Added `payment_plan` JSON column to quotations table
- Added `rejection_reason` column to quotations table

## Support

For technical support or questions about the Advanced Sales Engine API:

- **Documentation**: Check this document and Swagger specs
- **Testing**: Use the validation endpoints before implementing
- **Error Handling**: Follow the error response patterns
- **Best Practices**: Validate payment plans client-side before submission
