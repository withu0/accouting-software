<div class="header">
    <div class="unit-label">{{ $meta['unit_label'] }}</div>
    <div class="company-name">{{ $meta['company_name'] ?? '（社名未設定）' }}</div>
    @if (!empty($meta['address']))
        <div class="company-address">{{ $meta['address'] }}</div>
    @endif
    @if (!empty($meta['representative_name']))
        <div class="representative">代表者 {{ $meta['representative_name'] }}</div>
    @endif
    <div class="report-title">{{ $meta['title'] }}</div>
    @if (!empty($meta['period_label']))
        <div class="period-line">{{ $meta['period_label'] }}</div>
    @endif
    @if (!empty($meta['as_of_label']))
        <div class="period-line">{{ $meta['as_of_label'] }}</div>
    @endif
</div>
