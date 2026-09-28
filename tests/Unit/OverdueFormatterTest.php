<?php

namespace Tests\Unit;

use App\Support\OverdueFormatter;
use Tests\TestCase;

class OverdueFormatterTest extends TestCase
{
    public function test_uses_seconds_only_under_one_minute(): void
    {
        $this->assertSame('45秒超過', OverdueFormatter::label(45));
    }

    public function test_uses_minutes_under_one_hour(): void
    {
        $this->assertSame('2分超過', OverdueFormatter::label(120));
    }

    public function test_uses_hours_and_minutes_under_one_day(): void
    {
        $this->assertSame('18時間57分超過', OverdueFormatter::label(68236));
    }

    public function test_uses_days_and_hours_over_one_day(): void
    {
        $this->assertSame('1日1時間超過', OverdueFormatter::label(90000));
        $this->assertSame('2日超過', OverdueFormatter::label(172800));
    }
}
