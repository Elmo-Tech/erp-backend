<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ trans('employee_review.Employee Performance Review') }}</title>
    <style>
        /* Use DejaVu Sans for all languages - it has excellent Arabic support */
        body {
            font-family: "DejaVu Sans", "Arial Unicode MS", sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
            direction: {{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }};
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #2563eb;
            font-size: 24px;
            margin: 0;
            font-weight: 700;
        }
        
        .employee-info {
            background-color: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        
        .employee-info h2 {
            color: #1e40af;
            font-size: 16px;
            margin: 0 0 10px 0;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            color: #374151;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section h3 {
            background-color: #2563eb;
            color: white;
            padding: 8px 12px;
            margin: 0 0 15px 0;
            font-size: 14px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .metric-card {
            background-color: #f1f5f9;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border-{{ app()->getLocale() == 'ar' ? 'right' : 'left' }}: 4px solid #2563eb;
        }
        
        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .metric-label {
            font-size: 11px;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .progress-bar {
            background-color: #e2e8f0;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .progress-fill {
            background-color: #10b981;
            height: 100%;
            transition: width 0.3s ease;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .transactions-table th,
        .transactions-table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }};
            font-size: 11px;
        }
        
        .transactions-table th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        
        .positive-points {
            color: #059669;
            font-weight: bold;
        }
        
        .negative-points {
            color: #dc2626;
            font-weight: bold;
        }
        
        .warning-points {
            color: #d97706;
            font-weight: bold;
        }
        
        .grace-period-info {
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .grace-period-expired {
            background-color: #fee2e2;
            border: 1px solid #ef4444;
        }
        
        .grace-period-active {
            background-color: #d1fae5;
            border: 1px solid #10b981;
        }
        
        .deduction-info {
            background-color: #fef2f2;
            border: 1px solid #f87171;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #d1d5db;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ trans('employee_review.Employee Performance Review') }}</h1>
        <p>{{ trans('employee_review.Generated on') }} {{ date('F j, Y') }}</p>
    </div>

    <div class="employee-info">
        <h2>{{ trans('employee_review.Employee Information') }}</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Name') }}:</span> {{ $employee['name'] }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Email') }}:</span> {{ $employee['email'] }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Specialization') }}:</span> 
                {{ $employee['specialization'] ? trans('enums.' . $employee['specialization']->value) : trans('employee_review.N/A') }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Current Points') }}:</span> {{ $employee['totalPoints'] }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Review Period') }}:</span> 
                {{ $period['dateFrom'] ?? trans('employee_review.All time') }} {{ trans('employee_review.to') }} {{ $period['dateTo'] ?? trans('employee_review.Present') }}
            </div>
        </div>
    </div>

    {{-- Grace Period & Deduction Information --}}
    @if(isset($gracePeriod))
    <div class="grace-period-info {{ $gracePeriod['isExpired'] ? 'grace-period-expired' : 'grace-period-active' }}">
        <h3 style="margin: 0 0 10px 0; color: {{ $gracePeriod['isExpired'] ? '#dc2626' : '#059669' }};">
            {{ trans('employee_review.Grace Period Status') }}
        </h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Grace Period Days') }}:</span> {{ $gracePeriod['allowedDays'] }} {{ trans('employee_review.days') }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Days Used') }}:</span> {{ $gracePeriod['daysUsed'] }} {{ trans('employee_review.days') }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Remaining Days') }}:</span> 
                <span class="{{ $gracePeriod['remainingDays'] <= 0 ? 'negative-points' : 'positive-points' }}">
                    {{ max(0, $gracePeriod['remainingDays']) }} {{ trans('employee_review.days') }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Status') }}:</span> 
                <span class="{{ $gracePeriod['isExpired'] ? 'negative-points' : 'positive-points' }}">
                    {{ $gracePeriod['isExpired'] ? trans('employee_review.Expired') : trans('employee_review.Active') }}
                </span>
            </div>
        </div>
        @if($gracePeriod['isExpired'])
        <p style="margin: 10px 0 0 0; color: #dc2626; font-weight: bold;">
            {{ trans('employee_review.⚠️ Grace period has expired. Deductions may apply for late attendance.') }}
        </p>
        @endif
    </div>
    @endif

    {{-- Deduction Information --}}
    @if(isset($deductions) && !empty($deductions['totalDeductions']))
    <div class="deduction-info">
        <h3 style="margin: 0 0 10px 0; color: #dc2626;">{{ trans('employee_review.Attendance Deductions') }}</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Total Late Minutes') }}:</span> 
                <span class="negative-points">{{ $deductions['totalLateMinutes'] }} {{ trans('employee_review.minutes') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Deduction Rate') }}:</span> 
                {{ $deductions['deductionRate'] }}{{ trans('employee_review./minute') }}
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Total Deductions') }}:</span> 
                <span class="negative-points">-{{ $deductions['totalDeductions'] }} {{ trans('employee_review.points') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Days with Deductions') }}:</span> 
                {{ $deductions['daysWithDeductions'] }} {{ trans('employee_review.days') }}
            </div>
        </div>
        
        @if(!empty($deductions['dailyDeductions']) && count($deductions['dailyDeductions']) > 0)
        <h4 style="margin: 15px 0 10px 0; color: #dc2626; font-size: 12px;">{{ trans('employee_review.Daily Deduction Breakdown') }}</h4>
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>{{ trans('employee_review.Date') }}</th>
                    <th>{{ trans('employee_review.Clock In') }}</th>
                    <th>{{ trans('employee_review.Late Minutes') }}</th>
                    <th>{{ trans('employee_review.Points Deducted') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($deductions['dailyDeductions'], 0, 10) as $dailyDeduction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($dailyDeduction['date'])->format('M j, Y') }}</td>
                    <td>{{ $dailyDeduction['clockIn'] }}</td>
                    <td class="warning-points">{{ $dailyDeduction['lateMinutes'] }}</td>
                    <td class="negative-points">-{{ $dailyDeduction['deduction'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif

    {{-- Work Quality Assessment --}}
    @if(isset($tasks) && !empty($tasks['tasksWithBounces']) && count($tasks['tasksWithBounces']) > 0)
    <div class="section">
        <h3>{{ trans('employee_review.Work Quality Assessment') }}</h3>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Tasks with Issues') }}:</span> 
                <span class="{{ count($tasks['tasksWithBounces']) > 3 ? 'negative-points' : 'warning-points' }}">
                    {{ count($tasks['tasksWithBounces']) }} {{ trans('employee_review.tasks') }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Total Bounces') }}:</span> 
                <span class="{{ $tasks['totalBounces'] > 5 ? 'negative-points' : 'warning-points' }}">
                    {{ $tasks['totalBounces'] }} {{ trans('employee_review.bounces') }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Quality Score') }}:</span> 
                @php
                    $qualityScore = $tasks['total'] > 0 ? (($tasks['total'] - count($tasks['tasksWithBounces'])) / $tasks['total']) * 100 : 100;
                @endphp
                <span class="{{ $qualityScore >= 80 ? 'positive-points' : ($qualityScore >= 60 ? 'warning-points' : 'negative-points') }}">
                    {{ number_format($qualityScore, 1) }}%
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">{{ trans('employee_review.Average Bounce Rate') }}:</span> 
                <span class="{{ $overview['averageBounceRate'] <= 0.5 ? 'positive-points' : ($overview['averageBounceRate'] <= 1 ? 'warning-points' : 'negative-points') }}">
                    {{ $overview['averageBounceRate'] }}
                </span>
            </div>
        </div>
        
        <h4 style="margin: 15px 0 10px 0; color: #d97706; font-size: 12px;">{{ trans('employee_review.Tasks Requiring Rework') }}</h4>
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>{{ trans('employee_review.Task') }}</th>
                    <th>{{ trans('employee_review.Project') }}</th>
                    <th>{{ trans('employee_review.Bounces') }}</th>
                    <th>{{ trans('employee_review.Status') }}</th>
                    <th>{{ trans('employee_review.Time Spent') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($tasks['tasksWithBounces'], 0, 8) as $task)
                <tr>
                    <td>{{ Str::limit($task['title'], 30) }}</td>
                    <td>{{ Str::limit($task['projectTitle'] ?? 'N/A', 20) }}</td>
                    <td class="negative-points">{{ $task['reopenCount'] }}</td>
                    <td>
                        @php
                            $statusKey = strtolower(str_replace(' ', '_', $task['status']));
                            $translatedStatus = trans('enums.' . $statusKey);
                            if ($translatedStatus === 'enums.' . $statusKey) {
                                $translatedStatus = $task['status'];
                            }
                        @endphp
                        {{ $translatedStatus }}
                    </td>
                    <td>{{ $task['totalTimeSpent'] }}h</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="section">
        <h3>{{ trans('employee_review.Performance Overview') }}</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['totalTrackedHours'], 1) }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Tracked Hours') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['totalIdleHours'], 1) }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Idle Hours') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['taskCompletionRate'], 1) }}%</div>
                <div class="metric-label">{{ trans('employee_review.Task Completion Rate') }}</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $overview['taskCompletionRate'] }}%"></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['averageBounceRate'], 2) }}</div>
                <div class="metric-label">{{ trans('employee_review.Average Bounce Rate') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $overview['attendanceDays'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Attendance Days') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['productivityScore'], 1) }}</div>
                <div class="metric-label">{{ trans('employee_review.Productivity Score') }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>{{ trans('employee_review.Points Summary') }}</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value positive-points">+{{ $overview['pointsEarned'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Points Earned') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value negative-points">-{{ $overview['pointsDeducted'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Points Deducted') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value {{ $overview['netPointsChange'] >= 0 ? 'positive-points' : 'negative-points' }}">
                    {{ $overview['netPointsChange'] >= 0 ? '+' : '' }}{{ $overview['netPointsChange'] }}
                </div>
                <div class="metric-label">{{ trans('employee_review.Net Points Change') }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>{{ trans('employee_review.Tasks Summary') }}</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['total'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Tasks') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['completed'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Completed Tasks') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['inProgress'] }}</div>
                <div class="metric-label">{{ trans('employee_review.In Progress Tasks') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['totalBounces'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Bounces') }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>{{ trans('employee_review.Attendance Summary') }}</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['totalDays'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Days') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['lateDays'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Late Days') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['totalLateMinutes'] }}</div>
                <div class="metric-label">{{ trans('employee_review.Total Late Minutes') }}</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($attendance['averageIdlePerDay'], 1) }}</div>
                <div class="metric-label">{{ trans('employee_review.Avg Idle Per Day (min)') }}</div>
            </div>
        </div>
    </div>

    @if(!empty($points['transactions']) && count($points['transactions']) > 0)
    <div class="section page-break">
        <h3>{{ trans('employee_review.Recent Point Transactions') }}</h3>
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>{{ trans('employee_review.Date') }}</th>
                    <th>{{ trans('employee_review.Points') }}</th>
                    <th>{{ trans('employee_review.Type') }}</th>
                    <th>{{ trans('employee_review.Reason') }}</th>
                    <th>{{ trans('employee_review.Created By') }}</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $transactions = is_array($points['transactions']) ? $points['transactions'] : $points['transactions']->toArray();
                @endphp
                @foreach(array_slice($transactions, 0, 15) as $transaction)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($transaction['createdAt'])->format('M j, Y') }}</td>
                    <td class="{{ $transaction['points'] >= 0 ? 'positive-points' : 'negative-points' }}">
                        {{ $transaction['points'] >= 0 ? '+' : '' }}{{ $transaction['points'] }}
                    </td>
                    <td>
                        @php
                            $transactionType = strtolower(str_replace(' ', '_', $transaction['type']));
                            $translatedType = trans('enums.' . $transactionType);
                            // Fallback if translation not found
                            if ($translatedType === 'enums.' . $transactionType) {
                                $translatedType = $transaction['type'];
                            }
                        @endphp
                        {{ $translatedType }}
                    </td>
                    <td>{{ $transaction['reason'] ?? trans('employee_review.N/A') }}</td>
                    <td>{{ $transaction['createdBy'] ?? trans('employee_review.System') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Management Assessment Section --}}
    <div class="section page-break">
        <h3>{{ trans('employee_review.Management Assessment & Recommendations') }}</h3>
        
        {{-- Overall Performance Rating --}}
        <div class="grace-period-info {{ $overview['productivityScore'] >= 80 ? 'grace-period-active' : ($overview['productivityScore'] >= 60 ? '' : 'grace-period-expired') }}">
            <h4 style="margin: 0 0 10px 0; color: {{ $overview['productivityScore'] >= 80 ? '#059669' : ($overview['productivityScore'] >= 60 ? '#d97706' : '#dc2626') }};">
                {{ trans('employee_review.Overall Performance Rating') }}
            </h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Productivity Score') }}:</span> 
                    <span class="{{ $overview['productivityScore'] >= 80 ? 'positive-points' : ($overview['productivityScore'] >= 60 ? 'warning-points' : 'negative-points') }}">
                        {{ $overview['productivityScore'] }}/100
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Performance Level') }}:</span> 
                    @php
                        $performanceLevel = $overview['productivityScore'] >= 90 ? 'Excellent' : 
                                          ($overview['productivityScore'] >= 80 ? 'Good' : 
                                          ($overview['productivityScore'] >= 70 ? 'Satisfactory' : 
                                          ($overview['productivityScore'] >= 60 ? 'Needs Improvement' : 'Poor')));
                        $performanceLevelKey = strtolower(str_replace(' ', '_', $performanceLevel));
                    @endphp
                    <span class="{{ $overview['productivityScore'] >= 80 ? 'positive-points' : ($overview['productivityScore'] >= 60 ? 'warning-points' : 'negative-points') }}">
                        {{ trans('employee_review.' . $performanceLevelKey, [], 'en') !== 'employee_review.' . $performanceLevelKey ? trans('employee_review.' . $performanceLevelKey) : $performanceLevel }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Attendance Rating') }}:</span> 
                    @php
                        $latePercentage = $attendance['totalDays'] > 0 ? ($attendance['lateDays'] / $attendance['totalDays']) * 100 : 0;
                        $attendanceRating = $latePercentage <= 5 ? 'Excellent' : 
                                          ($latePercentage <= 10 ? 'Good' : 
                                          ($latePercentage <= 20 ? 'Fair' : 'Poor'));
                    @endphp
                    <span class="{{ $latePercentage <= 10 ? 'positive-points' : ($latePercentage <= 20 ? 'warning-points' : 'negative-points') }}">
                        {{ $attendanceRating }} ({{ number_format($latePercentage, 1) }}% {{ trans('employee_review.late days') }})
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Work Quality') }}:</span> 
                    @php
                        $qualityRating = $overview['averageBounceRate'] <= 0.3 ? 'Excellent' : 
                                       ($overview['averageBounceRate'] <= 0.7 ? 'Good' : 
                                       ($overview['averageBounceRate'] <= 1.2 ? 'Fair' : 'Poor'));
                    @endphp
                    <span class="{{ $overview['averageBounceRate'] <= 0.7 ? 'positive-points' : ($overview['averageBounceRate'] <= 1.2 ? 'warning-points' : 'negative-points') }}">
                        {{ $qualityRating }} ({{ $overview['averageBounceRate'] }} {{ trans('employee_review.avg bounces') }})
                    </span>
                </div>
            </div>
        </div>

        {{-- Financial Impact Assessment --}}
        <div class="deduction-info" style="margin-top: 20px;">
            <h4 style="margin: 0 0 10px 0; color: #dc2626;">{{ trans('employee_review.Financial Impact Assessment') }}</h4>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Points Balance Impact') }}:</span> 
                    <span class="{{ $overview['netPointsChange'] >= 0 ? 'positive-points' : 'negative-points' }}">
                        {{ $overview['netPointsChange'] >= 0 ? '+' : '' }}{{ $overview['netPointsChange'] }} {{ trans('employee_review.points') }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Attendance Deductions') }}:</span> 
                    <span class="negative-points">
                        -{{ $deductions['totalDeductions'] ?? 0 }} {{ trans('employee_review.points') }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Recommended Action') }}:</span> 
                    @php
                        $totalImpact = $overview['netPointsChange'] - ($deductions['totalDeductions'] ?? 0);
                        $recommendedAction = $totalImpact >= 10 ? 'Bonus Consideration' : 
                                           ($totalImpact >= 0 ? 'No Action Needed' : 
                                           ($totalImpact >= -10 ? 'Warning' : 'Salary Deduction'));
                    @endphp
                    <span class="{{ $totalImpact >= 0 ? 'positive-points' : ($totalImpact >= -10 ? 'warning-points' : 'negative-points') }}">
                        {{ $recommendedAction }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">{{ trans('employee_review.Total Financial Impact') }}:</span> 
                    <span class="{{ $totalImpact >= 0 ? 'positive-points' : 'negative-points' }}">
                        {{ $totalImpact >= 0 ? '+' : '' }}{{ $totalImpact }} {{ trans('employee_review.points') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Detailed Recommendations --}}
        <div style="margin-top: 20px; padding: 15px; background-color: #f8fafc; border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #1e40af;">{{ trans('employee_review.Management Recommendations') }}</h4>
            
            @if($overview['productivityScore'] >= 85)
                <p style="color: #059669; margin: 5px 0;">
                    <strong>✓ {{ trans('employee_review.High Performer') }}:</strong> {{ trans('employee_review.Consider for promotion or additional responsibilities. Excellent work quality and attendance.') }}
                </p>
            @elseif($overview['productivityScore'] >= 70)
                <p style="color: #d97706; margin: 5px 0;">
                    <strong>⚠ {{ trans('employee_review.Average Performer') }}:</strong> {{ trans('employee_review.Provide additional training and support. Monitor progress closely.') }}
                </p>
            @else
                <p style="color: #dc2626; margin: 5px 0;">
                    <strong>⚠ {{ trans('employee_review.Needs Improvement') }}:</strong> {{ trans('employee_review.Immediate intervention required. Consider performance improvement plan.') }}
                </p>
            @endif

            @if(isset($gracePeriod) && $gracePeriod['isExpired'])
                <p style="color: #dc2626; margin: 5px 0;">
                    <strong>⚠ {{ trans('employee_review.Attendance Issue') }}:</strong> {{ trans('employee_review.Grace period exceeded. Implement attendance improvement plan.') }}
                </p>
            @endif

            @if($overview['averageBounceRate'] > 1.0)
                <p style="color: #dc2626; margin: 5px 0;">
                    <strong>⚠ {{ trans('employee_review.Quality Concern') }}:</strong> {{ trans('employee_review.High rework rate indicates need for additional training or mentoring.') }}
                </p>
            @endif

            @if($overview['netPointsChange'] < -20)
                <p style="color: #dc2626; margin: 5px 0;">
                    <strong>💰 {{ trans('employee_review.Salary Deduction Recommended') }}:</strong> {{ trans('employee_review.Significant negative point balance warrants salary adjustment discussion.') }}
                </p>
            @elseif($overview['netPointsChange'] > 20)
                <p style="color: #059669; margin: 5px 0;">
                    <strong>💰 {{ trans('employee_review.Bonus Consideration') }}:</strong> {{ trans('employee_review.Strong positive performance warrants bonus or salary increase consideration.') }}
                </p>
            @endif
        </div>
    </div>

    <div class="footer">
        <p>{{ trans('employee_review.This report was generated automatically by the Business OS system.') }}</p>
        <p>{{ trans('employee_review.Report ID') }}: {{ uniqid() }} | {{ trans('employee_review.Generated at') }} {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>