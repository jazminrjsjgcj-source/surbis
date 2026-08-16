<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que llega al contestar una encuesta.
 *
 * Aqui solo se valida la FORMA. Que las respuestas sean validas, que la
 * pregunta estuviera visible y que el modo de identidad las admita lo decide
 * SubmitResponse: son reglas de negocio y viven en el dominio.
 */
final class SubmitResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Publica a proposito: quien escanea un cartel no ha iniciado sesion.
        // Que el enlace valga lo comprueba el token, no una sesion.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /*
             * El UUID lo genera EL CLIENTE, una sola vez, al empezar.
             *
             * Es lo que hace posible reintentar sin duplicar: si el envio
             * llego y la confirmacion no, el segundo intento trae el mismo
             * UUID y el servidor devuelve la respuesta que ya guardo.
             *
             * Generarlo aqui no protegeria de nada: cada reintento traeria
             * uno distinto.
             */
            'idempotency_key' => ['required', 'uuid'],

            'answers' => ['present', 'array', 'max:200'],

            /*
             * Cada respuesta es una cadena o una lista de cadenas.
             *
             * Las de seleccion multiple llegan como lista; las demas, como
             * valor suelto. Validar ambas formas aqui evita que un array
             * inesperado reviente dentro del caso de uso.
             */
            'answers.*' => ['nullable'],

            'comment' => ['nullable', 'string', 'max:2000'],

            'identity' => ['nullable', 'array'],
            'identity.name' => ['nullable', 'string', 'max:160'],
            'identity.email' => ['nullable', 'email', 'max:255'],
            'identity.phone' => ['nullable', 'string', 'max:40'],
            'identity.consent' => ['nullable', 'boolean'],
        ];
    }
}
