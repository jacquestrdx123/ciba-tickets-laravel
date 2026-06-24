<?php

namespace App\Jobs;

use App\Jobs\SyncGithubBranchesJob;
use App\Models\Ticket;
use App\Services\TicketSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SyncTicketsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public int $uniqueFor = 300;

    public function uniqueId(): string
    {
        return 'sync-tickets';
    }

    public function handle(TicketSyncService $syncService): void
    {
        self::setStatus('running', ['phase' => 'list']);

        $vendorIds = $syncService->syncList();
        $total = count($vendorIds);

        if ($total === 0) {
            self::setStatus('completed', [
                'synced'    => 0,
                'synced_at' => now()->toISOString(),
            ]);

            SyncGithubBranchesJob::dispatch();
            SyncGithubBranchesJob::setStatus('queued');

            return;
        }

        $failed = 0;

        foreach ($vendorIds as $index => $vendorId) {
            self::setStatus('running', [
                'phase'             => 'details',
                'details_total'     => $total,
                'details_completed' => $index,
                'details_failed'    => $failed,
            ]);

            try {
                $this->syncDetailWithRateLimit($syncService, $vendorId);
            } catch (Throwable) {
                $failed++;
            }
        }

        self::setStatus('completed', [
            'synced'                => Ticket::count(),
            'synced_at'             => now()->toISOString(),
            'details_total'         => $total,
            'details_failed'        => $failed,
            'customer_closed_count' => Ticket::where('closed_on_customer_side', true)->count(),
        ]);

        SyncGithubBranchesJob::dispatch();
        SyncGithubBranchesJob::setStatus('queued');
    }

    public function failed(Throwable $exception): void
    {
        self::setStatus('failed', [
            'error' => $exception->getMessage(),
        ]);
    }

    public static function status(): array
    {
        return Cache::get('tickets_sync_status', [
            'status'     => 'idle',
            'updated_at' => null,
        ]);
    }

    public static function setStatus(string $status, array $data = []): void
    {
        Cache::put('tickets_sync_status', array_merge([
            'status'     => $status,
            'updated_at' => now()->toISOString(),
        ], $data), now()->addHours(24));
    }

    private function syncDetailWithRateLimit(TicketSyncService $syncService, int $vendorId): void
    {
        while (!RateLimiter::attempt('vendor-api', 45, fn () => $syncService->syncDetail($vendorId), 60)) {
            sleep(2);
        }
    }
}
