@extends('reports._layout')

@section('content')
    @forelse ($report['accounts'] as $account)
        <div class="ledger-account-block">
            <div class="section-title">{{ trim(($account['account_code'] ?? '').' '.$account['account_name']) }}</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 80px;">日付</th>
                        <th>摘要</th>
                        <th class="amount-col">借方</th>
                        <th class="amount-col">貸方</th>
                        <th class="amount-col">残高</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td></td>
                        <td>繰越残高</td>
                        <td class="text-right"></td>
                        <td class="text-right"></td>
                        <td class="text-right">{{ number_format($account['opening_balance']) }}</td>
                    </tr>
                    @foreach ($account['lines'] as $line)
                        <tr>
                            <td class="text-center">{{ str_replace('-', '/', $line['entry_date']) }}</td>
                            <td>{{ $line['description'] }}</td>
                            <td class="text-right">{{ $line['debit'] ? number_format($line['debit']) : '' }}</td>
                            <td class="text-right">{{ $line['credit'] ? number_format($line['credit']) : '' }}</td>
                            <td class="text-right">{{ number_format($line['balance']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td></td>
                        <td>期末残高</td>
                        <td class="text-right"></td>
                        <td class="text-right"></td>
                        <td class="text-right">{{ number_format($account['closing_balance']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <table>
            <tbody>
                <tr>
                    <td class="text-center">元帳データがありません</td>
                </tr>
            </tbody>
        </table>
    @endforelse
@endsection
