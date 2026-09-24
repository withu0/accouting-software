@php
    $yen = fn (int $amount) => \App\Support\Yen::format($amount);
@endphp

<table class="equity-table">
    <thead>
        <tr>
            <th>区分</th>
            @foreach ($report['columns'] as $label)
                <th class="amount-col">{{ $label }}</th>
            @endforeach
            <th class="amount-col">株主資本合計</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($report['rows'] as $row)
            <tr @if (in_array($row['kind'], ['subtotal', 'ending', 'beginning'], true)) class="total-row" @endif>
                <td>{{ $row['label'] }}</td>
                <td class="text-right">{{ $row['capital'] === 0 && $row['kind'] === 'movement' ? '' : $yen($row['capital']) }}</td>
                <td class="text-right">{{ $row['capital_surplus'] === 0 && $row['kind'] === 'movement' ? '' : $yen($row['capital_surplus']) }}</td>
                <td class="text-right">{{ $row['retained_earnings'] === 0 && $row['kind'] === 'movement' ? '' : $yen($row['retained_earnings']) }}</td>
                <td class="text-right">{{ $row['treasury'] === 0 && $row['kind'] === 'movement' ? '' : $yen($row['treasury']) }}</td>
                <td class="text-right">{{ $yen($row['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if (!empty($report['is_manual']))
    <p style="margin-top: 8px;">※ 変動事由は手動で調整しています。</p>
@endif

@if (! $report['is_consistent'])
    <p style="margin-top: 12px;">株主資本等変動計算書の期末残高と貸借対照表の純資産残高が一致していません。</p>
    @foreach ($report['mismatches'] as $mismatch)
        <p>{{ $mismatch['account_name'] }}　差額 {{ $yen($mismatch['difference']) }}円</p>
    @endforeach
@endif
