<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Casts;

use App\Domain\Surveys\VersionSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<VersionSettings, VersionSettings|array<string, mixed>|null>
 */
final class VersionSettingsCast implements CastsAttributes
{
    /**
     * Nunca devuelve null: una version sin configuracion guardada usa la de
     * por defecto. Asi ninguna pantalla tiene que preguntar "y si no hay
     * nada", que es donde nacen los null que se propagan.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): VersionSettings
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return VersionSettings::fromArray(is_array($value) ? $value : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $settings = $value instanceof VersionSettings
            ? $value
            : VersionSettings::fromArray(is_array($value) ? $value : null);

        return [$key => json_encode($settings->toArray(), JSON_THROW_ON_ERROR)];
    }
}
