<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Casts;

use App\Domain\Surveys\QuestionLimits;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<QuestionLimits, QuestionLimits|array<string, mixed>|null> */
final class QuestionLimitsCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): QuestionLimits
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return QuestionLimits::fromArray(is_array($value) ? $value : null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $limits = $value instanceof QuestionLimits
            ? $value
            : QuestionLimits::fromArray(is_array($value) ? $value : null);

        /*
         * El tipo decide que limites se guardan, asi que hay que leerlo del
         * modelo. Si la pregunta cambio de numero a texto en el mismo
         * guardado, min y max se descartan aqui: conservarlos dejaria datos
         * que nadie lee y que alguien acabaria interpretando.
         */
        $type = $model->type;
        $aplicables = $limits->toArrayFor($type);

        return [$key => $aplicables === [] ? null : json_encode($aplicables, JSON_THROW_ON_ERROR)];
    }
}
