<?php

namespace App\Support;

use DateTimeInterface;

class JapaneseDateFormatter
{
    public static function format(DateTimeInterface $date): string
    {
        $western = self::western($date);
        $wareki = self::wareki($date);

        return "{$wareki}（{$western}）";
    }

    public static function western(DateTimeInterface $date): string
    {
        return $date->format('Y年n月j日');
    }

    public static function wareki(DateTimeInterface $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');
        $day = (int) $date->format('j');
        $timestamp = mktime(0, 0, 0, $month, $day, $year);

        if ($timestamp >= mktime(0, 0, 0, 5, 1, 2019)) {
            $eraYear = $year - 2018;

            return "令和{$eraYear}年{$month}月{$day}日";
        }

        if ($timestamp >= mktime(0, 0, 0, 1, 8, 1989)) {
            $eraYear = $year - 1988;

            return "平成{$eraYear}年{$month}月{$day}日";
        }

        if ($timestamp >= mktime(0, 0, 0, 12, 25, 1926)) {
            $eraYear = $year - 1925;

            return "昭和{$eraYear}年{$month}月{$day}日";
        }

        return self::western($date);
    }
}
