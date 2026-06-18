<?php

namespace App\Services\BankCsv;

class BankCsvEncodingNormalizer
{
    public function normalize(string $content): string
    {
        if ($content === '') {
            return '';
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if ($this->isValidUtf8($content)) {
            return $content;
        }

        $converted = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');

        return $converted !== false ? $converted : $content;
    }

    private function isValidUtf8(string $content): bool
    {
        return mb_check_encoding($content, 'UTF-8');
    }
}
