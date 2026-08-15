<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Panel de organizacion.
 *
 * Deja de ser una vista de Blade sin controlador. Las URLs viajan como props
 * porque React no conoce las rutas nombradas de Laravel: escribirlas en el
 * componente crearia una segunda verdad sobre las mismas direcciones.
 */
final class DashboardController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        return Inertia::render('Admin/Dashboard', [
            'branchesUrl' => route('admin.branches.index'),
            'peopleUrl' => route('admin.people.index'),
            'surveysUrl' => route('admin.surveys.index'),
        ]);
    }
}
