<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::daily()
    ->onOneServer()
    ->withoutOverlapping(30)
    ->group(function (): void {
        Schedule::command('app:prune-event-log');
        Schedule::command('app:prune-execution-drafts');
        Schedule::command('sanctum:prune-expired --hours='.config('retention.expired_token_hours'));
    });
