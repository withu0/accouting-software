<?php

namespace App\Services\CreditCardCsv\Support;

class CreditCardCsvRowSupport
{
    public function shouldSkipRow(string $dateValue, string $description): bool
    {
        if ($dateValue === '') {
            return true;
        }

        if (str_starts_with($description, 'ご利用者名:') || str_starts_with($description, 'ご利用者名：')) {
            return true;
        }

        foreach (['【小計】', '【合計】', '小計', '合計'] as $summaryPrefix) {
            if (str_starts_with($description, $summaryPrefix) || str_starts_with($dateValue, $summaryPrefix)) {
                return true;
            }
        }

        return false;
    }

    public function computeRowHash(string $date, string $description, int $amount, int $occurrence = 0): string
    {
        // Occurrence lets the same merchant/amount on the same day import more than once
        // (e.g. two LINE charges). Keep occurrence 0 compatible with existing hashes.
        $payload = "{$date}|{$description}|{$amount}";
        if ($occurrence > 0) {
            $payload .= "|{$occurrence}";
        }

        return hash('sha256', $payload);
    }
}
