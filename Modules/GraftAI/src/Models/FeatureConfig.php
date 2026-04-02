<?php

namespace GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureConfig extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'dsl_version', 'feature_version', 'lifecycle_stage',
        'type', 'data_source', 'pipeline', 'action', 'schedule', 'status',
        'trust_tier', 'cost_estimate', 'pipeline_signature',
        'contributes_to_evolution', 'promoted_to_core',
        'created_by', 'last_executed_at',
    ];

    protected $casts = [
        'pipeline' => 'array',
        'action' => 'array',
        'schedule' => 'array',
        'cost_estimate' => 'array',
        'contributes_to_evolution' => 'boolean',
        'promoted_to_core' => 'boolean',
        'last_executed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FeatureExecution::class, 'feature_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function suspend(): void
    {
        $this->update(['status' => 'suspended']);
        AuditEvent::log($this->tenant_id, $this->id, 'feature_suspended', 'system');
    }

    public function consecutiveFailureCount(): int
    {
        return $this->executions()
            ->latest('started_at')
            ->take(3)
            ->where('status', 'failure')
            ->count();
    }
}
