<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Vacio a proposito.
     *
     * El seeder que genera Laravel crea un "Test User" suelto, sin
     * organizacion ni membresia. Con este modelo eso es un usuario que no
     * puede iniciar sesion en ningun sitio: RF-GEN-001 resuelve la
     * organizacion activa despues de autenticar, y sin membresia no hay
     * ninguna. Un dato que parece util y no lo es.
     *
     * Los datos de desarrollo llegan en la Fase 1 cuando exista el caso de
     * uso que crea organizacion, usuario y membresia en una transaccion.
     */
    public function run(): void
    {
        //
    }
}
