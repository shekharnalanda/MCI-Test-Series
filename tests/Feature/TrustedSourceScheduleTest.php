<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class TrustedSourceScheduleTest extends TestCase
{
    public function test_trusted_source_automation_is_scheduled_before_current_affairs(): void
    {
        $events = collect(app(Schedule::class)->events());

        $health = $events->first(
            fn ($event) => str_contains($event->command ?? '', 'question-bank:check-sources')
        );

        $audit = $events->first(
            fn ($event) => str_contains($event->command ?? '', 'question-bank:audit-sources')
        );

        $this->assertNotNull($health);
        $this->assertSame('45 1 * * *', $health->expression);
        $this->assertSame('Asia/Kolkata', $health->timezone);

        $this->assertNotNull($audit);
        $this->assertSame('0 2 * * 1', $audit->expression);
        $this->assertSame('Asia/Kolkata', $audit->timezone);
    }
}
