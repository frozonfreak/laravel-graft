<?php

namespace GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'slug', 'status', 'evolution_settings'];

    protected $casts = [
        'evolution_settings' => 'array',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(FeatureConfig::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(FeatureExecution::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(TenantBudget::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(FeatureSnapshot::class);
    }

    public function currentBudget(): ?TenantBudget
    {
        return $this->budgets()
            ->where('billing_month', (int) now()->format('Ym'))
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function contributesToEvolution(): bool
    {
        return (bool) ($this->evolution_settings['contribute_to_pattern_detection'] ?? false);
    }
}
