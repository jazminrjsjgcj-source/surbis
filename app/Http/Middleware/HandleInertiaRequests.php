<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Localization\TextDirection;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Lo que toda pantalla necesita saber, resuelto en el servidor.
 *
 * El idioma y la direccion del texto siguen decidiendose aqui y no en el
 * navegador: es lo mismo que hacia el layout de Blade. Si se resolvieran en
 * cliente, la primera pintada saldria en la direccion equivocada y el arabe
 * daria un salto visible al cargar.
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user === null ? null : [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],

            'locale' => app()->getLocale(),
            'dir' => TextDirection::current(),

            /*
             * Las traducciones viajan como props.
             *
             * lang/es/*.php no lo lee React, asi que hay dos opciones:
             * duplicar los textos en el cliente —dos verdades sobre lo mismo,
             * que es como acaban diciendo cosas distintas— o mandarlos. Se
             * mandan.
             *
             * Solo los dos archivos que usa la interfaz. Si algun dia pesan,
             * se recortan por pagina; hoy son unos pocos kilobytes y
             * partirlos seria optimizar sin medir.
             */
            'translations' => [
                'interface' => trans('interface'),
                'auth' => trans('auth'),
                'passwords' => trans('passwords'),
            ],

            // El mensaje de exito de la accion anterior. Inertia ya comparte
            // los errores de validacion por su cuenta.
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
