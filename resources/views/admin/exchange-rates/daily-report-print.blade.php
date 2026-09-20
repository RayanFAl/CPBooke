<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily FX Buy + Sales · {{ $report['report_date'] }}</title>
    <style>
        :root { color-scheme: light; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #0f172a; }
        body { margin: 0; padding: 32px; background: #e2e8f0; }
        .sheet { max-width: 1024px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 28px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .toolbar span { color: #64748b; font-size: 13px; }
        .toolbar button { border: 0; border-radius: 999px; background: #0f172a; color: #fff; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
        h1 { margin: 0; font-size: 24px; }
        .meta { margin: 8px 0 20px; color: #64748b; font-size: 13px; }
        .summary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary div { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .summary dt { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .summary dd { margin: 4px 0 0; font-size: 18px; font-weight: 600; }
        h2 { margin: 24px 0 12px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: left; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 8px 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; vertical-align: top; }
        .footer { margin-top: 20px; color: #64748b; font-size: 12px; }
        @media print {
            body { padding: 0; background: #fff; }
            .toolbar { display: none; }
            .sheet { border: 0; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <span>Generated {{ $generated_at }} ({{ $report['timezone'] }})</span>
            <button type="button" onclick="window.print()">Print / Save PDF · طباعة</button>
        </div>

        <h1>{{ $company }}</h1>
        <p class="meta">
            Daily FX buy + sales report · تقرير يومي لأسعار الشراء والمبيعات
            · {{ $report['report_date'] }}
        </p>

        <dl class="summary">
            <div>
                <dt>USD buy</dt>
                <dd>{{ $report['usd_buy_rate'] }} LYD</dd>
            </div>
            <div>
                <dt>EUR buy</dt>
                <dd>{{ $report['eur_buy_rate'] }} LYD</dd>
            </div>
            <div>
                <dt>Paid orders</dt>
                <dd>{{ $report['totals']['orders_count'] }}</dd>
            </div>
            <div>
                <dt>Sales</dt>
                <dd style="font-size: 13px; line-height: 1.4;">{{ $report['sales_summary'] }}</dd>
            </div>
        </dl>

        <h2>Sales by currency · المبيعات حسب العملة</h2>
        <table>
            <thead>
                <tr>
                    <th>Currency</th>
                    <th>Orders</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['sales_by_currency'] as $row)
                    <tr>
                        <td>{{ $row['currency'] }}</td>
                        <td>{{ $row['orders_count'] }}</td>
                        <td>{{ $row['total_amount'] }} {{ $row['currency'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">No paid orders for this day.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <h2>Orders · الحجوزات</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Service</th>
                    <th>Amount</th>
                    <th>Method</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['orders'] as $order)
                    <tr>
                        <td>{{ $order['id'] }}</td>
                        <td>{{ $order['booking_reference'] ?: '—' }}</td>
                        <td>{{ $order['customer_name'] }}</td>
                        <td>{{ $order['service_type'] }}</td>
                        <td>{{ $order['amount'] }} {{ $order['currency'] }}</td>
                        <td>{{ $order['payment_method'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">No paid orders for this day.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="footer">{{ $report['summary_line'] }}</p>
    </div>
</body>
</html>
