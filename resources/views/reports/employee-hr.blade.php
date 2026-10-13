<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Comprehensive employee report</title>
    <style>
        @page { margin: 30px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #27354a; }
        h1 { font-size: 20px; color: #2757df; margin-bottom: 6px; }
        h2 { font-size: 13px; margin-top: 22px; border-bottom: 1px solid #dce4f0; padding-bottom: 6px; page-break-after: avoid; }
        .meta { color: #63738b; font-size: 10px; }
        .record { border: 1px solid #e0e6f0; padding: 9px; margin: 7px 0; page-break-inside: avoid; }
        .label { color: #748198; }
        table { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e0e6f0; padding: 7px; text-align: left; }
        th { background: #edf2ff; }
        .empty { color: #8995a8; }
    </style>
</head>
<body>
    <h1>Comprehensive employee report</h1>
    <strong>{{ $report['employee']['name'] }}</strong> · {{ $report['employee']['email'] }}
    <p class="meta">{{ $report['period']['dateFrom'] }} — {{ $report['period']['dateTo'] }} · Timezone: {{ $report['timezone'] }}<br>Generated: {{ $report['generatedAt'] }}</p>
    <p>Specialization: {{ $report['employee']['specialization'] ?? '—' }} · Status: {{ $report['employee']['isActive'] ? 'Active' : 'Inactive' }}</p>
    <p>Roles: {{ implode(', ', $report['employee']['roles']) }} · Skills: {{ implode(', ', $report['employee']['skills']) }}<br>Employee record created: {{ $report['employee']['joinedAt'] }}</p>
    <h2>Work commitment & performance</h2>
    <p>Required: {{ round($report['workCommitment']['requiredMinutes'] / 60, 2) }} hours · Tracked: {{ round($report['workCommitment']['workedMinutes'] / 60, 2) }} hours · Completion: {{ $report['workCommitment']['completionPercentage'] ?? '—' }}%</p>
    <p class="meta">Part-time required hours are prorated for the actual service dates. Supervision time is reported separately.</p>
    <h2>Salary by payroll month (EGP)</h2>
    <p class="meta">Salary is calculated for each payroll month from the inclusive contract service dates. Additions and deductions are included once. Months shown cover the selected date range.</p>
    <table><thead><tr><th>Month</th><th>Service days</th><th>Gross salary</th><th>Additions</th><th>Deductions</th><th>Net salary</th></tr></thead><tbody>
        @foreach ($report['salaryPeriods'] as $salary)
        <tr><td>{{ $salary['month'] }}</td><td>{{ $salary['serviceDays'] }} / {{ $salary['calendarDays'] }}</td><td>{{ number_format($salary['grossSalary'], 2) }}</td><td>{{ number_format($salary['additions'], 2) }}</td><td>{{ number_format($salary['totalDeductions'], 2) }}</td><td>{{ number_format($salary['netSalary'], 2) }}</td></tr>
        @endforeach
    </tbody></table>
    @foreach ($report['sections'] as $section)
        <h2>{{ $section['title'] }} ({{ count($section['rows']) }})</h2>
        @forelse ($section['rows'] as $row)
            <div class="record"><strong>Record #{{ $row['recordId'] }}</strong><br>
                @foreach ($section['columns'] as $key => $label)
                    <span class="label">{{ $label }}:</span> {{ $row[$key] ?? '—' }}<br>
                @endforeach
            </div>
        @empty
            <p class="empty">No records in this section for the selected period.</p>
        @endforelse
    @endforeach
</body>
</html>
