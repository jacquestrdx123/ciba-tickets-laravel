<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\TicketSyncService;
use App\Services\VendorTicketApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerClosedTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_flags_tickets_missing_from_vendor_list(): void
    {
        Ticket::create([
            'vendor_id' => 101,
            'ticket_number' => 'T-101',
            'subject' => 'Still open',
            'closed_on_customer_side' => false,
        ]);

        Ticket::create([
            'vendor_id' => 102,
            'ticket_number' => 'T-102',
            'subject' => 'Closed by customer',
            'closed_on_customer_side' => false,
        ]);

        $this->mock(VendorTicketApi::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetch')
                ->once()
                ->with(['action' => 'list'])
                ->andReturn([
                    'tickets' => [
                        ['id' => 101, 'ticket_number' => 'T-101', 'subject' => 'Still open', 'status' => 'open'],
                    ],
                    'meta' => ['has_more' => false],
                ]);
        });

        app(TicketSyncService::class)->syncList();

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 101,
            'closed_on_customer_side' => false,
        ]);

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 102,
            'closed_on_customer_side' => true,
        ]);

        $this->assertNotNull(Ticket::where('vendor_id', 102)->value('closed_on_customer_side_at'));
    }

    public function test_reconciliation_clears_flag_when_ticket_reappears(): void
    {
        Ticket::create([
            'vendor_id' => 201,
            'ticket_number' => 'T-201',
            'subject' => 'Reopened',
            'closed_on_customer_side' => true,
            'closed_on_customer_side_at' => now()->subDay(),
        ]);

        $this->mock(VendorTicketApi::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetch')
                ->once()
                ->with(['action' => 'list'])
                ->andReturn([
                    'tickets' => [
                        ['id' => 201, 'ticket_number' => 'T-201', 'subject' => 'Reopened', 'status' => 'open'],
                    ],
                    'meta' => ['has_more' => false],
                ]);
        });

        app(TicketSyncService::class)->syncList();

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 201,
            'closed_on_customer_side' => false,
            'closed_on_customer_side_at' => null,
        ]);
    }

    public function test_empty_vendor_list_does_not_flag_existing_tickets(): void
    {
        Ticket::create([
            'vendor_id' => 301,
            'ticket_number' => 'T-301',
            'subject' => 'Should stay open',
            'closed_on_customer_side' => false,
        ]);

        $this->mock(VendorTicketApi::class, function (MockInterface $mock): void {
            $mock->shouldReceive('fetch')
                ->once()
                ->with(['action' => 'list'])
                ->andReturn([
                    'tickets' => [],
                    'meta' => ['has_more' => false],
                ]);
        });

        app(TicketSyncService::class)->syncList();

        $this->assertDatabaseHas('tickets', [
            'vendor_id' => 301,
            'closed_on_customer_side' => false,
        ]);
    }

    public function test_api_includes_closed_on_customer_side_fields(): void
    {
        Ticket::create([
            'vendor_id' => 401,
            'ticket_number' => 'T-401',
            'subject' => 'Customer closed',
            'closed_on_customer_side' => true,
            'closed_on_customer_side_at' => '2026-06-24 10:00:00',
        ]);

        $response = $this->withApiToken()->getJson('/api/tickets');

        $response->assertOk()
            ->assertJsonPath('tickets.0.vendor_id', 401)
            ->assertJsonPath('tickets.0.closed_on_customer_side', true)
            ->assertJsonPath('tickets.0.closed_on_customer_side_at', fn ($value) => $value !== null);
    }

    public function test_api_filters_by_closed_on_customer_side(): void
    {
        Ticket::create([
            'vendor_id' => 501,
            'ticket_number' => 'T-501',
            'subject' => 'Open',
            'closed_on_customer_side' => false,
        ]);

        Ticket::create([
            'vendor_id' => 502,
            'ticket_number' => 'T-502',
            'subject' => 'Closed',
            'closed_on_customer_side' => true,
        ]);

        $response = $this->withApiToken()->getJson('/api/tickets?closed_on_customer_side=1');

        $response->assertOk()
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.vendor_id', 502);
    }
}
