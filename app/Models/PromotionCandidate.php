<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PromotionCandidate extends Model
{
    use HasUuids;

    protected $fillable = [
        'pipeline_signature', 'distinct_tenants', 'distinct_features',
        'weighted_exec_score', 'success_rate', 'avg_feedback_score',
        'risk_tier', 'status', 'reviewed_by', 'reviewed_at',
        'promoted_at', 'dsl_version_after',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'promoted_at' => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(string $reviewer): void
    {
        $this->update([
            'status'      => 'approved',
            'reviewed_by' => $reviewer,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(string $reviewer): void
    {
        $this->update([
            'status'      => 'rejected',
            'reviewed_by' => $reviewer,
            'reviewed_at' => now(),
        ]);
    }

    public function isAutoApprovable(): bool
    {
        return $this->risk_tier === 'low';
    }
}
