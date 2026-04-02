<?php

namespace GraftAI\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'feature_id', 'event_type', 'actor', 'detail', 'created_at',
    ];

    protected $casts = [
        'detail'     => 'array',
        'created_at' => 'datetime',
    ];

    // Immutable — no updates or deletes
    public static function boot(): void
    {
        parent::boot();

        static::updating(function () {
            throw new \RuntimeException('AuditEvent is immutable.');
        });

        static::deleting(function () {
            throw new \RuntimeException('AuditEvent is immutable.');
        });
    }

    public static function log(
        ?string $tenantId,
        ?string $featureId,
        string $eventType,
        string $actor,
        array $detail = []
    ): self {
        return static::create([
            'tenant_id'  => $tenantId,
            'feature_id' => $featureId,
            'event_type' => $eventType,
            'actor'      => $actor,
            'detail'     => $detail,
            'created_at' => now(),
        ]);
    }
}
