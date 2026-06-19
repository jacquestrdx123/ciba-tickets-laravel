<?php

namespace App\Jobs;

use App\Services\GithubBranchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SyncGithubBranchesJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'sync-github-branches';
    }

    public function handle(GithubBranchService $branchService): void
    {
        self::setStatus('running');

        $result = $branchService->sync();

        self::setStatus('completed', [
            'synced'    => $result['synced'],
            'synced_at' => $result['synced_at']->toISOString(),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        self::setStatus('failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    public static function status(): array
    {
        return Cache::get('github_branches_sync_status', [
            'status'     => 'idle',
            'updated_at' => null,
        ]);
    }

    public static function setStatus(string $status, array $data = []): void
    {
        Cache::put('github_branches_sync_status', array_merge([
            'status'     => $status,
            'updated_at' => now()->toISOString(),
        ], $data), now()->addHours(24));
    }
}
