<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provider Ledger · {{ $wallet['provider_name'] }}</title>
    <style>
        :root { color-scheme: light; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #0f172a; }
        body { margin: 0; padding: 32px; background: #e2e8f0; }
        .sheet { max-width: 960px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 28px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .toolbar span { color: #64748b; font-size: 13px; }
        .toolbar button { border: 0; border-radius: 999px; background: #0f172a; color: #fff; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
        h1 { margin: 0; font-size: 24px; }
        .meta { margin: 8px 0 20px; color: #64748b; font-size: 13px; }
        .summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
        .summary div { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        .summary dt { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        .summary dd { margin: 4px 0 0; font-size: 18px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 8px 6px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 10px 6px; vertical-align: top; }
        .credit { color: #047857; font-weight: 600; }
        .debit { color: #be123c; font-weight: 600; }
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
            Provider ledger statement · كشف الرصيد الدفتري للمزوّد
            · {{ $wallet['provider_name'] }}
            · {{ $wallet['currency'] }}
        </p>

        <dl class="summary">
            <div>
                <dt>Current balance · الرصيد الحالي</dt>
                <dd>{{ $wallet['balance'] }} {{ $wallet['currency'] }}</dd>
            </div>
            <div>
                <dt>Credit limit · حد الائتمان</dt>
                <dd>{{ number_format((float) $wallet['credit_limit'], 2, '.', '') }} {{ $wallet['currency'] }}</dd>
            </div>
        </dl>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance after</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>{{ $transaction['created_at'] }}</td>
                        <td>{{ $transaction['type_label'] }}</td>
                        <td class="{{ $transaction['is_debit'] ? 'debit' : 'credit' }}">
                            {{ $transaction['signed_amount'] }} {{ $transaction['currency'] }}
                        </td>
                        <td>{{ $transaction['balance_after'] }} {{ $transaction['currency'] }}</td>
                        <td>
                            @if ($transaction['booking_reference'])
                                {{ $transaction['booking_reference'] }}
                            @endif
                            @if ($transaction['description'])
                                {{ $transaction['description'] }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">No movements recorded yet. · لا توجد حركات.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="footer">
            Latest {{ count($transactions) }} ledger movement(s). Internal Booke prepaid balance — not the supplier portal.
            <br>
            أحدث حركات الدفتر الداخلي في Booke — وليس رصيد بوابة المزوّد.
        </p>
    </div>
</body>
</html>
