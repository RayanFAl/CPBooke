<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Provider Profile · {{ $supplier['name'] }}</title>
    <style>
        :root { color-scheme: light; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #0f172a; }
        body { margin: 0; padding: 32px; background: #e2e8f0; }
        .sheet { max-width: 820px; margin: 0 auto; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; padding: 28px; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .toolbar span { color: #64748b; font-size: 13px; }
        .toolbar button { border: 0; border-radius: 999px; background: #0f172a; color: #fff; padding: 10px 18px; font-size: 13px; font-weight: 600; cursor: pointer; }
        h1 { margin: 0; font-size: 24px; }
        .meta { margin: 8px 0 20px; color: #64748b; font-size: 13px; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin: 0; }
        dl div { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; }
        dt { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
        dd { margin: 4px 0 0; font-size: 15px; font-weight: 600; }
        .section { margin-top: 24px; }
        .section h2 { margin: 0 0 12px; font-size: 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 8px 6px; text-align: left; }
        th { font-size: 11px; text-transform: uppercase; color: #64748b; }
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
            Provider profile · ملف المزوّد
            · {{ $supplier['name'] }}
            · {{ $supplier['status'] }}
        </p>

        <dl>
            <div><dt>Commission · العمولة</dt><dd>{{ $supplier['commission_rate'] !== null ? $supplier['commission_rate'].'%' : '—' }}</dd></div>
            <div><dt>Settlement cycle · دورة التسوية</dt><dd>{{ $supplier['settlement_cycle'] ?: '—' }}</dd></div>
            <div><dt>Credit limit · حد الائتمان</dt><dd>{{ $supplier['credit_limit'] ?: '—' }} {{ $supplier['default_currency'] }}</dd></div>
            <div><dt>Currency · العملة</dt><dd>{{ $supplier['default_currency'] ?: '—' }}</dd></div>
            <div><dt>Contact · التواصل</dt><dd>{{ $supplier['contact_name'] ?: '—' }}</dd></div>
            <div><dt>Email · البريد</dt><dd>{{ $supplier['contact_email'] ?: '—' }}</dd></div>
            <div><dt>Phone · الهاتف</dt><dd>{{ $supplier['contact_phone'] ?: '—' }}</dd></div>
            <div><dt>Website · الموقع</dt><dd>{{ $supplier['website'] ?: '—' }}</dd></div>
        </dl>

        @if (! empty($supplier['wallets']))
            <div class="section">
                <h2>Ledger wallets · دفاتر الرصيد</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Currency</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($supplier['wallets'] as $wallet)
                            <tr>
                                <td>{{ $wallet['currency'] }}</td>
                                <td>{{ $wallet['balance'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($supplier['notes'] || $supplier['contract_notes'])
            <div class="section">
                <h2>Notes · ملاحظات</h2>
                <p>{{ $supplier['contract_notes'] ?: $supplier['notes'] }}</p>
            </div>
        @endif

        <p class="footer">
            Internal provider commercial profile from Booke.
            <br>
            ملف تجاري داخلي للمزوّد من Booke.
        </p>
    </div>
</body>
</html>
