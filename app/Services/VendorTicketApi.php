<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VendorTicketApi
{
    private const BASE_URL = 'https://tools.myciba.org/ticket-vendor-api.php';

    public function fetch(array $query): mixed
    {
        $token = config('app.vendor_api_token');
        if (!$token) {
            throw new RuntimeException('Missing VENDOR_API_TOKEN on the server.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->get(self::BASE_URL, $query);

        if ($response->failed()) {
            $body = $response->json() ?? ['raw' => $response->body()];
            $message = $body['message'] ?? $response->body();
            throw new RuntimeException(is_string($message) ? $message : 'Vendor API error');
        }

        return $response->json();
    }
}
