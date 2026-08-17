<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Deployments\Models\Deployment;
use App\Domain\Organizations\Models\Area;
use App\Domain\Organizations\Models\Branch;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\StaffMember;
use App\Domain\Surveys\Models\SurveyVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un dia de indicadores para una combinacion concreta.
 */
class ResponseMetric extends Model
{
    protected $fillable = [
        'organization_id', 'day', 'deployment_id', 'survey_version_id',
        'branch_id', 'area_id', 'staff_member_id', 'channel',
        'responses', 'invalidated', 'score_sum', 'max_score_sum', 'scored_responses',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<StaffMember, $this> */
    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    /** @return BelongsTo<Deployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /** @return BelongsTo<SurveyVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SurveyVersion::class, 'survey_version_id');
    }

    /**
     * @param  Builder<ResponseMetric>  $query
     * @return Builder<ResponseMetric>
     */
    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'responses' => 'integer',
            'invalidated' => 'integer',
            'score_sum' => 'integer',
            'max_score_sum' => 'integer',
            'scored_responses' => 'integer',
        ];
    }
}
