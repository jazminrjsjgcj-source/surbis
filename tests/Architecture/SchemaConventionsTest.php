<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
 * Estas dos pruebas no comprueban una funcionalidad. Vigilan una invariante
 * del esquema que, si se rompe, no produce ningun error visible: produce
 * fechas que se desplazan segun el servidor, y nadie se entera hasta que los
 * informes de una organizacion en otra zona horaria salen mal.
 *
 * Es la primera aplicacion concreta de "desconfiar del mecanismo que no da
 * error" (ANEXO 1, seccion 4).
 */
final class SchemaConventionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Sin esta comprobacion, ejecutar la suite sobre SQLite dejaria pasar la
     * prueba siguiente por el camino equivocado: information_schema no existe
     * en SQLite, asi que no encontraria infracciones porque no encontraria
     * nada. Una prueba verde que no mira nada es peor que una roja.
     */
    public function test_la_suite_se_ejecuta_contra_postgresql(): void
    {
        $this->assertSame(
            'pgsql',
            DB::connection()->getDriverName(),
            'ANEXO 1 seccion 68: las pruebas deben ejecutarse contra PostgreSQL, '.
            'no contra SQLite. Revisa la conexion configurada en phpunit.xml.',
        );
    }

    public function test_no_almacena_fechas_sin_zona_horaria(): void
    {
        $columns = DB::select(<<<'SQL'
            select table_name, column_name, data_type
              from information_schema.columns
             where table_schema = current_schema()
               and data_type in ('timestamp without time zone', 'time without time zone')
             order by table_name, column_name
            SQL);

        $offenders = array_map(
            static fn (object $column): string => sprintf(
                '%s.%s (%s)',
                $column->table_name,
                $column->column_name,
                $column->data_type,
            ),
            $columns,
        );

        $this->assertSame(
            [],
            $offenders,
            "RNF-GEN-013: estas columnas guardan fecha sin zona horaria.\n".
            "Sustituye timestamp() por timestampTz() y timestamps() por\n".
            'timestampsTz() en la migracion correspondiente.',
        );
    }
}
