<?php

namespace App\Http\Controllers;

use App\Jobs\SyncGithubBranchesJob;
use App\Models\Ticket;
use Illuminate\Http\Request;

class GithubController extends Controller
{
    /** Return stored GitHub branches for a ticket. */
    public function branch(Request $request)
    {
        $ticketNumber = trim($request->query('ticket_number', ''));
        if (!$ticketNumber) {
            abort(400, 'Query parameter ticket_number is required');
        }

        $ticket = Ticket::where('ticket_number', $ticketNumber)->first();
        $branches = $ticket?->github_branches ?? [];

        return response()->json([
            'success'       => true,
            'ticket_number' => $ticketNumber,
            'branches'      => $branches,
            'matched_count' => count($branches),
        ]);
    }

    /** Queue a GitHub branch sync for all tickets. */
    public function sync()
    {
        $status = SyncGithubBranchesJob::status();

        if (in_array($status['status'], ['queued', 'running'], true)) {
            return response()->json([
                'queued'  => false,
                'status'  => $status['status'],
                'message' => 'A GitHub branch sync is already in progress.',
            ], 409);
        }

        SyncGithubBranchesJob::dispatch();
        SyncGithubBranchesJob::setStatus('queued');

        return response()->json([
            'queued' => true,
            'status' => 'queued',
        ], 202);
    }

    /** Return the current GitHub branch sync job status. */
    public function syncStatus()
    {
        return response()->json(SyncGithubBranchesJob::status());
    }
}
