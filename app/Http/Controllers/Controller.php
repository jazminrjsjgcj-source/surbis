<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /*
     * Laravel 13 genera este controlador vacio: desde la 11 ya no trae
     * traits. Sin AuthorizesRequests, `$this->authorize(...)` no existe y la
     * llamada revienta con "Call to undefined method".
     *
     * Se anade aqui y no en cada controlador porque la autorizacion no es
     * opcional en este sistema: RA-005 dice que ocultar un boton no es
     * autorizar, y RNF-AO-COL-001 exige Policy en las operaciones de
     * administracion.
     */
    use AuthorizesRequests;
}
