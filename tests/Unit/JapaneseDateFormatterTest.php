<?php

namespace Tests\Unit;

use App\Support\JapaneseDateFormatter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class JapaneseDateFormatterTest extends TestCase
{
    public function test_formats_reiwa_date_with_western_parenthetical(): void
    {
        $date = new DateTimeImmutable('2025-03-31');

        $this->assertSame(
            '令和7年3月31日（2025年3月31日）',
            JapaneseDateFormatter::format($date),
        );
    }

    public function test_formats_heisei_date(): void
    {
        $date = new DateTimeImmutable('2019-04-30');

        $this->assertSame(
            '平成31年4月30日（2019年4月30日）',
            JapaneseDateFormatter::format($date),
        );
    }

    public function test_formats_showa_date(): void
    {
        $date = new DateTimeImmutable('1989-01-07');

        $this->assertSame(
            '昭和64年1月7日（1989年1月7日）',
            JapaneseDateFormatter::format($date),
        );
    }
}
