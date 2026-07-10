<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class PassportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # Passport 13 dropped the boolean client flags for a grant_types column, so the client is
        # created through the repository and identified by its grants. Only the password grant
        # client is needed — /login issues tokens through it — so the personal access client is
        # not seeded.
        $exists = Client::query()
            ->whereJsonContains('grant_types', 'password')
            ->where('revoked', false)
            ->exists();

        if (! $exists) {
            $client = app(ClientRepository::class)->createPasswordGrantClient('Password Grant Client', confidential: true);

            # Passport 13 hashes the secret on save; store a known value from config so the issuer
            # can send the same plain text (the stored hash cannot be read back).
            $client->secret = config('tokens.password_client_secret');
            $client->save();
        }

        $this->command->info('Passport clients configured.');
    }
}
