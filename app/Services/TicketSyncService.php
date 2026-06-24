<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Support\Carbon;

class TicketSyncService
{
    public function __construct(private VendorTicketApi $api) {}

    /**
     * Paginate the vendor list endpoint and upsert stub ticket records.
     *
     * @return array<int, int> Vendor IDs queued for detail sync.
     */
    public function syncList(): array
    {
        $now = now();
        $vendorIds = [];
        $query = ['action' => 'list'];

        do {
            $data = $this->api->fetch($query);

            foreach ($this->extractTicketRows($data) as $row) {
                $vendorId = $row['id'] ?? null;
                if (!$vendorId) {
                    continue;
                }

                $this->upsertTicketFromRow($row, $now);
                $vendorIds[] = $vendorId;
            }

            $meta = is_array($data) ? ($data['meta'] ?? []) : [];
            $hasMore = (bool) ($meta['has_more'] ?? false);
            $cursor = $meta['next_cursor'] ?? null;

            $query = $hasMore && $cursor
                ? ['action' => 'list', 'cursor' => $cursor]
                : [];
        } while ($query !== []);

        $this->reconcileMissingFromVendorList($vendorIds, $now);

        return $vendorIds;
    }

    /**
     * Flag tickets absent from the vendor list as closed on the customer side.
     *
     * @param  array<int, int>  $seenVendorIds
     */
    public function reconcileMissingFromVendorList(array $seenVendorIds, Carbon $now): int
    {
        if ($seenVendorIds === []) {
            return 0;
        }

        $newlyClosed = Ticket::query()
            ->whereNotIn('vendor_id', $seenVendorIds)
            ->where('closed_on_customer_side', false)
            ->update([
                'closed_on_customer_side' => true,
                'closed_on_customer_side_at' => $now,
            ]);

        Ticket::query()
            ->whereIn('vendor_id', $seenVendorIds)
            ->where('closed_on_customer_side', true)
            ->update([
                'closed_on_customer_side' => false,
                'closed_on_customer_side_at' => null,
            ]);

        return $newlyClosed;
    }

    public function syncDetail(int $vendorId): void
    {
        $data = $this->api->fetch([
            'action' => 'detail',
            'ticket_id' => $vendorId,
        ]);

        $row = $this->extractDetailTicket($data);
        if (!$row) {
            return;
        }

        $ticket = $this->upsertTicketFromRow($row, now(), fromDetail: true);

        if (isset($row['comments']) && is_array($row['comments'])) {
            $this->syncComments($ticket, $row['comments']);
        }
    }

    /** @return array{synced: int, synced_at: Carbon} */
    public function sync(): array
    {
        $vendorIds = $this->syncList();

        foreach ($vendorIds as $vendorId) {
            $this->syncDetail($vendorId);
        }

        return [
            'synced'    => count($vendorIds),
            'synced_at' => now(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function extractTicketRows(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }

        return $data['tickets'] ?? (isset($data[0]) ? $data : []);
    }

    /** @return array<string, mixed>|null */
    private function extractDetailTicket(mixed $data): ?array
    {
        if (!is_array($data)) {
            return null;
        }

        $ticket = $data['ticket'] ?? null;

        return is_array($ticket) ? $ticket : null;
    }

    /** @param array<string, mixed> $row */
    private function upsertTicketFromRow(array $row, Carbon $now, bool $fromDetail = false): Ticket
    {
        $vendorId = $row['id'] ?? null;
        if (!$vendorId) {
            throw new \InvalidArgumentException('Ticket row is missing an id.');
        }

        $comments = $row['comments'] ?? [];
        $lastCommentAt = $row['last_comment_at']
            ?? $row['lastCommentAt']
            ?? (is_array($comments) ? $this->latestCommentAt($comments) : null)
            ?? $row['updated_at']
            ?? null;

        $attributes = [
            'ticket_number'   => $row['ticket_number'] ?? $row['ticketNumber'] ?? '',
            'subject'         => $row['subject'] ?? $row['summary'] ?? null,
            'client_name'     => $row['client_name'] ?? $row['clientName'] ?? $row['requester_name'] ?? null,
            'status'          => $row['status'] ?? null,
            'last_comment_at' => $lastCommentAt,
            'raw'             => $row,
            'synced_at'       => $now,
        ];

        if ($fromDetail) {
            $attributes['description'] = $row['description'] ?? null;
        }

        return Ticket::updateOrCreate(
            ['vendor_id' => $vendorId],
            $attributes
        );
    }

    /** @param array<int, array<string, mixed>> $comments */
    private function latestCommentAt(array $comments): ?string
    {
        $latest = null;

        foreach ($comments as $comment) {
            if (!is_array($comment)) {
                continue;
            }

            $createdAt = $comment['created_at'] ?? $comment['createdAt'] ?? null;
            if ($createdAt && ($latest === null || $createdAt > $latest)) {
                $latest = $createdAt;
            }
        }

        return $latest;
    }

    /** @param array<int, array<string, mixed>> $comments */
    private function syncComments(Ticket $ticket, array $comments): void
    {
        foreach ($comments as $comment) {
            if (!is_array($comment)) {
                continue;
            }

            $vendorId = $comment['id'] ?? null;
            $attributes = [
                'author_name' => $comment['author_name'] ?? $comment['author'] ?? null,
                'body' => $comment['body'] ?? $comment['content'] ?? null,
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
        }
    }
}
