<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Analytics\ExportMetrics;
use App\Application\Analytics\WriteCsv;
use App\Domain\Audit\RecordAuditLog;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\User;
use App\Domain\Responses\Models\Response;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveOrganization;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descargar los indicadores. RF-AO-EXP-*.
 *
 * El umbral viene aplicado desde ExportMetrics, que usa el mismo
 * QueryMetrics que el panel: aqui no hay forma de saltarselo aunque se
 * quisiera.
 */
final class AnalyticsExportController extends Controller
{
    public function __invoke(
        Request $request,
        ExportMetrics $export,
        WriteCsv $csv,
        RecordAuditLog $audit,
    ): StreamedResponse {
        $this->authorize('viewAny', Response::class);

        $membership = $this->activeMembership($request);

        /** @var User $user */
        $user = $request->user();

        $filtros = [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
        ];

        /*
         * Se audita QUE se exporto y CON QUE filtros.
         *
         * Aunque solo salgan indicadores agregados: sacar datos del sistema
         * es una accion que conviene poder revisar despues, y los filtros
         * dicen sobre que periodo y que sucursales se pregunto.
         */
        $audit->record('analytics.exported', $membership->organization, [
            'format' => 'csv',
            'filters' => array_filter($filtros),
        ], actor: $user);

        $filas = $export->rows($membership->organization, $filtros);
        $contenido = $csv->render($filas);

        $nombre = 'indicadores-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            function () use ($contenido): void {
                echo $contenido;
            },
            $nombre,
            [
                // charset=utf-8 explicito, ademas del BOM: algunos programas
                // miran la cabecera y otros el contenido.
                'Content-Type' => 'text/csv; charset=utf-8',
            ],
        );
    }

    private function activeMembership(Request $request): Membership
    {
        /** @var Membership $membership */
        $membership = $request->attributes->get(EnsureActiveOrganization::REQUEST_ATTRIBUTE);

        return $membership;
    }
}
