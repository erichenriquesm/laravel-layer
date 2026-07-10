<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

class PassportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # firstOrCreate, not updateOrCreate: a fresh Str::random(40) in the update values would
        # rotate the client secret on every re-seed, invalidating whatever held the old one.
        $personalClient = Client::firstOrCreate(
            ['personal_access_client' => true, 'password_client' => false],
            [
                'name' => 'Personal Access Client',
                'secret' => Str::random(40),
                'redirect' => env('APP_URL', 'http://localhost'),
                'revoked' => false,
            ]
        );

        # The password grant needs this client; /login issues tokens through it.
        Client::firstOrCreate(
            ['password_client' => true, 'personal_access_client' => false],
            [
                'name' => 'Password Grant Client',
                'secret' => Str::random(40),
                'redirect' => env('APP_URL', 'http://localhost'),
                'revoked' => false,
            ]
        );

        DB::table('oauth_personal_access_clients')->updateOrInsert(
            ['client_id' => $personalClient->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        # No seeded user: register one through POST /register instead of shipping a
        # known account. A hardcoded credential in a starter is a foothold in production.

        $this->command->info('Passport clients configured.');
    }
}
