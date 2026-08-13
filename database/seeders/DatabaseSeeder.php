<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Los datos de desarrollo viven en DevelopmentSeeder, que se niega a
     * correr fuera de local. Este solo lo llama.
     */
    public function run(): void
    {
        $this->call(DevelopmentSeeder::class);
    }
}
