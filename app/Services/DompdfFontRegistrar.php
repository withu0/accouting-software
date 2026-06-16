<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfWrapper;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DompdfFontRegistrar
{
    public const FONT_FAMILY = 'noto sans jp';

    private const FONT_FILE = 'NotoSansJP-Regular.ttf';

    public function ensureJapaneseFontRegistered(?DompdfWrapper $pdf = null): void
    {
        $fontPath = $this->fontPath();

        $fontMetrics = ($pdf ?? Pdf::getDomPDF())->getFontMetrics();

        foreach (['normal', 'bold'] as $weight) {
            $registered = $fontMetrics->registerFont([
                'family' => self::FONT_FAMILY,
                'weight' => $weight,
                'style' => 'normal',
            ], $fontPath);

            if (! $registered) {
                throw new RuntimeException('Failed to register Japanese PDF font with DomPDF.');
            }
        }
    }

    public function resetFontCache(): void
    {
        $fontDir = storage_path('fonts');

        if (File::exists($fontDir.'/installed-fonts.json')) {
            File::delete($fontDir.'/installed-fonts.json');
        }

        foreach (File::glob($fontDir.'/noto_sans_jp_*') as $cachedFile) {
            File::delete($cachedFile);
        }
    }

    private function fontPath(): string
    {
        $fontPath = storage_path('fonts/'.self::FONT_FILE);

        if (! is_readable($fontPath)) {
            throw new RuntimeException(
                'Japanese PDF font not found. Place '.self::FONT_FILE.' in storage/fonts and run php artisan dompdf:install-font.'
            );
        }

        return realpath($fontPath) ?: $fontPath;
    }
}
