<?php

namespace GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBudget extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id', 'billing_month', 'monthly_limit', 'consumed', 'status',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function addCost(float $cost): void
    {
        $this->increment('consumed', (int) ceil($cost));

        $consumed = $this->fresh()->consumed;

        if ($consumed >= $this->monthly_limit) {
            $this->update(['status' => 'halted']);
        } elseif ($consumed >= $this->monthly_limit * 0.80) {
            $this->update(['status' => 'warning']);
        }
    }

    public function isHalted(): bool
    {
        return $this->status === 'halted';
    }

    public static function getOrCreate(string $tenantId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId, 'billing_month' => (int) now()->format('Ym')],
            ['monthly_limit' => 5000, 'consumed' => 0, 'status' => 'active']
        );
    }
}
