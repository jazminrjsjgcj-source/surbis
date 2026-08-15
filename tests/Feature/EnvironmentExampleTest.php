<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La plantilla de entorno tiene que servir para arrancar el proyecto.
 *
 * Existe esta prueba porque el defecto que la motivo era invisible desde
 * dentro: quien ya tenia un .env correcto nunca notaba que el .env.example
 * del repositorio era el de Laravel —sqlite, ingles, puerto 8000—. Solo
 * aparecio al clonar en una maquina nueva, semanas despues.
 *
 * Un archivo de ejemplo que no sirve de ejemplo es un mecanismo que no hace
 * nada, con la particularidad de que solo falla para quien llega nuevo.
 */
final class EnvironmentExampleTest extends TestCase
{
    public function test_la_plantilla_apunta_a_postgresql(): void
    {
        $example = $this->example();

        $this->assertStringContainsString('DB_CONNECTION=pgsql', $example);
        $this->assertStringContainsString('DB_HOST=pgsql', $example);

        // sqlite es el valor por defecto de Laravel. Si vuelve, alguien
        // sobrescribio la plantilla con la del framework.
        $this->assertStringNotContainsString('DB_CONNECTION=sqlite', $example);
    }

    public function test_la_plantilla_declara_las_claves_que_docker_necesita(): void
    {
        // Sin ellas, docker compose arranca con valores vacios y PostgreSQL
        // se niega a levantar por un motivo que no menciona este archivo.
        $example = $this->example();

        foreach (['UID=', 'GID=', 'APP_PORT=', 'FORWARD_DB_PORT=', 'DB_PASSWORD=', 'SEED_PASSWORD='] as $clave) {
            $this->assertStringContainsString($clave, $example, "Falta {$clave} en .env.example.");
        }
    }

    public function test_la_plantilla_no_trae_ninguna_contrasena(): void
    {
        // Las dos claves de credencial existen y estan VACIAS.
        $example = $this->example();

        $this->assertMatchesRegularExpression('/^DB_PASSWORD=\s*$/m', $example);
        $this->assertMatchesRegularExpression('/^SEED_PASSWORD=\s*$/m', $example);
    }

    public function test_la_plantilla_esta_en_espanol_y_en_utc(): void
    {
        $example = $this->example();

        $this->assertStringContainsString('APP_LOCALE=es', $example);
        $this->assertStringContainsString('APP_TIMEZONE=UTC', $example);
    }

    private function example(): string
    {
        $path = base_path('.env.example');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
