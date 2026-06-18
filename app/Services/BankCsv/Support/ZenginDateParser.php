<?php

namespace App\Services\BankCsv\Support;

use Carbon\Carbon;
use InvalidArgumentException;

class ZenginDateParser
{
    /**
     * Parse 和暦 YYMMDD (e.g. Reiwa 7 Apr 1 → 070401 → 2025-04-01).
     */
    public static function parse(string $yyMmDd, ?Carbon $preferWithinStart = null, ?Carbon $preferWithinEnd = null): Carbon
    {
        if (! preg_match('/^\d{6}$/', $yyMmDd)) {
            throw new InvalidArgumentException("Invalid zengin date: {$yyMmDd}");
        }

        $yy = (int) substr($yyMmDd, 0, 2);
        $mm = (int) substr($yyMmDd, 2, 2);
        $dd = (int) substr($yyMmDd, 4, 2);

        $candidates = [];

        if ($yy >= 1) {
            $candidates[] = Carbon::create(2018 + $yy, $mm, $dd)->startOfDay();
        }

        if ($yy >= 1) {
            $candidates[] = Carbon::create(1988 + $yy, $mm, $dd)->startOfDay();
        }

        if ($preferWithinStart !== null && $preferWithinEnd !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate->gte($preferWithinStart) && $candidate->lte($preferWithinEnd)) {
                    return $candidate;
                }
            }
        }

        if ($yy >= 1 && $candidates !== []) {
            return $candidates[0];
        }

        throw new InvalidArgumentException("Unable to parse zengin date: {$yyMmDd}");
    }
}
