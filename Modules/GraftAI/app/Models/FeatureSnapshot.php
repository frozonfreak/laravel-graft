<?php

namespace Modules\GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureSnapshot extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'snapshot_type', 'label', 'features',
        'dsl_version_at_snapshot', 'created_by', 'created_at',
    ];

    protected $casts = [
        'features'   => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(string $tenantId, string $label, string $dslVersion, string $createdBy): self
    {
        $features = FeatureConfig::where('tenant_id', $tenantId)->get()->toArray();

        return static::create([
            'tenant_id'              => $tenantId,
            'snapshot_type'          => 'tenant',
            'label'                  => $label,
            'features'               => $features,
            'dsl_version_at_snapshot' => $dslVersion,
            'created_by'             => $createdBy,
            'created_at'             => now(),
        ]);
    }

    public static function system(string $label, string $dslVersion): self
    {
        $allFeatures = FeatureConfig::all()->toArray();

        return static::create([
            'tenant_id'              => null,
            'snapshot_type'          => 'system',
            'label'                  => $label,
            'features'               => $allFeatures,
            'dsl_version_at_snapshot' => $dslVersion,
            'created_by'             => 'system',
            'created_at'             => now(),
        ]);
    }
}
