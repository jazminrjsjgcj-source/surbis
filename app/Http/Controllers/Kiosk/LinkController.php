<?php

declare(strict_types=1);

namespace App\Http\Controllers\Kiosk;

use App\Application\Kiosk\Exceptions\StationNotReady;
use App\Application\Kiosk\LinkStation;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveKioskStation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Vincular la tableta. Pantalla propia, no la de bienvenida.
 *
 * Son dos momentos distintos: configurar una vez, atender miles de veces.
 * Mezclarlos dejaria un campo de clave visible en una pantalla que ve el
 * publico.
 */
final class LinkController extends Controller
{
    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        // Si ya esta vinculada, no hay nada que hacer aqui.
        if ($request->attributes->get(ResolveKioskStation::REQUEST_ATTRIBUTE) !== null) {
            return redirect()->route('kiosk.welcome');
        }

        return Inertia::render('Kiosk/Link', [
            'action' => route('kiosk.link.store'),
        ]);
    }

    public function store(Request $request, LinkStation $stations): RedirectResponse
    {
        $request->validate([
            'key' => ['required', 'string', 'max:40'],
        ]);

        try {
            [, $token] = $stations->link($request->string('key')->toString());
        } catch (StationNotReady $problema) {
            return back()->withErrors([
                'key' => __("interface.kiosk.{$problema->key}"),
            ]);
        }

        /*
         * La cookie dura un ano y va con las tres protecciones.
         *
         * httpOnly: JavaScript no puede leerla, asi que un script inyectado
         * en la pagina no se lleva la credencial.
         *
         * secure: solo viaja por HTTPS. En una ventanilla con wifi
         * compartido, sin esto cualquiera en la misma red la ve.
         *
         * sameSite lax: no se manda desde otro sitio, lo que impide que una
         * pagina externa haga peticiones en nombre de la tableta.
         */
        return redirect()->route('kiosk.welcome')->withCookie(
            Cookie::make(
                ResolveKioskStation::COOKIE,
                $token,
                minutes: 60 * 24 * 365,
                path: '/',
                domain: null,
                secure: $request->secure(),
                httpOnly: true,
                raw: false,
                sameSite: 'lax',
            )
        );
    }
}
