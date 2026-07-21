<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $template->label() }} - {{ $lead->title }}</title>
    <style>
        @page { margin: 42px 48px; }
        body { color: #172033; font-family: "DejaVu Sans", sans-serif; font-size: 12px; line-height: 1.6; }
        .header { border-bottom: 3px solid #2563eb; margin-bottom: 28px; padding-bottom: 18px; }
        .brand { color: #2563eb; font-size: 13px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        h1 { font-size: 26px; line-height: 1.2; margin: 8px 0 4px; }
        h2 { color: #1d4ed8; font-size: 15px; margin: 24px 0 6px; }
        .subtitle { color: #64748b; font-size: 13px; }
        .meta { background: #f1f5f9; border-radius: 6px; margin: 18px 0 26px; padding: 14px 18px; }
        .meta-row { margin: 3px 0; }
        .label { color: #475569; display: inline-block; font-weight: bold; width: 120px; }
        .section { page-break-inside: avoid; }
        .notes { background: #eff6ff; border-left: 3px solid #3b82f6; margin-top: 24px; padding: 12px 16px; }
        .footer { border-top: 1px solid #cbd5e1; color: #64748b; font-size: 10px; margin-top: 34px; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">{{ config('app.name') }}</div>
        <h1>{{ $template->label() }}</h1>
        <div class="subtitle">Prepared for {{ $lead->metadata['company_name'] ?? $lead->title }}</div>
    </div>

    <div class="meta">
        <div class="meta-row"><span class="label">Opportunity</span>{{ $lead->title }}</div>
        @if (! empty($lead->metadata['contact_name']))
            <div class="meta-row"><span class="label">Contact</span>{{ $lead->metadata['contact_name'] }}</div>
        @endif
        @if ($lead->expected_budget !== null)
            <div class="meta-row"><span class="label">Expected budget</span>{{ number_format((float) $lead->expected_budget, 2) }}</div>
        @endif
        <div class="meta-row"><span class="label">Valid until</span>{{ $validUntil->format('F j, Y') }}</div>
    </div>

    <p>{{ $template->description() }}</p>

    @foreach ($template->sections() as $section)
        <div class="section">
            <h2>{{ $section['heading'] }}</h2>
            <p>{{ $section['body'] }}</p>
        </div>
    @endforeach

    @if ($notes)
        <div class="notes">
            <strong>Additional notes</strong><br>
            {!! nl2br(e($notes)) !!}
        </div>
    @endif

    <div class="footer">Generated {{ now()->format('F j, Y') }} · Valid through {{ $validUntil->format('F j, Y') }}</div>
</body>
</html>
