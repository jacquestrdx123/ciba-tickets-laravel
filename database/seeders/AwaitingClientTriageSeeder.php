<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class AwaitingClientTriageSeeder extends Seeder
{
    private const TRIAGE_STORAGE_KEY = 'triage/awaiting-client.json';

    private const DATA_PATH = 'data/awaiting-client-triage.json';

    public function run(): void
    {
        $path = database_path(self::DATA_PATH);

        if (!is_readable($path)) {
            $this->command?->error('Missing '.self::DATA_PATH.' — export the awaiting-client triage map from production and place it at database/'.self::DATA_PATH);

            return;
        }

        $records = json_decode(file_get_contents($path), true);

        if (!is_array($records)) {
            throw new \RuntimeException('Invalid JSON in '.self::DATA_PATH);
        }

        Storage::put(self::TRIAGE_STORAGE_KEY, json_encode($records, JSON_PRETTY_PRINT));

        $this->command?->info(sprintf('Seeded %d awaiting-client triage records.', count($records)));
    }
}
