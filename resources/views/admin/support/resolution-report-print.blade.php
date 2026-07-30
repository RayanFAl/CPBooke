<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Support Resolution Report · {{ $ticket['ticket_number'] ?? ('Ticket #'.$ticket['id']) }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.5;
            color: #0f172a;
        }

        body {
            margin: 0;
            padding: 32px;
            background: #f8fafc;
        }

        .sheet {
            max-width: 920px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .toolbar button {
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: #fff;
            padding: 10px 18px;
            font-size: 14px;
            cursor: pointer;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }

        .meta {
            color: #64748b;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            background: #f8fafc;
        }

        .card h2 {
            margin: 0 0 12px;
            font-size: 13px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }

        .card p,
        .card dd,
        .card dt {
            margin: 0;
            font-size: 14px;
        }

        .card dl {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 8px 12px;
            margin: 0;
        }

        .section {
            margin-top: 24px;
            border-top: 1px solid #e2e8f0;
            padding-top: 24px;
        }

        .section h2 {
            margin: 0 0 12px;
            font-size: 18px;
        }

        .section p {
            margin: 0;
            white-space: pre-line;
            color: #334155;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-top: 24px;
        }

        .stat {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
        }

        .stat span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .stat strong {
            display: block;
            margin-top: 8px;
            font-size: 16px;
        }

        @media print {
            body {
                padding: 0;
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                border: 0;
                border-radius: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <div class="meta">Generated {{ $generated_at }}</div>
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>

        <header>
            <h1>Support Resolution Report</h1>
            <p class="meta">
                Ticket {{ $ticket['ticket_number'] ?? ('#'.$ticket['id']) }}
                · {{ $ticket['subject'] ?? 'No subject' }}
            </p>
        </header>

        <div class="stats">
            <div class="stat">
                <span>Resolution Type</span>
                <strong>{{ ucwords(str_replace('_', ' ', $report['resolution_type'])) }}</strong>
            </div>
            <div class="stat">
                <span>Status After</span>
                <strong>{{ ucwords(str_replace('_', ' ', $report['status_after'])) }}</strong>
            </div>
            <div class="stat">
                <span>Handling Minutes</span>
                <strong>{{ $report['handling_minutes'] }}</strong>
            </div>
            <div class="stat">
                <span>Reopened Count</span>
                <strong>{{ $report['reopened_count'] }}</strong>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h2>Ticket</h2>
                <dl>
                    <dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $ticket['status'])) }}</dd>
                    <dt>Priority</dt><dd>{{ ucwords(str_replace('_', ' ', $ticket['priority'])) }}</dd>
                    <dt>Category</dt><dd>{{ ucwords(str_replace('_', ' ', $ticket['category'])) }}</dd>
                    <dt>Created</dt><dd>{{ $ticket['created_at'] ?? 'N/A' }}</dd>
                    <dt>Resolved</dt><dd>{{ $report['resolved_at'] ?? 'N/A' }}</dd>
                </dl>
            </div>

            <div class="card">
                <h2>Customer</h2>
                <dl>
                    <dt>Name</dt><dd>{{ $ticket['customer']['name'] ?? 'N/A' }}</dd>
                    <dt>Email</dt><dd>{{ $ticket['customer']['email'] ?? 'N/A' }}</dd>
                    <dt>Phone</dt><dd>{{ $ticket['customer']['phone'] ?? 'N/A' }}</dd>
                    <dt>Country</dt><dd>{{ $ticket['customer']['country'] ?? 'N/A' }}</dd>
                </dl>
            </div>

            <div class="card">
                <h2>Agent</h2>
                <dl>
                    <dt>Name</dt><dd>{{ $report['agent']['name'] ?? ($ticket['assignee']['name'] ?? 'System') }}</dd>
                    <dt>Email</dt><dd>{{ $report['agent']['email'] ?? ($ticket['assignee']['email'] ?? 'N/A') }}</dd>
                    <dt>Escalated</dt><dd>{{ $report['escalated'] ? 'Yes' : 'No' }}</dd>
                    <dt>Satisfaction Requested</dt><dd>{{ $report['satisfaction_requested'] ? 'Yes' : 'No' }}</dd>
                </dl>
            </div>

            <div class="card">
                <h2>Order</h2>
                @if ($ticket['order'])
                    <dl>
                        <dt>Reference</dt><dd>{{ $ticket['order']['booking_reference'] ?? 'N/A' }}</dd>
                        <dt>External ID</dt><dd>{{ $ticket['order']['external_booking_id'] ?? 'N/A' }}</dd>
                        <dt>Status</dt><dd>{{ ucwords(str_replace('_', ' ', $ticket['order']['status'])) }}</dd>
                        <dt>Total</dt><dd>{{ $ticket['order']['total_amount'] }} {{ $ticket['order']['currency'] }}</dd>
                    </dl>
                @else
                    <p>No linked order.</p>
                @endif
            </div>
        </div>

        <div class="section">
            <h2>Root Cause</h2>
            <p>{{ $report['root_cause'] ?: 'Not available' }}</p>
        </div>

        <div class="section">
            <h2>Actions Taken</h2>
            <p>{{ $report['actions_taken'] ?: 'Not available' }}</p>
        </div>

        <div class="section">
            <h2>Resolution Summary</h2>
            <p>{{ $report['resolution_summary'] ?: 'Not available' }}</p>
        </div>

        <div class="section">
            <h2>Customer Visible Notes</h2>
            <p>{{ $report['customer_visible_notes'] ?: 'Not available' }}</p>
        </div>

        <div class="section">
            <h2>Internal Notes</h2>
            <p>{{ $report['internal_notes'] ?: 'Not available' }}</p>
        </div>
    </div>
</body>
</html>
