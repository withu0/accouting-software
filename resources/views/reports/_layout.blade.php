<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['title'] }}</title>
    @include('reports.partials.styles')
</head>
<body>
    @include('reports.partials.header')

    @yield('content')

    <div class="footer-left">作成日: {{ $meta['generated_at'] }}</div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('noto sans jp', 'normal');
            $orientation = '{{ $meta['orientation'] }}';
            $x = $orientation === 'landscape' ? 390 : 270;
            $y = 28;
            $pdf->page_text($x, $y, '{PAGE_NUM} / {PAGE_COUNT}', $font, 9, [0.3, 0.3, 0.3]);
        }
    </script>
</body>
</html>
