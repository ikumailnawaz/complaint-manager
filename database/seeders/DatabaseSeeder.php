<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the production application database with core users, locations, and parts catalog.
     */
    public function run(): void
    {
        // Ensure standard public storage directories exist
        $disk = Storage::disk('public');
        $disk->makeDirectory('resolutions');
        $disk->makeDirectory('vouchers');
        $disk->makeDirectory('reports');
        $disk->makeDirectory('part-requests');
        $disk->makeDirectory('pm-documents');

        // 1. Seed the 28 corporate users with assigned roles & regions
        $this->call(UserResetSeeder::class);

        // 2. Seed machine models and parts catalog with initial 0 stock
        $this->call(UserPartsImportSeeder::class);
    }
}
