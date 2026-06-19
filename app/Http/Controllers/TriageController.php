<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TriageController extends Controller
{
    private string $storageKey = 'triage/awaiting-client.json';

    private function readMap(): array
    {
        if (!Storage::exists($this->storageKey)) {
            return [];
        }
        $raw = Storage::get($this->storageKey);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeMap(array $map): void
    {
        Storage::put($this->storageKey, json_encode($map, JSON_PRETTY_PRINT));
    }

    public function index()
    {
        $map = $this->readMap();
        $records = array_values($map);
        usort($records, fn($a, $b) => strcmp($b['awaiting_client_at'], $a['awaiting_client_at']));
        return response()->json(['records' => $records]);
    }

    public function store(Request $request)
    {
        $ticketId = $request->input('ticket_id');
        $ticketNumber = trim((string) $request->input('ticket_number', ''));

        $n = intval($ticketId);
        if (!is_numeric($ticketId) || $n <= 0 || $n != $ticketId) {
            abort(400, 'ticket_id must be a positive integer');
        }
        if (!$ticketNumber) {
            abort(400, 'ticket_number is required');
        }

        $note = $request->input('note');
        $note = ($note !== null && $note !== '') ? substr(trim((string) $note), 0, 500) : null;

        $lastCommentAt = $request->input('last_comment_at');
        $lastCommentAt = ($lastCommentAt !== null && $lastCommentAt !== '') ? trim((string) $lastCommentAt) : null;

        $map = $this->readMap();
        $record = [
            'ticket_id' => $n,
            'ticket_number' => $ticketNumber,
            'awaiting_client_at' => now()->toISOString(),
            'awaiting_client_note' => $note,
            'parked_last_comment_at' => $lastCommentAt,
        ];
        $map[(string) $n] = $record;
        $this->writeMap($map);

        return response()->json(['success' => true, 'record' => $record]);
    }

    public function destroy(int $ticketId)
    {
        if ($ticketId <= 0) {
            abort(400, 'ticketId must be a positive integer');
        }

        $map = $this->readMap();
        $key = (string) $ticketId;

        if (!isset($map[$key])) {
            abort(404, 'Ticket is not in the awaiting-client list');
        }

        unset($map[$key]);
        $this->writeMap($map);

        return response()->json(['success' => true, 'ticket_id' => $ticketId]);
    }
}
