@extends('reports._layout')

@section('content')
    @php
        $leftContent = [];
        $leftContent[] = ['type' => 'section', 'label' => '資産の部'];
        foreach ($report['asset_rows'] as $row) {
            $leftContent[] = ['type' => 'data', 'code' => $row['account_code'] ?? '', 'name' => $row['account_name'], 'amount' => $row['amount']];
        }

        $rightContent = [];
        $rightContent[] = ['type' => 'section', 'label' => '負債の部'];
        foreach ($report['liability_rows'] as $row) {
            $rightContent[] = ['type' => 'data', 'code' => $row['account_code'] ?? '', 'name' => $row['account_name'], 'amount' => $row['amount']];
        }
        $rightContent[] = ['type' => 'total', 'label' => '負債合計', 'amount' => $report['total_liabilities']];

        $rightContent[] = ['type' => 'section', 'label' => '純資産の部'];
        foreach ($report['equity_rows'] as $row) {
            $rightContent[] = ['type' => 'data', 'code' => $row['account_code'] ?? '', 'name' => $row['account_name'], 'amount' => $row['amount']];
        }
        $rightContent[] = ['type' => 'total', 'label' => '純資産合計', 'amount' => $report['total_equity']];

        $maxContent = max(count($leftContent), count($rightContent));
        while (count($leftContent) < $maxContent) {
            $leftContent[] = ['type' => 'pad'];
        }
        while (count($rightContent) < $maxContent) {
            $rightContent[] = ['type' => 'pad'];
        }

        $grandTotalLeft = ['type' => 'total', 'label' => '資産合計', 'amount' => $report['total_assets']];
        $grandTotalRight = ['type' => 'total', 'label' => '負債・純資産合計', 'amount' => $report['total_liabilities_and_equity']];
    @endphp

    <table class="bs-outer">
        <thead>
            <tr class="bs-side-head">
                <th colspan="3">資産</th>
                <th colspan="3">負債・純資産</th>
            </tr>
            <tr>
                <th class="code-col">科目コード</th>
                <th class="bs-name-col">勘定科目</th>
                <th class="amount-col bs-divider-right">金額</th>
                <th class="code-col">科目コード</th>
                <th class="bs-name-col">勘定科目</th>
                <th class="amount-col">金額</th>
            </tr>
        </thead>
        <tbody>
            @for ($i = 0; $i < $maxContent; $i++)
                @php
                    $left = $leftContent[$i];
                    $right = $rightContent[$i];
                @endphp
                <tr @if ($left['type'] === 'total' || $right['type'] === 'total') class="total-row" @endif>
                    @if ($left['type'] === 'section')
                        <td colspan="3" class="bs-section-head bs-divider-right">{{ $left['label'] }}</td>
                    @elseif ($left['type'] === 'data')
                        <td class="text-center">{{ $left['code'] }}</td>
                        <td>{{ $left['name'] }}</td>
                        <td class="text-right bs-divider-right">{{ number_format($left['amount']) }}</td>
                    @elseif ($left['type'] === 'total')
                        <td></td>
                        <td>{{ $left['label'] }}</td>
                        <td class="text-right bs-divider-right">{{ number_format($left['amount']) }}</td>
                    @else
                        <td class="bs-pad-row bs-divider-right" colspan="3">&nbsp;</td>
                    @endif

                    @if ($right['type'] === 'section')
                        <td colspan="3" class="bs-section-head">{{ $right['label'] }}</td>
                    @elseif ($right['type'] === 'data')
                        <td class="text-center">{{ $right['code'] }}</td>
                        <td>{{ $right['name'] }}</td>
                        <td class="text-right">{{ number_format($right['amount']) }}</td>
                    @elseif ($right['type'] === 'total')
                        <td></td>
                        <td>{{ $right['label'] }}</td>
                        <td class="text-right">{{ number_format($right['amount']) }}</td>
                    @else
                        <td class="bs-pad-row" colspan="3">&nbsp;</td>
                    @endif
                </tr>
            @endfor
            <tr class="total-row">
                <td></td>
                <td>{{ $grandTotalLeft['label'] }}</td>
                <td class="text-right bs-divider-right">{{ number_format($grandTotalLeft['amount']) }}</td>
                <td></td>
                <td>{{ $grandTotalRight['label'] }}</td>
                <td class="text-right">{{ number_format($grandTotalRight['amount']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
