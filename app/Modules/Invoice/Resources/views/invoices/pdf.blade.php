<!doctype html>
<html lang="{{ $locale ?? 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .header {
            border-bottom: 2px solid #111827;
            margin-bottom: 28px;
            padding-bottom: 18px;
        }

        .title {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
        }

        .muted {
            color: #6b7280;
        }

        .grid {
            display: table;
            margin-bottom: 24px;
            width: 100%;
        }

        .column {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }

        .label {
            color: #6b7280;
            font-size: 11px;
            text-transform: uppercase;
        }

        .value {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        table {
            border-collapse: collapse;
            margin-top: 20px;
            width: 100%;
        }

        th {
            background: #f3f4f6;
            color: #374151;
            font-size: 11px;
            text-align: left;
            text-transform: uppercase;
        }

        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px;
        }

        .amount {
            text-align: right;
        }

        .total {
            font-size: 18px;
            font-weight: 700;
        }

        .notes {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Invoice {{ $invoice->invoice_number }}</h1>
        <div class="muted">Generated {{ $generatedAt->toDateTimeString() }}</div>
    </div>

    <div class="grid">
        <div class="column">
            <div class="label">Project</div>
            <div class="value">{{ $project?->title ?? 'N/A' }}</div>
            <div class="label">Client</div>
            <div class="value">{{ $client?->name ?? $client?->email ?? 'N/A' }}</div>
        </div>
        <div class="column">
            <div class="label">Status</div>
            <div class="value">{{ $invoice->status->label() }}</div>
            <div class="label">Due date</div>
            <div class="value">{{ $invoice->due_date->toDateString() }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $invoice->milestone_name ?: 'Project invoice' }}</td>
                <td class="amount">{{ number_format((float) $invoice->amount, 2) }} {{ $invoice->currency }}</td>
            </tr>
            <tr>
                <td class="total">Total</td>
                <td class="amount total">{{ number_format((float) $invoice->amount, 2) }} {{ $invoice->currency }}</td>
            </tr>
        </tbody>
    </table>

    @if($invoice->notes)
        <div class="notes">
            <div class="label">Notes</div>
            <div>{{ $invoice->notes }}</div>
        </div>
    @endif
</body>
</html>
