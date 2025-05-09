<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PassportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        # Criar ou atualizar Personal Access Client
        $personalClient = Client::updateOrCreate(
            ['personal_access_client' => true, 'password_client' => false],
            [
                'name' => 'Personal Access Client',
                'secret' => Str::random(40),
                'redirect' => env('APP_URL', 'http://localhost'),
                'personal_access_client' => true,
                'password_client' => false,
                'revoked' => false,
            ]
        );

        # Criar ou atualizar Password Grant Client
        Client::updateOrCreate(
            ['password_client' => true, 'personal_access_client' => false],
            [
                'name' => 'Password Grant Client',
                'secret' => Str::random(40),
                'redirect' => env('APP_URL', 'http://localhost'),
                'personal_access_client' => false,
                'password_client' => true,
                'revoked' => false,
            ]
        );

        # Atualizar variáveis no .env (se necessário)
        DB::table('oauth_personal_access_clients')->updateOrInsert(
            ['client_id' => $personalClient->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        # Cria ou atualiza usuário base
        User::updateOrCreate(
            ['email' => 'layer@gmail.com'],
            [
                'name' => 'Layer Project',
                'email' => 'layer@gmail.com',
                'password' => '$2y$12$IoAh36uDDlskW.d2MuCfZ.heoiaxhP7N5ljkwLQ6uulVNFikpPQrq'
            ]
        );

        $this->command->info('✅ Passport Clients e Personal Access configurados com sucesso!');
    }
}
