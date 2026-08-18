<?php

namespace Tests\Unit\Support;

use App\Support\MyInvoisGap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MyInvoisGapTest extends TestCase
{
    #[DataProvider('gapReasons')]
    public function test_it_classifies_myinvois_submission_gaps(
        ?string $uuid,
        ?string $status,
        ?string $expected
    ): void {
        $this->assertSame($expected, MyInvoisGap::myinvoisGapReason($uuid, $status));
    }

    public static function gapReasons(): array
    {
        return [
            'null uuid takes precedence over pending' => [null, 'pending', 'Not submitted'],
            'empty uuid is not submitted' => ['', 'valid', 'Not submitted'],
            'valid submission is not a gap' => ['uuid-123', 'valid', null],
            'rejected submission' => ['uuid-123', 'rejected', 'rejected'],
            'invalid submission' => ['uuid-123', 'invalid', 'invalid'],
            'pending submission with uuid' => ['uuid-123', 'pending', 'pending'],
            'cancelled submission is not a gap' => ['uuid-123', 'cancelled', null],
        ];
    }
}
