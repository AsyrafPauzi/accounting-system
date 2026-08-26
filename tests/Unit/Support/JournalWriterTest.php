<?php

namespace Tests\Unit\Support;

use App\Support\JournalWriter;
use LogicException;
use PHPUnit\Framework\TestCase;

class JournalWriterTest extends TestCase
{
    public function test_assert_balanced_accepts_equal_debits_and_credits(): void
    {
        JournalWriter::assertBalanced([
            ['account_code' => '1100', 'debit' => 100, 'credit' => 0],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 100],
        ]);

        $this->assertTrue(true);
    }

    public function test_assert_balanced_rejects_unbalanced_lines(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Journal is not balanced');

        JournalWriter::assertBalanced([
            ['account_code' => '1100', 'debit' => 100, 'credit' => 0],
            ['account_code' => '4000', 'debit' => 0, 'credit' => 50],
        ]);
    }

    public function test_assert_balanced_rejects_empty_lines(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('at least one line');

        JournalWriter::assertBalanced([]);
    }
}
