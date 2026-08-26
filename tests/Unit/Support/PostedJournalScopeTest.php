<?php

namespace Tests\Unit\Support;

use App\Support\PostedJournalScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostedJournalScopeTest extends TestCase
{
    public function test_apply_adds_posted_status_filter(): void
    {
        $query = DB::table('journal_entries');
        PostedJournalScope::apply($query);

        $sql = $query->toSql();
        $this->assertStringContainsString('"journal_entries"."status" = ?', $sql);
        $this->assertSame('posted', $query->getBindings()[0]);
    }

    public function test_apply_supports_custom_alias(): void
    {
        $query = DB::table('journal_entries as je');
        PostedJournalScope::apply($query, 'je');

        $sql = $query->toSql();
        $this->assertStringContainsString('"je"."status" = ?', $sql);
    }
}
