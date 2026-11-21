<?php

namespace Tests\Unit;

use App\Services\InvoiceTaxCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceTaxCalculatorTest extends TestCase
{
    public function test_tax_is_truncated_once_per_rate(): void
    {
        $summary = (new InvoiceTaxCalculator)->calculate([
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1005, 'tax_rate' => '10'],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 1005, 'tax_rate' => '10'],
            ['description' => 'C', 'quantity' => 2, 'unit_price' => 1000, 'tax_rate' => '8'],
        ]);

        $this->assertSame('10%', $summary['rates'][0]['label']);
        $this->assertSame(2010, $summary['rates'][0]['base']);
        $this->assertSame(201, $summary['rates'][0]['tax']);
        $this->assertSame('8%（軽減）', $summary['rates'][1]['label']);
        $this->assertSame(2000, $summary['rates'][1]['base']);
        $this->assertSame(160, $summary['rates'][1]['tax']);
        $this->assertTrue($summary['lines'][2]['is_reduced']);
        $this->assertSame('8% 軽', $summary['lines'][2]['short_label']);
        $this->assertSame(4010, $summary['subtotal']);
        $this->assertSame(361, $summary['tax_total']);
        $this->assertSame(4371, $summary['total']);
    }

    public function test_fractional_tax_on_one_line_is_truncated(): void
    {
        $summary = (new InvoiceTaxCalculator)->calculate([
            ['description' => '端数', 'quantity' => 1, 'unit_price' => 1005, 'tax_rate' => '10'],
        ]);

        $this->assertSame(1005, $summary['rates'][0]['base']);
        $this->assertSame(100, $summary['rates'][0]['tax']);
    }
}
