<?php

use App\Services\DompdfFontRegistrar;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('dompdf:install-font', function (DompdfFontRegistrar $registrar) {
    $registrar->resetFontCache();
    $registrar->ensureJapaneseFontRegistered();
    $this->info('DomPDF Japanese font registered: '.DompdfFontRegistrar::FONT_FAMILY);
})->purpose('Register the Japanese font for PDF report generation');
