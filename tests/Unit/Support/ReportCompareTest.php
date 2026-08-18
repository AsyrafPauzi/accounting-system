<?php

namespace Tests\Unit\Support;

use App\Support\ReportCompare;
use PHPUnit\Framework\TestCase;

class ReportCompareTest extends TestCase
{
    public function test_it_merges_current_and_prior_lines_by_account_code(): void
    {
        $current = [
            ['code' => '4000', 'name' => 'Sales', 'amount' => 100],
        ];
        $prior = [
            ['code' => '4000', 'name' => 'Sales', 'amount' => 80],
            ['code' => '4100', 'name' => 'Other Income', 'amount' => 10],
        ];

        $this->assertSame([
            [
                'code' => '4000',
                'name' => 'Sales',
                'amount' => 100.0,
                'compare_amount' => 80.0,
                'variance' => 20.0,
            ],
            [
                'code' => '4100',
                'name' => 'Other Income',
                'amount' => 0.0,
                'compare_amount' => 10.0,
                'variance' => -10.0,
            ],
        ], ReportCompare::mergeLines($current, $prior));
    }
}
