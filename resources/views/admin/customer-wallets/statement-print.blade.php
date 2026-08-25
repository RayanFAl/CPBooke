<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet Statement · {{ $wallet['wallet_number'] }}</title>
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
            max-width: 960px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 28px;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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

        h1 {
            margin: 0;
            font-size: 24px;
        }

        .meta {
            margin: 8px 0 20px;
            color: #64748b;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            text-align: left;
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #cbd5e1;
            padding: 8px 6px;
        }

        td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 6px;
            vertical-align: top;
        }

        .credit {
            color: #047857;
            font-weight: 600;
        }

        .debit {
            color: #be123c;
            font-weight: 600;
        }

        .footer {
            margin-top: 20px;
            color: #64748b;
            font-size: 12px;
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
            <span>Generated {{ $generated_at }}</span>
            <button type="button" onclick="window.print()">Print / Save PDF · طباعة</button>
        </div>

        <h1>{{ $company }}</h1>
        <p class="meta">
            Wallet statement · كشف المحفظة
            · {{ $wallet['user_name'] }}
            · {{ $wallet['wallet_number'] }}
            · {{ $wallet['balance'] }} {{ $wallet['currency'] }}
        </p>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Before</th>
                    <th>After</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>#{{ $transaction['id'] }}</td>
                        <td>{{ $transaction['created_at'] }}</td>
                        <td>{{ $transaction['type_label'] }}</td>
                        <td class="{{ $transaction['is_debit'] ? 'debit' : 'credit' }}">
                            {{ $transaction['is_debit'] ? '-' : '+' }}{{ $transaction['amount'] }} {{ $transaction['currency'] }}
                        </td>
                        <td>{{ $transaction['balance_before'] }} {{ $transaction['currency'] }}</td>
                        <td>{{ $transaction['balance_after'] }} {{ $transaction['currency'] }}</td>
                        <td>{{ $transaction['created_by'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No transactions recorded yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p class="footer">
            Latest 100 wallet transactions. This statement is generated from the customer wallet ledger.
            <br>
            أحدث 100 معاملة. هذا الكشف صادر من دفتر محفظة العميل.
        </p>
    </div>
</body>
</html>
