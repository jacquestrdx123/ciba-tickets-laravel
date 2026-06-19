<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class VendorTicketsSeeder extends Seeder
{
    private const DATA_PATH = 'data/vendor-tickets.json';

    public function run(): void
    {
        $path = database_path(self::DATA_PATH);

        if (!is_readable($path)) {
            $this->command?->error('Missing '.self::DATA_PATH.' — place the ticket cache export at database/'.self::DATA_PATH);

            return;
        }

        $entries = json_decode(file_get_contents($path), true);

        if (!is_array($entries)) {
            throw new \RuntimeException('Invalid JSON in '.self::DATA_PATH);
        }

        $now = now();
        $ticketCount = 0;
        $commentCount = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $value = $entry['value'] ?? $entry;
            $vendorId = $entry['key'] ?? $value['id'] ?? null;

            if (!$vendorId) {
                continue;
            }

            $ticket = $this->upsertTicket($value, $now);
            $commentCount += $this->syncComments($ticket, $value);
            $ticketCount++;
        }

        $this->command?->info(sprintf(
            'Seeded %d tickets (%d comments). Triage state unchanged — use AwaitingClientTriageSeeder for parked tickets.',
            $ticketCount,
            $commentCount
        ));
    }

    /** @param array<string, mixed> $value */
    private function upsertTicket(array $value, Carbon $now): Ticket
    {
        $vendorId = $value['id'] ?? null;

        if (!$vendorId) {
            throw new \InvalidArgumentException('Ticket entry is missing id.');
        }

        $detail = $value['raw_detail']['ticket'] ?? [];
        $raw = $detail !== [] ? $detail : $this->stripCacheMetadata($value);

        return Ticket::updateOrCreate(
            ['vendor_id' => $vendorId],
            [
                'ticket_number' => $value['ticket_number'] ?? '',
                'subject' => $value['summary'] ?? $detail['summary'] ?? null,
                'description' => $detail['description'] ?? null,
                'client_name' => $value['requester_name'] ?? $detail['requester_name'] ?? null,
                'status' => $value['status'] ?? $detail['status'] ?? null,
                'github_branches' => $this->githubBranchesFromCache($value),
                'last_comment_at' => $value['last_comment_at'] ?? null,
                'raw' => $raw,
                'synced_at' => $this->parseSyncedAt($value['detail_synced_at'] ?? $value['github_synced_at'] ?? null) ?? $now,
            ]
        );
    }

    /** @param array<string, mixed> $value */
    private function syncComments(Ticket $ticket, array $value): int
    {
        $comments = $value['raw_detail']['ticket']['comments'] ?? [];

        if (!is_array($comments) || $comments === []) {
            return 0;
        }

        $synced = 0;

        foreach ($comments as $comment) {
            if (!is_array($comment)) {
                continue;
            }

            $vendorId = $comment['id'] ?? null;
            $attributes = [
                'author_name' => $comment['author_name'] ?? $comment['author'] ?? null,
                'body' => $comment['content'] ?? $comment['body'] ?? null,
                'comment_type' => $comment['comment_type'] ?? null,
                'commented_at' => $comment['created_at'] ?? $comment['createdAt'] ?? null,
                'raw' => $comment,
            ];

            if ($vendorId) {
                $ticket->comments()->updateOrCreate(
                    ['vendor_id' => $vendorId],
                    $attributes
                );
            } else {
                Comment::create(array_merge(['ticket_id' => $ticket->id], $attributes));
            }

            $synced++;
        }

        return $synced;
    }

    /** @param array<string, mixed> $value */
    private function githubBranchesFromCache(array $value): ?array
    {
        $meta = $value['github_branch_meta'] ?? null;

        if (!is_array($meta) || $meta === []) {
            return null;
        }

        $primary = $value['github_branch_name'] ?? null;

        return array_map(
            fn (array $branch) => [
                'name' => $branch['name'],
                'merged' => (bool) ($branch['merged'] ?? false),
                'openPr' => (bool) ($branch['openPr'] ?? false),
                'is_default' => $primary !== null && ($branch['name'] ?? '') === $primary,
            ],
            $meta
        );
    }

    /** @param array<string, mixed> $value */
    private function stripCacheMetadata(array $value): array
    {
        $strip = [
            'github_branch_exists',
            'github_branch_name',
            'github_matching_branches',
            'github_branch_meta',
            'github_commit_count',
            'github_files_changed',
            'github_synced_at',
            'github_error',
            'detail_synced_at',
            'raw_detail',
        ];

        return array_diff_key($value, array_flip($strip));
    }

    private function parseSyncedAt(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
