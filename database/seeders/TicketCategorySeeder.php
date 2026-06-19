<?php

namespace Database\Seeders;

use App\Models\TicketCategory;
use Illuminate\Database\Seeder;

class TicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Bug / broken functionality', 'color' => '#185FA5'],
            ['name' => 'Feature / enhancement request', 'color' => '#0F6E56'],
            ['name' => 'Data / reporting', 'color' => '#BA7517'],
            ['name' => 'Document / file management', 'color' => '#993C1D'],
            ['name' => 'CPD', 'color' => '#534AB7'],
            ['name' => 'Tax / licence', 'color' => '#3B6D11'],
            ['name' => 'Membership / member profile', 'color' => '#993556'],
            ['name' => 'ECMS / applications', 'color' => '#888780'],
            ['name' => 'Payments / billing', 'color' => '#D4537E'],
            ['name' => 'Email / notifications', 'color' => '#1D9E75'],
        ];

        foreach ($categories as $category) {
            TicketCategory::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
