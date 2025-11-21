<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call(ParametrosPLDSeeder::class);
        $this->call(TipoPersonaSeeder::class);
        $this->call(OcupacionGiroSeeder::class);
        $this->call(EstadoSeeder::class);
        $this->call(SistemaOrigenSeeder::class);
        $this->call(TipoMonedaSeeder::class);
        $this->call(FormaPagoSeeder::class);
    }
}
