<?php

use Illuminate\Support\Facades\Schedule;

/*
| The cron entry in docs/DEPLOY.md runs `schedule:run` every minute; these are
| what it actually does. Without this file the cron would be a no-op and
| search_logs would grow without bound.
*/

// Search and click history past SEARCH_LOG_RETENTION_DAYS (default 90).
// The reports only look at recent windows, so older rows are pure growth.
Schedule::command('search-logs:prune')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping();

// Only relevant if the client switched QUEUE_CONNECTION to `database`; a
// no-op otherwise, and cheap enough to leave registered either way.
Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->onOneServer();
