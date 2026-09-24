<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>{{ $shared['title'] }}</title>
    @include('reports.partials.styles')
</head>
<body>
    @foreach ($sections as $section)
        <div class="statement">
            @include('reports.partials.header', ['meta' => $section['meta']])
            @include($section['partial'], ['report' => $section['report']])
        </div>
    @endforeach

    <div class="footer-left">作成日: {{ $shared['generated_at'] }}</div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font('noto sans jp', 'normal');
            $x = 270;
            $y = 28;
            $pdf->page_text($x, $y, '{PAGE_NUM} / {PAGE_COUNT}', $font, 9, [0.3, 0.3, 0.3]);
        }
    </script>
</body>
</html>
