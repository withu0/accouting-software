@extends('reports._layout')

@section('content')
    <table>
        <thead>
            <tr>
                <th style="width: 60px;">伝票No.</th>
                <th style="width: 80px;">日付</th>
                <th>摘要</th>
                <th class="code-col">科目コード</th>
                <th>勘定科目</th>
                <th class="amount-col">借方</th>
                <th class="amount-col">貸方</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['entries'] as $entry)
                @foreach ($entry['lines'] as $index => $line)
                    <tr>
                        <td class="text-center">{{ $index === 0 ? $entry['voucher_no'] : '' }}</td>
                        <td class="text-center">{{ $index === 0 ? str_replace('-', '/', $entry['entry_date']) : '' }}</td>
                        <td>{{ $index === 0 ? $entry['description'] : '' }}</td>
                        <td class="text-center">{{ $line['account_code'] ?? '' }}</td>
                        <td>{{ $line['account_name'] }}</td>
                        <td class="text-right">{{ $line['debit'] ? number_format($line['debit']) : '' }}</td>
                        <td class="text-right">{{ $line['credit'] ? number_format($line['credit']) : '' }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="7" class="text-center">仕訳データがありません</td>
                </tr>
            @endforelse
        </tbody>
    </table>
@endsection
