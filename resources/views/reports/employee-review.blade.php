<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('employee_review.Employee Performance Review') }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap');
        /* إعدادات الصفحة للطباعة */
        @page {
            margin: 0;
            size: A4;
        }

        :root {
            --primary: #0f172a;      /* Slate 900 - للنصوص الأساسية */
            --secondary: #64748b;    /* Slate 500 - للنصوص الفرعية */
            --border: #e2e8f0;       /* Slate 200 - للحدود */
            --bg-surface: #ffffff;   /* خلفية الكروت */
            --bg-base: #f8fafc;      /* خلفية الصفحة */
            
            --brand: #2563eb;        /* Blue 600 - اللون الرئيسي */
            --success: #10b981;      /* Emerald 500 - للإيجابيات */
            --success-bg: #ecfdf5;   
            --danger: #ef4444;       /* Red 500 - للسلبيات */
            --danger-bg: #fef2f2;
            --warning: #f59e0b;      /* Amber 500 - للتحذيرات */
            --warning-bg: #fffbeb;
        }

        body {
            font-family: 'Cairo', 'DejaVu Sans', 'Inter', sans-serif;
            background-color: var(--bg-base);
            color: var(--primary);
            margin: 0;
            padding: 40px;
            font-size: 11px;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        * {
            font-family: 'Cairo', 'DejaVu Sans', 'Inter', sans-serif;
        }

        /* تنسيقات عامة مساعدة */
        .flex { display: flex; align-items: center; }
        .justify-between { justify-content: space-between; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .font-bold { font-weight: 700; }
        .text-sm { font-size: 10px; }
        .text-xs { font-size: 9px; }
        .mb-2 { margin-bottom: 0.5rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-4 { margin-top: 1rem; }
        
        /* الألوان للنصوص */
        .text-success { color: #059669; }
        .text-danger { color: #dc2626; }
        .text-warning { color: #d97706; }
        .text-secondary { color: var(--secondary); }
        .text-brand { color: var(--brand); }

        /* الكروت (Cards) */
        .card {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        /* الهيدر */
        .header {
            margin-bottom: 30px;
            border-bottom: 2px solid var(--primary);
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .header-meta {
            color: var(--secondary);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* أيقونات SVG */
        .icon {
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-{{ app()->getLocale() == 'ar' ? 'left' : 'right' }}: 6px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .icon-lg { width: 20px; height: 20px; }

        /* قسم الموظف */
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .emp-item label {
            display: block;
            font-size: 9px;
            color: var(--secondary);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .emp-item div {
            font-size: 13px;
            font-weight: 700;
        }

        /* قسم الراتب (Hero) */
        .salary-section {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .salary-header {
            background: #f1f5f9;
            padding: 12px 20px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
        }
        .salary-body {
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-around;
        }
        .salary-item { text-align: center; }
        .salary-val { font-size: 18px; font-weight: 700; display: block; }
        .salary-lbl { font-size: 10px; color: var(--secondary); font-family: 'Cairo', 'DejaVu Sans', sans-serif; }
        .math-op { font-size: 20px; color: #cbd5e1; }
        .final-box {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-align: center;
        }

        /* الجداول */
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th {
            background: #f8fafc;
            color: var(--secondary);
            font-weight: 600;
            text-transform: uppercase;
            padding: 10px;
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 10px;
            border-bottom: 1px solid var(--border);
            color: var(--primary);
        }
        tr:last-child td { border-bottom: none; }

        /* البادجات */
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 700;
            display: inline-block;
        }
        .bg-success { background: var(--success-bg); color: var(--success); border: 1px solid #a7f3d0; }
        .bg-danger { background: var(--danger-bg); color: var(--danger); border: 1px solid #fecaca; }
        .bg-warning { background: var(--warning-bg); color: var(--warning); border: 1px solid #fde68a; }

        /* فواصل الصفحات */
        .page-break { page-break-before: always; }
        
        /* Progress Bar */
        .progress-container { background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden; margin-top: 5px; }
        .progress-bar { height: 100%; border-radius: 3px; }
        
        /* إصلاح النصوص العربية */
        .arabic-text {
            font-family: 'Cairo', 'DejaVu Sans', sans-serif !important;
            direction: ltr;
            unicode-bidi: embed;
        }
    </style>
</head>
<body>

    <div class="header flex justify-between">
        <div>
            <h1>
                <svg class="icon icon-lg" style="margin-right: 8px; margin-left: 8px; color: var(--brand);" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                {{ trans('employee_review.Employee Performance Review') }}
            </h1>
        </div>
        <div class="text-right header-meta">
            <div>{{ trans('employee_review.Generated on') }} {{ date('F j, Y') }}</div>
            <div style="margin-top: 4px;">{{ trans('employee_review.Report ID') }}: #{{ uniqid() }}</div>
        </div>
    </div>

    <div class="card">
        <div class="employee-grid">
            <div class="emp-item">
                <label>{{ trans('employee_review.Name') }}</label>
                <div class="flex">
                    <svg class="icon text-secondary" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span class="arabic-text">{{ $employee['name'] }}</span>
                </div>
            </div>
            <div class="emp-item">
                <label>{{ trans('employee_review.Email') }}</label>
                <div class="flex">
                    <svg class="icon text-secondary" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    {{ $employee['email'] }}
                </div>
            </div>
            <div class="emp-item">
                <label>{{ trans('employee_review.Specialization') }}</label>
                <div class="flex">
                    <svg class="icon text-secondary" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    @php
                        $specialization = $employee['specialization'];
                        if ($specialization) {
                            $specializationValue = is_object($specialization) && property_exists($specialization, 'value') ? $specialization->value : (string) $specialization;
                            $translatedSpecialization = trans('enums.' . $specializationValue);
                            if ($translatedSpecialization === 'enums.' . $specializationValue) $translatedSpecialization = $specializationValue;
                        } else {
                            $translatedSpecialization = trans('employee_review.N/A');
                        }
                    @endphp
                    <span class="arabic-text">{{ $translatedSpecialization }}</span>
                </div>
            </div>
            <div class="emp-item">
                <label>{{ trans('employee_review.Current Points') }}</label>
                <div class="flex">
                    <svg class="icon {{ $employee['totalPoints'] >= 0 ? 'text-success' : 'text-danger' }}" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                    {{ $employee['totalPoints'] }}
                </div>
            </div>
        </div>
    </div>

    @php
        $baseSalary = $employee['salary'] ?? 0;
        $pointValue = 10;
        $pointsImpact = $overview['netPointsChange'] * $pointValue;
        $attendanceDeduction = ($deductions['totalDeductions'] ?? 0) * $pointValue;
        $totalAdjustment = $pointsImpact - $attendanceDeduction;
        $finalSalary = $baseSalary + $totalAdjustment;
    @endphp

    <div class="salary-section">
        <div class="salary-header flex justify-between">
            <span>
                <svg class="icon" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                {{ trans('employee_review.Monthly Salary Calculation') }}
            </span>
            <span style="font-weight: 400; font-size: 10px; color: var(--secondary);">1 Point = {{ $pointValue }} EGP</span>
        </div>
        <div class="salary-body">
            <div class="salary-item">
                <span class="salary-val">{{ number_format($baseSalary) }}</span>
                <span class="salary-lbl arabic-text">{{ trans('employee_review.Base Salary') }}</span>
            </div>
            <div class="math-op">+</div>
            <div class="salary-item">
                <span class="salary-val {{ $pointsImpact >= 0 ? 'text-success' : 'text-danger' }}">
                    {{ number_format($pointsImpact) }}
                </span>
                <span class="salary-lbl arabic-text">{{ trans('employee_review.Performance') }}</span>
            </div>
            <div class="math-op">-</div>
            <div class="salary-item">
                <span class="salary-val text-danger">{{ number_format($attendanceDeduction) }}</span>
                <span class="salary-lbl arabic-text">{{ trans('employee_review.Deductions') }}</span>
            </div>
            <div class="math-op">=</div>
            <div class="final-box">
                <span style="display: block; font-size: 20px; font-weight: 800; line-height: 1;">{{ number_format($finalSalary) }} <small style="font-size: 10px;">EGP</small></span>
                <span class="arabic-text" style="font-size: 9px; opacity: 0.9;">{{ trans('employee_review.Final Net Salary') }}</span>
            </div>
        </div>
    </div>

    <h3 style="margin-top: 0; font-size: 14px; margin-bottom: 10px;" class="arabic-text">{{ trans('employee_review.Performance Overview') }}</h3>
    <div class="grid-3">
        <div class="card" style="margin-bottom: 0;">
            <div class="flex justify-between mb-2">
                <span class="text-secondary text-xs uppercase font-bold arabic-text">{{ trans('employee_review.Productivity') }}</span>
                <svg class="icon text-brand" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            </div>
            <div style="font-size: 24px; font-weight: 800;">{{ number_format($overview['productivityScore'], 1) }}<small class="text-secondary" style="font-size: 12px;">/100</small></div>
            <div class="badge {{ $overview['productivityScore'] >= 80 ? 'bg-success' : 'bg-warning' }}" style="margin-top: 5px;">
                <span class="arabic-text">{{ $overview['productivityScore'] >= 80 ? trans('employee_review.Good') : trans('employee_review.Average') }}</span>
            </div>
        </div>
        
        <div class="card" style="margin-bottom: 0;">
            <div class="flex justify-between mb-2">
                <span class="text-secondary text-xs uppercase font-bold arabic-text">{{ trans('employee_review.Task Completion') }}</span>
                <svg class="icon text-success" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div style="font-size: 24px; font-weight: 800;">{{ number_format($overview['taskCompletionRate'], 1) }}%</div>
            <div class="progress-container">
                <div class="progress-bar" style="width: {{ $overview['taskCompletionRate'] }}%; background: var(--success);"></div>
            </div>
            <div class="text-xs text-secondary mt-4 arabic-text">{{ $tasks['completed'] }} / {{ $tasks['total'] }} {{ trans('employee_review.Tasks') }}</div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="flex justify-between mb-2">
                <span class="text-secondary text-xs uppercase font-bold arabic-text">{{ trans('employee_review.Tracked Hours') }}</span>
                <svg class="icon text-warning" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            </div>
            <div style="font-size: 24px; font-weight: 800;">{{ number_format($overview['totalTrackedHours'], 1) }}<small style="font-size: 12px;">h</small></div>
            <div class="text-xs text-secondary mt-4 arabic-text">
                {{ trans('employee_review.Idle') }}: {{ number_format($overview['totalIdleHours'], 1) }}h
            </div>
        </div>
    </div>

    <div style="margin-bottom: 25px;"></div>

    <div class="grid-2">
        @if(isset($gracePeriod))
        <div class="card {{ $gracePeriod['isExpired'] ? 'bg-danger' : 'bg-success' }}" style="background: {{ $gracePeriod['isExpired'] ? '#fef2f2' : '#f0fdf4' }}; border-color: {{ $gracePeriod['isExpired'] ? '#fecaca' : '#bbf7d0' }};">
            <h4 class="flex arabic-text" style="margin: 0 0 10px 0; color: {{ $gracePeriod['isExpired'] ? '#991b1b' : '#166534' }};">
                <svg class="icon" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                {{ trans('employee_review.Grace Period Status') }}
            </h4>
            <div class="grid-2">
                <div>
                    <span class="text-xs text-secondary block arabic-text">{{ trans('employee_review.Status') }}</span>
                    <span class="font-bold {{ $gracePeriod['isExpired'] ? 'text-danger' : 'text-success' }} arabic-text">
                        {{ $gracePeriod['isExpired'] ? trans('employee_review.Expired') : trans('employee_review.Active') }}
                    </span>
                </div>
                <div>
                    <span class="text-xs text-secondary block arabic-text">{{ trans('employee_review.Days Remaining') }}</span>
                    <span class="font-bold">{{ max(0, $gracePeriod['remainingDays']) }} / {{ $gracePeriod['allowedDays'] }}</span>
                </div>
            </div>
            <p class="text-xs mt-4 arabic-text" style="color: {{ $gracePeriod['isExpired'] ? '#7f1d1d' : '#14532d' }}; opacity: 0.8;">
                {{ $gracePeriod['isExpired'] ? trans('employee_review.Grace period has expired. Deductions may apply.') : trans('employee_review.Grace period is active. No deductions applied.') }}
            </p>
        </div>
        @endif

        @if(isset($deductions))
        <div class="card">
            <h4 class="flex arabic-text" style="margin: 0 0 10px 0;">
                <svg class="icon text-danger" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                {{ trans('employee_review.Attendance Deductions') }}
            </h4>
            <div class="grid-2">
                <div>
                    <span class="text-xs text-secondary block arabic-text">{{ trans('employee_review.Late Minutes') }}</span>
                    <span class="font-bold text-danger">{{ $deductions['totalLateMinutes'] }} min</span>
                </div>
                <div>
                    <span class="text-xs text-secondary block arabic-text">{{ trans('employee_review.Total Deductions') }}</span>
                    <span class="font-bold text-danger">-{{ $deductions['totalDeductions'] }} pts</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(isset($deductions) && !empty($deductions['dailyDeductions']) && count($deductions['dailyDeductions']) > 0)
    <div class="card">
        <h4 class="arabic-text" style="margin: 0 0 15px 0; font-size: 12px;">{{ trans('employee_review.Daily Deduction Breakdown') }}</h4>
        <table>
            <thead>
                <tr>
                    <th class="arabic-text">{{ trans('employee_review.Date') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Clock In') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Late Minutes') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Points Deducted') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $dailyDeductionsArray = is_array($deductions['dailyDeductions']) ? $deductions['dailyDeductions'] : $deductions['dailyDeductions']->toArray();
                @endphp
                @foreach(array_slice($dailyDeductionsArray, 0, 10) as $dailyDeduction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dailyDeduction['date'])->format('M j, Y') }}</td>
                    <td><span class="badge bg-warning" style="color: #92400e;">{{ $dailyDeduction['clockIn'] }}</span></td>
                    <td class="font-bold text-warning">{{ $dailyDeduction['lateMinutes'] }} min</td>
                    <td class="font-bold text-danger">-{{ $dailyDeduction['deduction'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="page-break"></div>

    @if(isset($tasks) && !empty($tasks['tasksWithBounces']) && count($tasks['tasksWithBounces']) > 0)
    <div class="card" style="border-left: 4px solid var(--danger);">
        <h4 class="flex text-danger arabic-text" style="margin: 0 0 15px 0;">
            <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            {{ trans('employee_review.Tasks Requiring Rework') }} ({{ count($tasks['tasksWithBounces']) }})
        </h4>
        <table>
            <thead>
                <tr>
                    <th class="arabic-text">{{ trans('employee_review.Task') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Project') }}</th>
                    <th class="text-center arabic-text">{{ trans('employee_review.Bounces') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $tasksWithBouncesArray = is_array($tasks['tasksWithBounces']) ? $tasks['tasksWithBounces'] : $tasks['tasksWithBounces']->toArray();
                @endphp
                @foreach(array_slice($tasksWithBouncesArray, 0, 8) as $task)
                <tr>
                    <td class="font-bold arabic-text">{{ Str::limit($task['title'], 35) }}</td>
                    <td class="text-secondary arabic-text">{{ Str::limit($task['projectTitle'] ?? 'N/A', 20) }}</td>
                    <td class="text-center text-danger font-bold">{{ $task['reopenCount'] }}</td>
                    <td>
                        @php
                            $statusKey = strtolower(str_replace(' ', '_', $task['status']));
                            $translatedStatus = trans('enums.' . $statusKey);
                            if ($translatedStatus === 'enums.' . $statusKey) $translatedStatus = $task['status'];
                        @endphp
                        <span class="badge bg-warning arabic-text">{{ $translatedStatus }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($points['transactions']) && count($points['transactions']) > 0)
    <h3 class="arabic-text" style="font-size: 14px; margin-bottom: 10px; margin-top: 20px;">{{ trans('employee_review.Recent Point Transactions') }}</h3>
    <div class="card" style="padding: 0; overflow: hidden;">
        <table>
            <thead style="background: #f1f5f9;">
                <tr>
                    <th class="arabic-text">{{ trans('employee_review.Date') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Type') }}</th>
                    <th class="arabic-text">{{ trans('employee_review.Reason') }}</th>
                    <th class="text-center arabic-text">{{ trans('employee_review.Points') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $transactions = is_array($points['transactions']) ? $points['transactions'] : $points['transactions']->toArray();
                    $limitedTransactions = array_slice($transactions, 0, 15);
                @endphp
                @foreach($limitedTransactions as $transaction)
                <tr>
                    <td style="width: 15%;">{{ \Carbon\Carbon::parse($transaction['createdAt'])->format('M j, Y') }}</td>
                    <td style="width: 20%;">
                        @php
                            $transactionType = strtolower(str_replace(' ', '_', $transaction['type']));
                            $translatedType = trans('enums.' . $transactionType);
                            if ($translatedType === 'enums.' . $transactionType) $translatedType = $transaction['type'];
                        @endphp
                        <span class="arabic-text" style="font-size: 10px; font-weight: 600;">{{ $translatedType }}</span>
                    </td>
                    <td style="width: 50%; color: var(--secondary);" class="arabic-text">{{ $transaction['reason'] ?? trans('employee_review.N/A') }}</td>
                    <td style="width: 15%;" class="text-center font-bold {{ $transaction['points'] >= 0 ? 'text-success' : 'text-danger' }}">
                        {{ $transaction['points'] >= 0 ? '+' : '' }}{{ $transaction['points'] }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="card" style="background: #f8fafc; border: 1px dashed var(--border);">
        <h4 class="arabic-text" style="margin: 0 0 10px 0; color: var(--primary);">{{ trans('employee_review.Management Recommendations') }}</h4>
        
        @php
            $totalImpact = $overview['netPointsChange'] - ($deductions['totalDeductions'] ?? 0);
        @endphp

        @if($totalImpact > 20)
            <div class="flex text-success font-bold arabic-text">
                <svg class="icon" viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
                {{ trans('employee_review.Bonus Consideration') }}
            </div>
            <p class="text-secondary arabic-text" style="margin: 5px 0 0 28px;">{{ trans('employee_review.Strong positive performance warrants bonus or salary increase consideration.') }}</p>
        @elseif($totalImpact < -10)
             <div class="flex text-danger font-bold arabic-text">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                {{ trans('employee_review.Action Required') }}
            </div>
            <p class="text-secondary arabic-text" style="margin: 5px 0 0 28px;">{{ trans('employee_review.Significant negative point balance warrants salary adjustment discussion.') }}</p>
        @else
            <div class="flex text-secondary font-bold arabic-text">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                {{ trans('employee_review.Stable Performance') }}
            </div>
            <p class="text-secondary arabic-text" style="margin: 5px 0 0 28px;">{{ trans('employee_review.Performance is stable. Continue monitoring.') }}</p>
        @endif
    </div>

</body>
</html>