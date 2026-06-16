@extends('reports._layout')

@section('content')
    <div class="section-title">Ⅰ 収益の部</div>
    <table>
        <thead>
            <tr>
                <th class="code-col">科目コード</th>
                <th>勘定科目</th>
                <th class="amount-col">金額</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['revenue_rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['account_code'] ?? '' }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td class="text-right">{{ number_format($row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">該当なし</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td></td>
                <td>収益合計</td>
                <td class="text-right">{{ number_format($report['total_revenue']) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="section-title">Ⅱ 費用の部</div>
    <table>
        <thead>
            <tr>
                <th class="code-col">科目コード</th>
                <th>勘定科目</th>
                <th class="amount-col">金額</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['expense_rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['account_code'] ?? '' }}</td>
                    <td>{{ $row['account_name'] }}</td>
                    <td class="text-right">{{ number_format($row['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center">該当なし</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td></td>
                <td>費用合計</td>
                <td class="text-right">{{ number_format($report['total_expense']) }}</td>
            </tr>
            <tr class="total-row">
                <td></td>
                <td>当期純利益</td>
                <td class="text-right">{{ number_format($report['net_income']) }}</td>
            </tr>
        </tbody>
    </table>
@endsection
