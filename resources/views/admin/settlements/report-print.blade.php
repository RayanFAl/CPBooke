<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settlement #{{ $settlement['id'] }} · {{ $settlement['provider_name'] }}</title>
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
        .panel { border: 1px solid #bae6fd; background: #f0f9ff; border-radius: 8px; padding: 12px; margin-bottom: 20px; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { text-align: left; font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 8px 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; vertical-align: top; }
        .warn { color: #b45309; font-weight: 600; }
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
            <span>Generated {{ $generated_at }}</span>
            <button type="button" onclick="window.print()">Print / Save PDF · طباعة</button>
        </div>

        <h1>{{ $company }}</h1>
        <p class="meta">
            Settlement report · تقرير التسوية
            · {{ $settlement['provider_name'] }}
            · {{ $settlement['period_start'] }} → {{ $settlement['period_end'] }}
            · {{ $settlement['status'] }}
        </p>

        <dl class="summary">
            <div>
                <dt>Booke cost</dt>
                <dd>{{ $settlement['expected_cost'] }} {{ $settlement['currency'] }}</dd>
            </div>
            <div>
                <dt>Invoice total</dt>
                <dd>{{ $settlement['supplier_invoice_total'] ?: '—' }} {{ $settlement['currency'] }}</dd>
            </div>
            <div>
                <dt>Difference</dt>
                <dd class="{{ (float) $settlement['difference'] !== 0.0 ? 'warn' : '' }}">{{ $settlement['difference'] }} {{ $settlement['currency'] }}</dd>
            </div>
            <div>
                <dt>Need review</dt>
                <dd>{{ $settlement['review_count'] }}</dd>
            </div>
        </dl>

        @if (! empty($portal_wallets['wallets']))
            <div class="panel">
                <strong>Supplier portal balance (reference) · رصيد بوابة المزوّد (مرجعي)</strong>
                @foreach ($portal_wallets['wallets'] as $wallet)
                    · {{ $wallet['currency'] }}: {{ $wallet['balance'] }}
                @endforeach
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Booke cost</th>
                    <th>Invoice</th>
                    <th>Difference</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>{{ $item['booking_reference'] ?: '—' }}</td>
                        <td>{{ $item['supplier_cost'] ?? $item['wallet_debit'] ?? '—' }}</td>
                        <td>{{ $item['supplier_invoice_cost'] ?? '—' }}</td>
                        <td class="{{ (float) ($item['difference'] ?? 0) !== 0.0 ? 'warn' : '' }}">{{ $item['difference'] ?? '—' }}</td>
                        <td>{{ $item['status'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No line items. · لا توجد بنود.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="footer">
            {{ count($items) }} line item(s). Supplier portal balances are reference-only and fetched at print time when available.
            <br>
            أرصدة بوابة المزوّد للمرجعية فقط.
        </p>
    </div>
</body>
</html>
