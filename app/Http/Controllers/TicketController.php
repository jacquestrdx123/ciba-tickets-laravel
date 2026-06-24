<?php

namespace App\Http\Controllers;

use App\Jobs\SyncTicketsJob;
use App\Models\Ticket;
use App\Services\VendorTicketApi;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function __construct(private VendorTicketApi $vendorApi) {}

    /** Return all locally-stored tickets from MySQL. */
    public function index(Request $request)
    {
        $uncategorizedOnly = $request->boolean('uncategorized');
        $customerClosedOnly = $request->boolean('closed_on_customer_side');

        $tickets = Ticket::query()
            ->when($uncategorizedOnly, fn ($query) => $query->whereNull('category_id'))
            ->when($customerClosedOnly, fn ($query) => $query->where('closed_on_customer_side', true))
            ->with(['latestComment', 'category'])
            ->orderByDesc('last_comment_at')
            ->get()
            ->map(fn (Ticket $ticket) => $this->formatTicketListItem($ticket, includeDescription: $uncategorizedOnly));

        return response()->json(['tickets' => $tickets]);
    }

    /** Return a single locally-stored ticket with comments. */
    public function show(string $vendorId)
    {
        $ticket = Ticket::with(['comments', 'category'])
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        return response()->json($this->formatTicket($ticket));
    }

    /** Queue a full ticket sync from the vendor API. */
    public function sync()
    {
        $status = SyncTicketsJob::status();

        if (in_array($status['status'], ['queued', 'running'], true)) {
            return response()->json([
                'queued' => false,
                'status' => $status['status'],
                'message' => 'A ticket sync is already in progress.',
            ], 409);
        }

        SyncTicketsJob::dispatch();
        SyncTicketsJob::setStatus('queued');

        return response()->json([
            'queued' => true,
            'status' => 'queued',
        ], 202);
    }

    /** Return the current ticket sync job status. */
    public function syncStatus()
    {
        return response()->json(SyncTicketsJob::status());
    }

    /** Proxy detail requests to vendor API. */
    public function detail(Request $request)
    {
        $query = array_merge($request->query(), ['action' => 'detail']);

        try {
            return response()->json($this->vendorApi->fetch($query));
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }
    }

    /** Proxy summary requests to vendor API. */
    public function summary(Request $request)
    {
        $query = array_merge($request->query(), ['action' => 'summary']);

        try {
            return response()->json($this->vendorApi->fetch($query));
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function formatTicketListItem(Ticket $ticket, bool $includeDescription = false): array
    {
        $raw = is_array($ticket->raw) ? $ticket->raw : [];
        $branches = $ticket->github_branches ?? [];
        $lastAuthor = $ticket->latestComment?->author_name
            ?? $raw['last_comment_author']
            ?? $raw['lastCommentAuthor']
            ?? null;

        $item = [
            'id' => $ticket->vendor_id,
            'vendor_id' => $ticket->vendor_id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'summary' => $ticket->subject,
            'status' => $ticket->status,
            'closed_on_customer_side' => $ticket->closed_on_customer_side,
            'closed_on_customer_side_at' => $ticket->closed_on_customer_side_at?->toISOString(),
            'client_name' => $ticket->client_name,
            'priority' => $raw['priority'] ?? '',
            'updated_at' => $raw['updated_at'] ?? $raw['updatedAt'] ?? $ticket->synced_at?->toDateTimeString(),
            'last_comment_at' => $ticket->last_comment_at,
            'last_comment_author' => $lastAuthor,
            'github_branches' => $branches,
            'github_branch_exists' => count($branches) > 0,
            'github_branch_name' => $branches[0]['name'] ?? null,
            'github_commit_count' => null,
            'synced_at' => $ticket->synced_at?->toISOString(),
            'category' => $ticket->category ? ['id' => $ticket->category->id, 'name' => $ticket->category->name, 'color' => $ticket->category->color] : null,
        ];

        if ($includeDescription) {
            $item['description'] = $ticket->description;
        }

        return $item;
    }

    /** @return array<string, mixed> */
    private function formatTicket(Ticket $ticket): array
    {
        $raw = is_array($ticket->raw) ? $ticket->raw : [];
        $branches = $ticket->github_branches ?? [];
        $lastAuthor = $ticket->comments->last()?->author_name
            ?? $raw['last_comment_author']
            ?? $raw['lastCommentAuthor']
            ?? null;

        return [
            'id' => $ticket->vendor_id,
            'vendor_id' => $ticket->vendor_id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'status' => $ticket->status,
            'closed_on_customer_side' => $ticket->closed_on_customer_side,
            'closed_on_customer_side_at' => $ticket->closed_on_customer_side_at?->toISOString(),
            'client_name' => $ticket->client_name,
            'priority' => $raw['priority'] ?? '',
            'created_at' => $raw['created_at'] ?? $ticket->created_at?->toDateTimeString(),
            'updated_at' => $raw['updated_at'] ?? $raw['updatedAt'] ?? $ticket->synced_at?->toDateTimeString(),
            'last_comment_at' => $ticket->last_comment_at,
            'last_comment_author' => $lastAuthor,
            'description' => $ticket->description,
            'github_branches' => $branches,
            'github_branch_exists' => count($branches) > 0,
            'github_branch_name' => $branches[0]['name'] ?? null,
            'comments' => $ticket->comments->map->toArray()->values()->all(),
            'synced_at' => $ticket->synced_at?->toISOString(),
            'category' => $ticket->category ? ['id' => $ticket->category->id, 'name' => $ticket->category->name, 'color' => $ticket->category->color] : null,
        ];
    }
}
