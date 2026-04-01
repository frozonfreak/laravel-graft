<?php

namespace Modules\GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureExecution extends Model
{
    use HasUuids;

    protected $fillable = [
        'feature_id', 'tenant_id', 'status', 'started_at', 'completed_at',
        'execution_ms', 'rows_scanned', 'cost_actual', 'error_detail', 'signal_emitted',
    ];

    protected $casts = [
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'error_detail'   => 'array',
        'signal_emitted' => 'boolean',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(FeatureConfig::class, 'feature_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
