<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * The first admin is created by the web installer, not here. Local
     * development also gets the QA logins from DevUserSeeder.
     */
    public function run(): void
    {
        if (app()->environment('local')) {
            $this->call(DevUserSeeder::class);
        }
    }
}
