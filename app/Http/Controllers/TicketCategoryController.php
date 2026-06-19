<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TicketCategory::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:ticket_categories,name'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $category = TicketCategory::create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, TicketCategory $ticketCategory): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:ticket_categories,name,'.$ticketCategory->id],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $ticketCategory->update($validated);

        return response()->json($ticketCategory);
    }

    public function destroy(TicketCategory $ticketCategory): JsonResponse
    {
        $ticketCategory->delete();

        return response()->json(null, 204);
    }

    public function assignToTicket(Request $request, string $vendorId): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
        ]);

        $ticket = Ticket::where('vendor_id', $vendorId)->firstOrFail();
        $newCategoryId = $validated['category_id'];

        if (
            $ticket->category_id !== null
            && $newCategoryId !== null
            && (int) $ticket->category_id !== (int) $newCategoryId
        ) {
            return response()->json([
                'message' => 'Ticket already has a category. Uncategorize first or assign the same category.',
                'category_id' => $ticket->category_id,
            ], 409);
        }

        $ticket->update(['category_id' => $newCategoryId]);

        return response()->json(['category_id' => $ticket->category_id]);
    }
}
