<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>請求書 {{ $invoiceNumber }}</title>
    <style>
        @page { margin: 16mm 14mm 16mm 14mm; }
        body { font-family: 'noto sans jp', sans-serif; font-size: 11px; color: #111; line-height: 1.45; }
        h1 { text-align: center; font-size: 22px; letter-spacing: 0.45em; margin: 0 0 16px; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { border: none; vertical-align: top; padding: 0; }
        .customer { font-size: 15px; font-weight: bold; border-bottom: 1px solid #111; padding-bottom: 4px; }
        .issuer { margin-top: 8px; }
        .issuer-name { font-size: 13px; font-weight: bold; }
        .registration { margin-top: 6px; font-weight: bold; }
        .lines { margin-top: 16px; }
        .lines th, .lines td { border: 1px solid #333; padding: 4px 6px; }
        .lines th { background: #eee; text-align: center; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .reduced { font-weight: bold; }
        .summary { width: 70%; margin-top: 14px; margin-left: auto; }
        .summary th, .summary td { border: 1px solid #333; padding: 4px 6px; }
        .summary th { background: #eee; text-align: center; }
        .total td { font-weight: bold; background: #f7f7f7; }
        .note { margin-top: 14px; font-size: 10px; }
        .remarks { margin-top: 10px; border: 1px solid #333; padding: 6px 8px; }
    </style>
</head>
<body>
    <h1>請求書</h1>

    <table class="meta">
        <tr>
            <td style="width: 55%;">
                <div class="customer">{{ $customerName }} 御中</div>
                <p>下記のとおりご請求申し上げます。</p>
                <p>金額は税抜です。消費税は税率ごとに切り捨てています。</p>
            </td>
            <td style="width: 45%;">
                <div>請求番号　{{ $invoiceNumber }}</div>
                <div>発行日　　{{ $issueDate }}</div>
                <div class="issuer">
                    <div class="issuer-name">{{ $companyName }}</div>
                    @if (!empty($address))
                        <div>{{ $address }}</div>
                    @endif
                    @if (!empty($representativeName))
                        <div>代表者 {{ $representativeName }}</div>
                    @endif
                    <div class="registration">登録番号　{{ $registrationNumber }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width: 46%;">取引内容</th>
                <th style="width: 10%;">数量</th>
                <th style="width: 16%;">単価（税抜）</th>
                <th style="width: 12%;">税率</th>
                <th style="width: 16%;">金額（税抜）</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['lines'] as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td class="text-right">{{ number_format($line['quantity']) }}</td>
                    <td class="text-right">{{ \App\Support\Yen::format($line['unit_price']) }}</td>
                    <td class="text-center {{ $line['is_reduced'] ? 'reduced' : '' }}">{{ $line['short_label'] }}</td>
                    <td class="text-right">{{ \App\Support\Yen::format($line['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">「軽」は軽減税率（8%）の対象です。</p>

    <table class="summary">
        <thead>
            <tr>
                <th>適用税率</th>
                <th>税抜対価</th>
                <th>消費税額</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['rates'] as $rate)
                <tr>
                    <td class="text-center">{{ $rate['label'] }}</td>
                    <td class="text-right">{{ \App\Support\Yen::format($rate['base']) }}</td>
                    <td class="text-right">{{ \App\Support\Yen::format($rate['tax']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td class="text-center">合計</td>
                <td class="text-right">{{ \App\Support\Yen::format($summary['subtotal']) }}</td>
                <td class="text-right">{{ \App\Support\Yen::format($summary['tax_total']) }}</td>
            </tr>
            <tr class="total">
                <td class="text-center">請求金額（税込）</td>
                <td class="text-right" colspan="2">{{ \App\Support\Yen::format($summary['total']) }}円</td>
            </tr>
        </tbody>
    </table>

    @if (!empty($notes))
        <div class="remarks">
            <div>備考</div>
            <div>{!! nl2br(e($notes)) !!}</div>
        </div>
    @endif
</body>
</html>
