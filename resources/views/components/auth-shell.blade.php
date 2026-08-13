{{--
    Marco de las pantallas sin sesion: acceso, eleccion de organizacion y,
    mas adelante, recuperacion de contrasena y verificacion en dos pasos.

    El degradado vino con la textura de olas viene de la identidad municipal
    de La Paz, igual que la paleta. Es el unico elemento decorativo del
    sistema y esta aqui a proposito: es la primera pantalla que ve alguien y
    la unica donde no compite con datos.
--}}
@props(['title', 'subtitle' => null])

<x-layout :title="$title">
    <div class="auth-backdrop grid min-h-screen place-items-center p-6">
        <div class="w-full max-w-100">
            <header class="mb-5 text-center">
                <p class="font-display text-3xl leading-none font-extrabold tracking-tight text-white">
                    PULSO <span class="font-script text-wordmark-accent text-2xl">Sí</span>
                </p>

                @if ($subtitle)
                    <p class="mt-1.5 text-xs font-semibold tracking-widest text-white/90 uppercase">
                        {{ $subtitle }}
                    </p>
                @endif
            </header>

            <div class="card card-pad">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-layout>
