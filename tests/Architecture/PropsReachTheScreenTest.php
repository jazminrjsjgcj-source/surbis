<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Las URL que manda el servidor llegan a alguna pantalla.
 *
 * ESTA PRUEBA NACE DE TRES FALLOS DEL MISMO DIA, y el patron es siempre igual:
 * una pieza escrita entera a la que nadie puede llegar.
 *
 *   la subida de imagenes    dominio, validacion y pantalla — sin ruta
 *   la vista previa          controlador y pantalla — sin ruta
 *   el enlace de vista previa  ruta y prop — sin etiqueta que lo pinte
 *
 * Nada rompia. Las pruebas comprueban lo que HAY, y una funcionalidad
 * inalcanzable simplemente no esta: se descubre cuando alguien intenta usarla,
 * que en este proyecto fue meses despues de escribirla.
 *
 * Aqui se mira el otro extremo de la cadena: si un controlador se molesta en
 * calcular una URL y mandarla, alguien tendria que usarla.
 */
final class PropsReachTheScreenTest extends TestCase
{
    /**
     * Props que se mandan a proposito sin que ninguna pantalla las nombre.
     *
     * Cada una con su motivo: una excepcion sin explicar acaba siendo donde
     * se esconde el siguiente olvido.
     *
     * @var list<string>
     */
    private const SIN_USO_ESPERADO = [
        // Las consume AdminShell para todas las pantallas, no cada una.
        'logoutUrl',
        'switchUrl',
    ];

    public function test_toda_url_que_se_manda_se_usa_en_su_pantalla(): void
    {
        $sinUsar = [];

        foreach ($this->renders() as $render) {
            $pantalla = $this->screenFile($render['component']);

            if ($pantalla === null) {
                // Si la pantalla no existe, lo dice la otra prueba.
                continue;
            }

            $codigo = file_get_contents($pantalla) ?: '';

            foreach ($render['urlProps'] as $prop) {
                if (in_array($prop, self::SIN_USO_ESPERADO, true)) {
                    continue;
                }

                if (! str_contains($codigo, $prop)) {
                    $sinUsar[] = "{$render['component']} recibe «{$prop}» y no lo usa"
                        ." ({$render['controller']})";
                }
            }
        }

        sort($sinUsar);

        $this->assertSame([], $sinUsar, $this->explain($sinUsar));
    }

    /**
     * Cada Inertia::render de los controladores, con las props que acaban en
     * "Url".
     *
     * Se buscan SOLO las de URL a proposito: son las que llevan a una
     * funcionalidad, y una que no se usa significa que no hay forma de
     * llegar. Otras props sin usar son ruido —datos que la pantalla podria
     * necesitar mañana— y llenarian la prueba de falsos avisos.
     *
     * @return list<array{controller: string, component: string, urlProps: list<string>}>
     */
    private function renders(): array
    {
        $base = app_path('Http/Controllers');

        if (! is_dir($base)) {
            return [];
        }

        $encontrados = [];

        foreach (Finder::create()->files()->in($base)->name('*.php') as $archivo) {
            /** @var SplFileInfo $archivo */
            $codigo = file_get_contents($archivo->getRealPath()) ?: '';

            preg_match_all(
                "/Inertia::render\(\s*'([^']+)'\s*,\s*\[(.*?)\n        \]\)/s",
                $codigo,
                $coincidencias,
                PREG_SET_ORDER,
            );

            foreach ($coincidencias as $render) {
                preg_match_all("/'(\w*[Uu]rl)'\s*=>/", $render[2], $props);

                if ($props[1] === []) {
                    continue;
                }

                $encontrados[] = [
                    'controller' => $archivo->getFilename(),
                    'component' => $render[1],
                    'urlProps' => array_values(array_unique($props[1])),
                ];
            }
        }

        return $encontrados;
    }

    /**
     * El archivo de la pantalla, si existe.
     *
     * Inertia nombra los componentes con la ruta relativa dentro de pages,
     * asi que la conversion es directa.
     */
    private function screenFile(string $component): ?string
    {
        $ruta = resource_path('js/pages/'.Str::replace('.', '/', $component).'.tsx');

        return file_exists($ruta) ? $ruta : null;
    }

    /** @param list<string> $sinUsar */
    private function explain(array $sinUsar): string
    {
        if ($sinUsar === []) {
            return '';
        }

        return "URL que el servidor manda y la pantalla no usa:\n  "
            .implode("\n  ", $sinUsar)
            ."\n\nO falta el enlace en la pantalla, o la prop sobra."
            ."\nSi es a proposito, va a SIN_USO_ESPERADO con su motivo.";
    }
}
