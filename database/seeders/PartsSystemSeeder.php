<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PartsSystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UserPartsImportSeeder::class);
    }
}
