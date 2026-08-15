<?php

declare(strict_types=1);

namespace App\Domain\Surveys\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Esta pregunta se muestra si la pregunta N respondio <opcion>."
 *
 * RF-AO-BLD-007.
 */
class SurveyQuestionCondition extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'survey_question_id',
        'organization_id',
        'depends_on_question_id',
        'option_id',
    ];

    /** @return BelongsTo<SurveyQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    /** @return BelongsTo<SurveyQuestion, $this> */
    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'depends_on_question_id');
    }

    /** @return BelongsTo<SurveyQuestionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestionOption::class, 'option_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
