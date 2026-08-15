<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use App\Domain\Surveys\Enums\OptionDisplay;
use Database\Factories\SurveyQuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionOption extends Model
{
    /** @use HasFactory<SurveyQuestionOptionFactory> */
    use HasFactory;

    use HasPublicUlid;

    protected $fillable = [
        'survey_question_id',
        'organization_id',
        'label',
        'value',
        'score',
        'display',
        'appearance',
        'position',
    ];

    /** @return BelongsTo<SurveyQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * El nombre accesible. RF-AO-BLD-005.
     *
     * Siempre la etiqueta, se muestre o no. Una opcion que solo ensena una
     * imagen sigue necesitando como llamarse para quien no la ve.
     */
    public function accessibleName(): string
    {
        return $this->label;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'display' => OptionDisplay::class,
            'appearance' => 'array',
            'score' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SurveyQuestionOptionFactory::new();
    }
}
