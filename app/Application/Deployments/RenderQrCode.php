<?php

declare(strict_types=1);

namespace App\Application\Deployments;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * El QR de un enlace publico. RF-AO-DEP-008 y 009.
 *
 * SVG y no PNG: RF-AO-DEP-009 pide formato apto para IMPRESION, y un cartel
 * puede acabar en A5 o en A2. Un PNG de tamano fijo se pixela al ampliarlo;
 * un SVG no.
 */
final class RenderQrCode
{
    /**
     * 320 px de lado como referencia.
     *
     * En SVG el tamano es solo la caja inicial: lo que importa es que el
     * dibujo escale. Se fija para que la vista previa en pantalla tenga un
     * tamano razonable sin CSS extra.
     */
    private const SIZE = 320;

    /**
     * Margen de 2 modulos, el minimo de la norma.
     *
     * Sin zona tranquila alrededor, muchos lectores no encuentran el codigo.
     * Es de los detalles que se descubren cuando el cartel ya esta impreso.
     */
    private const MARGIN = 2;

    public function svg(string $url): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle(self::SIZE, self::MARGIN),
                new SvgImageBackEnd,
            )
        );

        return $writer->writeString($url);
    }
}
