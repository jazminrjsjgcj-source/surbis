<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Domain\Identity\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            /*
             * La contrasena ACTUAL, siempre.
             *
             * Sin esto, cualquiera que encuentre una sesion abierta —una
             * pantalla sin bloquear, un ordenador compartido— podria cambiar
             * la contrasena y quedarse con la cuenta.
             *
             * 'current_password' la comprueba contra la del usuario en sesion
             * sin que haya que escribir la comparacion a mano.
             */
            'current_password' => ['required', 'current_password'],

            /*
             * La MISMA politica que al restablecer por correo.
             *
             * Escribir aqui otras reglas crearia dos definiciones de "una
             * contrasena valida", y el dia que cambie una, la otra se
             * quedaria vieja sin que nadie lo note.
             */
            'password' => ['required', 'confirmed', 'different:current_password', PasswordPolicy::rules()],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'current_password' => __('interface.security.current_password'),
            'password' => __('interface.security.new_password'),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            // El mensaje por defecto habla de "el campo contrasena actual",
            // que no dice lo que pasa.
            'current_password.current_password' => __('interface.security.wrong_current'),
            'password.different' => __('interface.security.same_password'),
        ];
    }
}
