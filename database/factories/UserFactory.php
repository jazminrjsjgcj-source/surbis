<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= bcrypt('password'),
            'status' => UserStatus::Active,
            'is_platform_admin' => false,
            'remember_token' => Str::random(10),

            /*
             * Estas tres van explicitas aunque su valor por defecto en la
             * base ya sea null.
             *
             * create() devuelve un modelo con SOLO los atributos que se
             * insertaron, no con la fila entera. Y Model::shouldBeStrict()
             * convierte en excepcion leer un atributo que no se cargo. Asi
             * que sin estas lineas, cualquier codigo que consulte
             * hasMfaEnabled() sobre un usuario recien creado por la factory
             * revienta, aunque sobre uno leido de la base funcione.
             *
             * Regla general: la factory declara todas las columnas que el
             * modelo lee.
             */
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'mfa_channel' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => [
            'email_verified_at' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Suspended,
        ]);
    }

    public function platformAdmin(): static
    {
        return $this->state(fn (): array => [
            'is_platform_admin' => true,
        ]);
    }
}
