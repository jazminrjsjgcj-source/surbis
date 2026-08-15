<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Casts\QuestionLimitsCast;
use App\Domain\Surveys\Enums\QuestionType;
use Database\Factories\SurveyQuestionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SurveyQuestion extends Model
{
    /** @use HasFactory<SurveyQuestionFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'survey_version_id',
        'organization_id',
        'type',
        'text',
        'help',
        'is_required',
        'limits',
        'position',
    ];

    /** @return BelongsTo<SurveyVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SurveyVersion::class, 'survey_version_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<SurveyQuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(SurveyQuestionOption::class)->orderBy('position');
    }

    /**
     * La condicion que decide si esta pregunta se muestra. RF-AO-BLD-007.
     *
     * HasOne y no HasMany: una sola condicion por pregunta. La base lo
     * garantiza con un unique sobre survey_question_id.
     *
     * @return HasOne<SurveyQuestionCondition, $this>
     */
    public function condition(): HasOne
    {
        return $this->hasOne(SurveyQuestionCondition::class, 'survey_question_id');
    }

    /**
     * Las preguntas que dependen de ESTA.
     *
     * Sirve para decir por que no se puede borrar o mover: "no se puede" sin
     * decir que lo impide obliga a probar una por una.
     *
     * @return HasMany<SurveyQuestionCondition, $this>
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(SurveyQuestionCondition::class, 'depends_on_question_id');
    }

    public function hasOptions(): bool
    {
        return $this->type->hasOptions();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'limits' => QuestionLimitsCast::class,
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SurveyQuestionFactory::new();
    }
}
