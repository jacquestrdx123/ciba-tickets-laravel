<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncategorized_filter_returns_only_tickets_without_category_and_includes_description(): void
    {
        $category = TicketCategory::create(['name' => 'CPD', 'color' => '#534AB7']);

        Ticket::create([
            'vendor_id' => 101,
            'ticket_number' => 'T-101',
            'subject' => 'Uncategorized ticket',
            'description' => 'Needs CPD help',
            'category_id' => null,
        ]);

        Ticket::create([
            'vendor_id' => 102,
            'ticket_number' => 'T-102',
            'subject' => 'Categorized ticket',
            'description' => 'Already sorted',
            'category_id' => $category->id,
        ]);

        $response = $this->withSession(['authenticated' => true])
            ->getJson('/api/tickets?uncategorized=1');

        $response->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.vendor_id', 101)
            ->assertJsonPath('tickets.0.description', 'Needs CPD help')
            ->assertJsonPath('tickets.0.category', null);
    }

    public function test_assign_category_succeeds_on_uncategorized_ticket(): void
    {
        $category = TicketCategory::create(['name' => 'CPD', 'color' => '#534AB7']);

        Ticket::create([
            'vendor_id' => 201,
            'ticket_number' => 'T-201',
            'subject' => 'CPD question',
            'category_id' => null,
        ]);

        $response = $this->withSession(['authenticated' => true])
            ->patchJson('/api/tickets/201/category', ['category_id' => $category->id]);

        $response->assertOk()
            ->assertJsonPath('category_id', $category->id);

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 201,
            'category_id' => $category->id,
        ]);
    }

    public function test_assign_category_returns_conflict_when_changing_existing_category(): void
    {
        $cpd = TicketCategory::create(['name' => 'CPD', 'color' => '#534AB7']);
        $bug = TicketCategory::create(['name' => 'Bug / broken functionality', 'color' => '#185FA5']);

        Ticket::create([
            'vendor_id' => 301,
            'ticket_number' => 'T-301',
            'subject' => 'Already categorized',
            'category_id' => $cpd->id,
        ]);

        $response = $this->withSession(['authenticated' => true])
            ->patchJson('/api/tickets/301/category', ['category_id' => $bug->id]);

        $response->assertStatus(409)
            ->assertJsonPath('category_id', $cpd->id);

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 301,
            'category_id' => $cpd->id,
        ]);
    }

    public function test_assign_same_category_is_idempotent(): void
    {
        $category = TicketCategory::create(['name' => 'CPD', 'color' => '#534AB7']);

        Ticket::create([
            'vendor_id' => 401,
            'ticket_number' => 'T-401',
            'subject' => 'Already categorized',
            'category_id' => $category->id,
        ]);

        $response = $this->withSession(['authenticated' => true])
            ->patchJson('/api/tickets/401/category', ['category_id' => $category->id]);

        $response->assertOk()
            ->assertJsonPath('category_id', $category->id);
    }

    public function test_uncategorize_allows_clearing_category(): void
    {
        $category = TicketCategory::create(['name' => 'CPD', 'color' => '#534AB7']);

        Ticket::create([
            'vendor_id' => 501,
            'ticket_number' => 'T-501',
            'subject' => 'Will be cleared',
            'category_id' => $category->id,
        ]);

        $response = $this->withSession(['authenticated' => true])
            ->patchJson('/api/tickets/501/category', ['category_id' => null]);

        $response->assertOk()
            ->assertJsonPath('category_id', null);

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 501,
            'category_id' => null,
        ]);
    }
}
