<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet Receipt · #{{ $transaction['id'] }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: #0f172a;
        }

        body {
            margin: 0;
            padding: 32px;
            background: #e2e8f0;
        }

        .sheet {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 28px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .toolbar span {
            color: #64748b;
            font-size: 13px;
        }

        .toolbar button {
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: #fff;
            padding: 10px 18px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 28px 32px 8px;
        }

        .brand h1 {
            margin: 0;
            font-size: 22px;
        }

        .brand p {
            margin: 6px 0 0;
            color: #64748b;
            font-size: 13px;
        }

        .status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #dcfce7;
            color: #166534;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 6px 12px;
        }

        .amount {
            padding: 8px 32px 24px;
            border-bottom: 1px solid #e2e8f0;
        }

        .amount small {
            color: #64748b;
            font-size: 12px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .amount strong {
            display: block;
            margin-top: 6px;
            font-size: 36px;
            color: {{ $transaction['is_debit'] ? '#be123c' : '#047857' }};
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 14px 32px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .row span {
            color: #64748b;
        }

        .row b {
            text-align: right;
            font-weight: 600;
        }

        .ar {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 400;
        }

        .footer {
            padding: 20px 32px 28px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
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
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <span>Generated {{ $generated_at }}</span>
            <button type="button" onclick="window.print()">Print / Save PDF · طباعة</button>
        </div>

        <div class="brand">
            <div>
                <h1>{{ $company }}</h1>
                <p>Wallet receipt · إيصال المحفظة</p>
            </div>
            <span class="status">Completed · مكتمل</span>
        </div>

        <div class="amount">
            <small>{{ $transaction['is_debit'] ? 'Debited · تم الخصم' : 'Credited · تم الإضافة' }}</small>
            <strong>{{ $transaction['is_debit'] ? '-' : '+' }}{{ $transaction['amount'] }} {{ $transaction['currency'] }}</strong>
        </div>

        <div class="row">
            <span>Transaction ID <span class="ar">رقم المعاملة</span></span>
            <b>#{{ $transaction['id'] }}</b>
        </div>
        <div class="row">
            <span>Type <span class="ar">النوع</span></span>
            <b>{{ $transaction['type_label'] }} · {{ $transaction['type_label_ar'] }}</b>
        </div>
        <div class="row">
            <span>Date / Time <span class="ar">التاريخ والوقت</span></span>
            <b>{{ $transaction['created_at'] }}</b>
        </div>
        <div class="row">
            <span>Customer <span class="ar">العميل</span></span>
            <b>{{ $wallet['user_name'] }}</b>
        </div>
        <div class="row">
            <span>Email / Phone</span>
            <b>{{ $wallet['user_email'] ?: '—' }} · {{ $wallet['user_phone'] ?: '—' }}</b>
        </div>
        <div class="row">
            <span>Wallet <span class="ar">المحفظة</span></span>
            <b>{{ $wallet['wallet_number'] }} · {{ $wallet['currency'] }}</b>
        </div>
        <div class="row">
            <span>Balance before <span class="ar">قبل</span></span>
            <b>{{ $transaction['balance_before'] }} {{ $transaction['currency'] }}</b>
        </div>
        <div class="row">
            <span>Balance after <span class="ar">بعد</span></span>
            <b>{{ $transaction['balance_after'] }} {{ $transaction['currency'] }}</b>
        </div>
        <div class="row">
            <span>Processed by <span class="ar">نفّذ العملية</span></span>
            <b>{{ $transaction['created_by'] ?: 'System' }}</b>
        </div>
        @if ($transaction['reason_label'])
            <div class="row">
                <span>Reason <span class="ar">السبب</span></span>
                <b>{{ $transaction['reason_label'] }}</b>
            </div>
        @endif
        @if ($transaction['note'])
            <div class="row">
                <span>Note <span class="ar">ملاحظة</span></span>
                <b>{{ $transaction['note'] }}</b>
            </div>
        @endif
        @if ($transaction['order_reference'])
            <div class="row">
                <span>Order <span class="ar">الحجز</span></span>
                <b>{{ $transaction['order_reference'] }}</b>
            </div>
        @endif

        <div class="footer">
            This is a system-generated wallet receipt. It records a ledger transaction, not a direct balance edit.
            <br>
            هذا إيصال صادر من النظام لمعاملة محفظة مسجّلة، وليس تعديلاً مباشراً على الرصيد.
            @if ($transaction['reference_id'])
                <br>Internal ID: {{ $transaction['reference_id'] }}
            @endif
        </div>
    </div>
</body>
</html>
