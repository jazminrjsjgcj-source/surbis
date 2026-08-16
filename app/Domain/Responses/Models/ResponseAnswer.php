<?php

declare(strict_types=1);

namespace App\Domain\Responses\Models;

use App\Domain\Surveys\Enums\QuestionType;
use App\Domain\Surveys\Models\SurveyQuestion;
use App\Domain\Surveys\Models\SurveyQuestionOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponseAnswer extends Model
{
    protected $fillable = [
        'response_id', 'survey_question_id', 'option_id',
        'question_text', 'question_type', 'option_label', 'value', 'score', 'position',
    ];

    /** @return BelongsTo<Response, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    /** @return BelongsTo<SurveyQuestion, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'survey_question_id');
    }

    /** @return BelongsTo<SurveyQuestionOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestionOption::class, 'option_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'score' => 'integer',
            'position' => 'integer',
        ];
    }
}
