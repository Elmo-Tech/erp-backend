<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Performance Review</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
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
            border-left: 4px solid #2563eb;
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
            text-align: left;
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
        <h1>Employee Performance Review</h1>
        <p>Generated on {{ date('F j, Y') }}</p>
    </div>

    <div class="employee-info">
        <h2>Employee Information</h2>
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Name:</span> {{ $employee['name'] }}
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span> {{ $employee['email'] }}
            </div>
            <div class="info-item">
                <span class="info-label">Specialization:</span> 
                {{ $employee['specialization'] ? $employee['specialization']->value : 'N/A' }}
            </div>
            <div class="info-item">
                <span class="info-label">Current Points:</span> {{ $employee['totalPoints'] }}
            </div>
            <div class="info-item">
                <span class="info-label">Review Period:</span> 
                {{ $period['dateFrom'] ?? 'All time' }} to {{ $period['dateTo'] ?? 'Present' }}
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Performance Overview</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['totalTrackedHours'], 1) }}</div>
                <div class="metric-label">Total Tracked Hours</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['totalIdleHours'], 1) }}</div>
                <div class="metric-label">Total Idle Hours</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['taskCompletionRate'], 1) }}%</div>
                <div class="metric-label">Task Completion Rate</div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: {{ $overview['taskCompletionRate'] }}%"></div>
                </div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['averageBounceRate'], 2) }}</div>
                <div class="metric-label">Average Bounce Rate</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $overview['attendanceDays'] }}</div>
                <div class="metric-label">Attendance Days</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($overview['productivityScore'], 1) }}</div>
                <div class="metric-label">Productivity Score</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Points Summary</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value positive-points">+{{ $overview['pointsEarned'] }}</div>
                <div class="metric-label">Points Earned</div>
            </div>
            <div class="metric-card">
                <div class="metric-value negative-points">-{{ $overview['pointsDeducted'] }}</div>
                <div class="metric-label">Points Deducted</div>
            </div>
            <div class="metric-card">
                <div class="metric-value {{ $overview['netPointsChange'] >= 0 ? 'positive-points' : 'negative-points' }}">
                    {{ $overview['netPointsChange'] >= 0 ? '+' : '' }}{{ $overview['netPointsChange'] }}
                </div>
                <div class="metric-label">Net Points Change</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Tasks Summary</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['total'] }}</div>
                <div class="metric-label">Total Tasks</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['completed'] }}</div>
                <div class="metric-label">Completed Tasks</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['inProgress'] }}</div>
                <div class="metric-label">In Progress Tasks</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $tasks['totalBounces'] }}</div>
                <div class="metric-label">Total Bounces</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>Attendance Summary</h3>
        <div class="metrics-grid">
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['totalDays'] }}</div>
                <div class="metric-label">Total Days</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['lateDays'] }}</div>
                <div class="metric-label">Late Days</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ $attendance['totalLateMinutes'] }}</div>
                <div class="metric-label">Total Late Minutes</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">{{ number_format($attendance['averageIdlePerDay'], 1) }}</div>
                <div class="metric-label">Avg Idle Per Day (min)</div>
            </div>
        </div>
    </div>

    @if(!empty($points['transactions']) && count($points['transactions']) > 0)
    <div class="section page-break">
        <h3>Recent Point Transactions</h3>
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Points</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Created By</th>
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
                    <td>{{ ucfirst(str_replace('_', ' ', $transaction['type'])) }}</td>
                    <td>{{ $transaction['reason'] ?? 'N/A' }}</td>
                    <td>{{ $transaction['createdBy'] ?? 'System' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically by the Business OS system.</p>
        <p>Report ID: {{ uniqid() }} | Generated at {{ date('Y-m-d H:i:s') }}</p>
    </div>
</body>
</html>