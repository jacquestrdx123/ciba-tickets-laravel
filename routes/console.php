<?php

use App\Jobs\SyncGithubBranchesJob;
use App\Jobs\SyncTicketsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tickets:sync', function () {
    $status = SyncTicketsJob::status();

    if (in_array($status['status'], ['queued', 'running'], true)) {
        $this->warn('A ticket sync is already in progress.');
        return 1;
    }

    SyncTicketsJob::dispatch();
    SyncTicketsJob::setStatus('queued');
    $this->info('Ticket sync job dispatched.');

    return 0;
})->purpose('Queue a full ticket sync from the vendor API');

Artisan::command('github-branches:sync', function () {
    $status = SyncGithubBranchesJob::status();

    if (in_array($status['status'], ['queued', 'running'], true)) {
        $this->warn('A GitHub branch sync is already in progress.');
        return 1;
    }

    SyncGithubBranchesJob::dispatch();
    SyncGithubBranchesJob::setStatus('queued');
    $this->info('GitHub branch sync job dispatched.');

    return 0;
})->purpose('Queue a GitHub branch sync for all stored tickets');

Schedule::job(new SyncTicketsJob)->everyFiveMinutes();
Schedule::job(new SyncGithubBranchesJob)->everyFiveMinutes();
