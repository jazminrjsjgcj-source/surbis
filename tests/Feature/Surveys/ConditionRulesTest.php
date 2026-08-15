<?php

declare(strict_types=1);

namespace Tests\Feature\Surveys;

use App\Domain\Surveys\ConditionRules;
use Tests\TestCase;

/**
 * Que ordenes rompen una condicion. RF-AO-BLD-007.
 *
 * Sin base de datos: decidir si un orden es valido es una funcion pura sobre
 * la lista de preguntas. ANEXO 1 seccion 70.
 */
final class ConditionRulesTest extends TestCase
{
    public function test_una_condicion_hacia_atras_es_valida(): void
    {
        // La 2 depende de la 1: quien contesta ya respondio la 1 al llegar.
        $this->assertTrue($this->rules()->allows([
            $this->question('a', 'Primera'),
            $this->question('b', 'Segunda', dependsOn: 'a'),
        ]));
    }

    public function test_una_condicion_hacia_delante_se_detecta(): void
    {
        /*
         * La 1 depende de la 2. Quien contesta llegaria a la 1 sin haber
         * respondido de que depende, y la pregunta no podria decidir si
         * mostrarse.
         */
        $rotas = $this->rules()->forwardConditions([
            $this->question('a', 'Primera', dependsOn: 'b'),
            $this->question('b', 'Segunda'),
        ]);

        $this->assertCount(1, $rotas);
        $this->assertSame(1, $rotas->first()['position']);
        $this->assertSame(2, $rotas->first()['depends_on_position']);
    }

    public function test_una_pregunta_no_puede_depender_de_si_misma(): void
    {
        // Es el unico ciclo posible con una sola condicion. La base tambien
        // lo impide con un CHECK.
        $this->assertFalse($this->rules()->allows([
            $this->question('a', 'Sola', dependsOn: 'a'),
        ]));
    }

    public function test_una_condicion_sin_su_pregunta_origen_se_detecta(): void
    {
        // Alguien borro la pregunta de la que esta dependia.
        $rotas = $this->rules()->forwardConditions([
            $this->question('b', 'Huerfana', dependsOn: 'desaparecida'),
        ]);

        $this->assertCount(1, $rotas);
        $this->assertSame(0, $rotas->first()['depends_on_position']);
    }

    public function test_las_preguntas_sin_condicion_no_estorban(): void
    {
        $this->assertTrue($this->rules()->allows([
            $this->question('a', 'Una'),
            $this->question('b', 'Dos'),
            $this->question('c', 'Tres'),
        ]));
    }

    public function test_se_puede_saber_que_depende_de_una_pregunta(): void
    {
        /*
         * "No se puede borrar" sin decir que lo impide obliga a probar una
         * por una.
         */
        $dependientes = $this->rules()->dependentsOf([
            $this->question('a', 'Origen'),
            $this->question('b', 'Depende', dependsOn: 'a'),
            $this->question('c', 'Libre'),
            $this->question('d', 'Tambien depende', dependsOn: 'a'),
        ], 'a');

        $this->assertSame([2, 4], $dependientes);
    }

    public function test_reordenar_hacia_delante_rompe_y_se_ve(): void
    {
        // El caso real: la 1 y la 2 estaban bien, alguien las intercambia.
        $preguntas = [
            $this->question('a', 'Origen'),
            $this->question('b', 'Depende', dependsOn: 'a'),
        ];

        $this->assertTrue($this->rules()->allows($preguntas));

        $intercambiadas = [$preguntas[1], $preguntas[0]];

        $this->assertFalse($this->rules()->allows($intercambiadas));
    }

    /** @return array<string, mixed> */
    private function question(string $ulid, string $text, ?string $dependsOn = null): array
    {
        return [
            'ulid' => $ulid,
            'text' => $text,
            'condition' => $dependsOn === null
                ? null
                : ['depends_on_ulid' => $dependsOn, 'option_ulid' => 'cualquiera'],
        ];
    }

    private function rules(): ConditionRules
    {
        return app(ConditionRules::class);
    }
}
