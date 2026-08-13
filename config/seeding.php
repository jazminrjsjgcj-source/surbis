<?php

declare(strict_types=1);

return [
    /*
     * Contrasena de las cuentas que crea DevelopmentSeeder.
     *
     * Vive en config y no como env() suelto dentro del seeder por una razon
     * concreta: `php artisan config:cache` congela la configuracion y a
     * partir de ahi env() devuelve null en cualquier sitio que no sea un
     * archivo de config. Un seeder que llama a env() directamente funciona
     * en desarrollo y falla en cuanto alguien cachea la configuracion, sin
     * que el motivo tenga nada que ver con el seeder.
     *
     * Sin valor por defecto a proposito. Una contrasena escrita en el
     * repositorio acaba en produccion tarde o temprano.
     */
    'password' => env('SEED_PASSWORD'),
];
